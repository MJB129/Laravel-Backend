<?php

namespace App\Jobs;

ini_set('memory_limit', '5G'); //1 GIGABYTE
ini_set('max_execution_time', 0);
set_time_limit(0);

use App\Common\CommonUtils;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\Middleware\WithoutOverlapping;


use App\Models\Video;
use App\Models\TmpTranscodeProgress;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use ProtoneMedia\LaravelFFMpeg\Support\FFMpeg;
use FFMpeg\Format\Video\X264;
use ProtoneMedia\LaravelFFMpeg\Filters\TileFactory;

use Aws\S3\S3Client;
use Illuminate\Support\Facades\File;

class VideoTranscode implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    public $tries = 1;
    public $maxExceptions = 1;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    private $video_id;
    public function __construct($video_id)
    {
        $this->video_id = $video_id;
    }

    // commented `handle` method for brevity

    public function middleware()
    {
        return [(new WithoutOverlapping($this->video_id))->releaseAfter(30)];
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        try {
            $curTime = microtime(true);
            $video = Video::where('id', $this->video_id)->where('is_transcoded', 0)->first();

            if ($video) {
                $array = array(0 => '1080', 1 => '720', 2 => '480', 3 => '360', 4 => '240');
                $key = array_search($video->original_resolution, $array);
                $newArray = array_slice($array, $key);
                sort($newArray);

                $path = $video->user_id . '/' . $video->file_name . '/' . $video->original_file_url;
                $keyPath = $video->user_id . '/' . $video->file_name;
                $masetPath = $video->user_id . '/' . $video->file_name . '/master.m3u8';
                $vttPath = $video->user_id . '/' . $video->file_name . '/';

                $keyTime = 10;
                switch (true) {
                    case round($video->original_filesize_raw / 1024 / 1024) >= 5120:
                        $keyTime = 240;
                        break;
                    case round($video->original_filesize_raw / 1024 / 1024) >= 3072:
                        $keyTime = 180;
                        break;
                    case round($video->original_filesize_raw / 1024 / 1024) >= 2048:
                        $keyTime = 120;
                        break;
                    case round($video->original_filesize_raw / 1024 / 1024) >= 1024:
                        $keyTime = 60;
                        break;
                    case round($video->original_filesize_raw / 1024 / 1024) >= 500:
                        $keyTime = 20;
                        break;
                    default:
                        $keyTime = 10;
                        break;
                }

                $p240 = (new X264)->setKiloBitrate(150)->setAdditionalParameters(['-c:v', 'h264', '-profile:v', 'main', '-pix_fmt', 'yuv420p', '-movflags', '+faststart']);
                $p360 = (new X264)->setKiloBitrate(276)->setAdditionalParameters(['-c:v', 'h264', '-profile:v', 'main', '-pix_fmt', 'yuv420p', '-movflags', '+faststart']);
                $p480 = (new X264)->setKiloBitrate(750)->setAdditionalParameters(['-c:v', 'h264', '-profile:v', 'main', '-pix_fmt', 'yuv420p', '-movflags', '+faststart']);
                $p720 = (new X264)->setKiloBitrate(2048)->setAdditionalParameters(['-c:v', 'h264', '-profile:v', 'main', '-pix_fmt', 'yuv420p', '-movflags', '+faststart', '-g', '60']);
                $p1080 = (new X264)->setKiloBitrate(4096)->setAdditionalParameters(['-c:v', 'h264', '-profile:v', 'main', '-pix_fmt', 'yuv420p', '-movflags', '+faststart', '-g', '60']);

                $processOutput =  FFMpeg::fromDisk('uploads')->open($path)
                    // ->addFilter(['-c:v', 'h264', '-profile:v', 'main', '-pix_fmt', 'yuv420p', '-movflags', '+faststart', '-f', 'hls'])
                    // ->export()
                    ->exportTile(function (TileFactory $factory) use ($vttPath) {
                        $factory->interval(2)
                            ->scale(160, 90)
                            ->grid(15, 350);
                    })->save($vttPath . 'preview_%02d.jpg')
                    ->exportForHLS()
                    ->setSegmentLength(4)
                    ->withRotatingEncryptionKey(function ($filename, $contents) use ($keyPath, $keyTime) {
                        Storage::disk('uploads')->put("{$keyPath}/$filename", $contents);
                    }, $keyTime);

                $is_portrait = $video->original_width < $video->original_resolution ? true : false;
                foreach ($newArray as $key => $value) {
                    if ($value == '240') {
                        $processOutput->addFormat($p240, function ($media) use ($is_portrait) {
                            // $ratio = $v_height / 240;
                            // $width = round($v_width / $ratio);
                            // Log::info('width for 240: ' . $width);
                            // $media->scale($width, 240);
                            if (!$is_portrait) {
                                $media->scale(426, 240);
                            }
                        });
                    } else if ($value == '360') {
                        $processOutput->addFormat($p360, function ($media) use ($is_portrait) {
                            if (!$is_portrait) {
                                $media->scale(640, 360);
                            }
                        });
                    } else if ($value == '480') {
                        $processOutput->addFormat($p480, function ($media) use ($is_portrait) {
                            if (!$is_portrait) {
                                $media->scale(854, 480);
                            }
                        });
                    } else if ($value == '720') {
                        $processOutput->addFormat($p720, function ($media) use ($is_portrait) {
                            if (!$is_portrait) {
                                $media->scale(1280, 720);
                            }
                        });
                    } else if ($value == '1080') {
                        $processOutput->addFormat($p1080, function ($media) use ($is_portrait) {
                            if (!$is_portrait) {
                                $media->scale(1920, 1080);
                            }
                            // $media->addFilter(function ($filters, $in, $out) {
                            //     $filters->custom($in, 'scale=1920:1080', $out); // $in, $parameters, $out
                            // });
                        });
                    }
                }

                $processOutput->useSegmentFilenameGenerator(function ($name, $format, $key, callable $segments, callable $playlist) {
                    if ($format->getKiloBitrate() == 150) {
                        $segments("{$name}-240-%03d.ts");
                        $playlist("{$name}-240.m3u8");
                    } else if ($format->getKiloBitrate() == 276) {
                        $segments("{$name}-360-%03d.ts");
                        $playlist("{$name}-360.m3u8");
                    } else if ($format->getKiloBitrate() == 750) {
                        $segments("{$name}-480-%03d.ts");
                        $playlist("{$name}-480.m3u8");
                    } else if ($format->getKiloBitrate() == 2048) {
                        $segments("{$name}-720-%03d.ts");
                        $playlist("{$name}-720.m3u8");
                    } else if ($format->getKiloBitrate() == 4096) {
                        $segments("{$name}-1080-%03d.ts");
                        $playlist("{$name}-1080.m3u8");
                    }
                })
                    ->onProgress(function ($percentage) use ($video, $newArray) {
                        if ($percentage == 100) {
                            $this->updateTranscodeStatus($percentage, 1, $video->file_name, $newArray);
                        } else {
                            $this->updateTranscodeStatus($percentage, 0, $video->file_name, $newArray);
                        }
                    })
                    ->save($masetPath);
                $this->updateVideoStatus($video->id, 1, 1);
                // get time difference in milliseconds
                $timeConsumed = round(microtime(true) - $curTime, 3);
                $this->updateVideoProcessTime($video->id, $timeConsumed);

                // check default storage and move
                $storage = CommonUtils::getDefaultStorage();
                if (!$storage) {
                    return true;
                }
                if ($storage->type === 'S3') {
                    // s3 storage
                    $options = json_decode($storage->options);
                    $client = new S3Client([
                        'region' => $options->DEFAULT_REGION,
                        'version' => 'latest',
                        'credentials' => [
                            'key' => $options->ACCESS_KEY_ID,
                            'secret' => $options->SECRET_ACCESS_KEY,
                        ]
                    ]);
                    // upload folder
                    $params = [
                        'before' => function (\Aws\Command $command) {
                            $command['ACL'] = strpos($command['Key'], 'CONFIDENTIAL') === false ? 'public-read' : 'private';
                        }
                    ];
                    $client->uploadDirectory(public_path('/uploads/' . $keyPath), $options->BUCKET, '/uploads/' . $keyPath, $params);
                    // remove current directory
                    File::deleteDirectory(public_path('uploads/' . $keyPath));
                }
                return true;
            } else {
                return true;
            }
        } catch (\Throwable $e) {
            Log::info("VideoTranscode=> exception " . $e->getMessage());
            $this->updateVideoStatus($this->video_id, 2, 2);
            if ($this->attempts() > 1) {
                return;
            }
        }
    }

    public function updateTranscodeStatus($progress, $is_complete, $file_name, $fileFormatArray)
    {
        $lastFormat = last($fileFormatArray);

        //iniatially set the progress to 1%
        $progress = $progress == 0 ? 1 : $progress;

        foreach ($fileFormatArray as $key => $format) {
            if ($format == '240') {
                if ($lastFormat == '240') {
                    $newProgress = $progress;
                } else {
                    $newProgress = ($progress + 20)  >= 99 ? 100 : ($progress + 20);
                }
                TmpTranscodeProgress::where('file_name', $file_name)->where('file_format', $format)->update(['progress' => $newProgress, 'is_complete' => $is_complete]);
            } elseif ($format == '360') {
                if ($lastFormat == '360') {
                    $newProgress = $progress;
                } else {
                    $newProgress = ($progress + 10) >= 99 ? 100 : ($progress + 10);
                }
                TmpTranscodeProgress::where('file_name', $file_name)->where('file_format', $format)->update(['progress' => $newProgress, 'is_complete' => $is_complete]);
            } elseif ($format == '480') {
                if ($lastFormat == '480') {
                    $newProgress = $progress;
                } else {
                    $newProgress = ($progress + 5) >= 99 ? 100 : ($progress + 5);
                }
                TmpTranscodeProgress::where('file_name', $file_name)->where('file_format', $format)->update(['progress' => $newProgress, 'is_complete' => $is_complete]);
            } elseif ($format == '720') {
                if ($lastFormat == '720') {
                    $newProgress = $progress;
                } else {
                    $newProgress = ($progress + 2) >= 99 ? 100 : ($progress + 2);
                }
                TmpTranscodeProgress::where('file_name', $file_name)->where('file_format', $format)->update(['progress' => $newProgress, 'is_complete' => $is_complete]);
            } elseif ($format == '1080') {
                TmpTranscodeProgress::where('file_name', $file_name)->where('file_format', $format)->update(['progress' => $progress, 'is_complete' => $is_complete]);
            }
        }
    }
    public function updateVideoStatus($video_id, $status, $is_transcoded)
    {
        $updated = Video::where('id', $video_id)->update(['status' => $status, 'is_transcoded' => $is_transcoded]);
        if ($updated) {
            $this->deleteTranscodeStatus($video_id);
        }
    }

    public function deleteTranscodeStatus($video_id)
    {
        TmpTranscodeProgress::where('video_id', $video_id)->delete();
    }

    public function updateVideoProcessTime($video_id, $processTime)
    {
        Video::where('id', $video_id)->update(['process_time' => $processTime]);
    }
}
