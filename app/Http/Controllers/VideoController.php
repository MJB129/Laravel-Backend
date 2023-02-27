<?php

namespace App\Http\Controllers;

use App\Common\CommonUtils;
use App\Models\Video;
use App\Models\Storage as StorageInfo;
use App\Models\TmpTranscodeProgress;
use Cviebrock\EloquentSluggable\Services\SlugService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use ProtoneMedia\LaravelFFMpeg\Support\FFMpeg;

use App\Jobs\VideoTranscode;
use App\Models\Videoima;
use Aws\S3\S3Client;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\File;

class VideoController extends Controller
{
    //
    public function getUniqueVideoId()
    {
        $chars = "bcdfghjklmnpqrstvwxyz";
        $chars .= "BCDFGHJKLMNPQRSTVWXYZ";
        $chars .= "0123456789";
        while (1) {
            $key = '';
            srand((float)microtime() * 1000000);
            for ($i = 0; $i < 10; $i++) {
                $key .= substr($chars, (rand() % (strlen($chars))), 1);
            }
            break;
        }
        return $key;
    }

    public function createTmpTranscodeEntry($original_resolution, $file_name, $video_id)
    {
        $array = array(0 => '1080', 1 => '720', 2 => '480', 3 => '360', 4 => '240');
        $key = array_search($original_resolution, $array);
        $newArray = array_slice($array, $key);
        sort($newArray);
        foreach ($newArray as $key => $format) {
            $res = TmpTranscodeProgress::updateOrCreate([
                'file_name'   => $file_name,
                'video_id'    => $video_id,
                'file_format' => $format
            ], [
                'progress'     => 1,
            ]);
        }
    }

    public function loadAll()
    {
        try {
            $videos = Video::where('user_id', Auth::user()->id)->orderBy('sequence', 'ASC')->get();
            return response()->json(['videos' => $videos]);
        } catch (\Throwable $e) {
            Log::error('Loading videos error: ' . $e->getMessage());
            return response()->json(['videos' => []], 200);
        }
    }

    // file upload endpoint /video/file-upload
    public function fileUpload(Request $request)
    {
        try {
            $allowed_file_types = ['mp4', 'webm', 'mkv', 'wmv', 'avi', 'avchd', 'flv', 'ts', 'mov'];
            $isValid = in_array(request()->file->getClientOriginalExtension(), $allowed_file_types);

            if ($request->file() && $isValid) {
                $fileName = $this->getUniqueVideoId();
                $filePath = $fileName . '.' . request()->file->getClientOriginalExtension();
                $save_path = Auth::user()->id . '/' . $fileName;

                request()->file->move(public_path('uploads/' . $save_path), $filePath);

                return response()->json(['fileName' => $fileName, 'filePath' => $filePath], 200);
            } else {
                return response()->json(['error' => 'Fail to upload file'], 400);
            }
        } catch (\Throwable $e) {
            Log::error('File upload error: ' . $e->getMessage());
            return response()->json(['error' => 'Fail to upload file'], 400);
        }
    }

    public function loadBySlug($slug,$show = '')
    {
        try {
            $video = Video::where('slug', $slug)->first();
            if ($video) {
                // load video_imas
                $videoimas = Videoima::where('video_id', $video->id)->get();
                if($show != 'show'){
                    $video->makeHidden('custom_script_one');
                }
                return response()->json(['video' => $video, 'imas' => $videoimas], 200);
            } else {
                return response()->json(['error' => 'Video not found!'], 400);
            }
        } catch (\Throwable $e) {
            Log::error('Load by slug: ' . $e->getMessage());
            return response()->json(['error' => 'Video not found!'], 400);
        }
    }

    public function loadByName($filename)
    {
        try {
            $video = Video::where('file_name', $filename)->first();
            if ($video) {
                $videoimas = Videoima::where('video_id', $video->id)->get();
                return response()->json(['video' => $video, 'imas' => $videoimas], 200);
            } else {
                return response()->json(['error' => 'Video not found!'], 400);
            }
        } catch (\Throwable $e) {
            Log::error('Load by filename: ' . $e->getMessage());
            return response()->json(['error' => 'Video not found!'], 400);
        }
    }

    // create video endpoint /video/create-video
    public function createVideo(Request $request)
    {
        try {
            $path = Auth::user()->id . '/' . $request->path . '/' . $request->name;
            $media = FFMpeg::fromDisk('uploads')->open($path);

            // get video duration
            $durationInSeconds = 0;
            try {
                $durationInSeconds = $media->getDurationInSeconds();
            } catch (\Throwable $th) {
                Log::error('Get duration failed: ' . $th->getMessage());
            }

            $codec = $media->getVideoStream()->get('codec_name'); // returns a string
            $original_resolution = $media->getVideoStream()->get('height'); // returns an array
            $original_width = $media->getVideoStream()->get('width');
            $bitrate = $media->getVideoStream()->get('bit_rate'); // returns an integer

            $original_filesize = Storage::disk('uploads')->size($path);
            $posterImage = null;

            if ($request->hasFile('poster')) {
                // Process the new image
                $fileName = 'poster.' . request()->file('poster')->getClientOriginalExtension();
                $save_path = Auth::user()->id . '/' . $request->path;
                request()->file('poster')->move(public_path('uploads/' . $save_path), $fileName);
                $posterImage = $fileName;
            } else {
                $point = $durationInSeconds > 10 ? 8 : $durationInSeconds / 2;
                $media->getFrameFromSeconds($point)
                    ->export()
                    ->save(Auth::user()->id . '/' . $request->path . '/' . 'poster.png');
                $posterImage = 'poster.png';
            }

            // create video
            $video = new Video();
            $video->title = $request->title;
            $video->slug = SlugService::createSlug(Video::class, 'slug', $request->title);
            $video->description = $request->description;
            $video->poster = $posterImage;
            $video->original_file_url = $request->name;
            $video->playback_url = 'master.m3u8';
            $video->user_id = Auth::user()->id;
            $video->video_duration = $durationInSeconds;
            $video->original_filesize = $original_filesize;
            $video->original_resolution = $original_resolution;
            $video->original_width = $original_width;
            $video->original_bitrate = $bitrate ? $bitrate : rand(1, 600000);
            $video->original_video_codec = $codec;
            $video->file_name = $request->path;
            $video->is_transcoded = 0;
            $video->upload_duration = $request->uploadDuration ? $request->uploadDuration : 10;

            $storage = CommonUtils::getDefaultStorage();

            if ($storage) {
                $video->storage_id = $storage->id;
                $options = json_decode($storage->options);
                if ($storage->type === 'S3') {
                    $video->playback_prefix = $options->AWS_URL;
                } else if ($storage->type === 'Google Drive') {
                    $video->playback_prefix = $options->PLAYBACK_URL;
                }
            }

            if ($video->save()) {
                $this->createTmpTranscodeEntry($original_resolution, $request->path, $video->id);
                $job = (new VideoTranscode($video->id))->delay(Carbon::now()->addSeconds(10));
                dispatch($job);
                return response()->json(['videoId' => $video->id, 'file' => $request->name]);
            } else {
                return response()->json(['error' => 'Error while saving video!'], 400);
            }
        } catch (\Throwable $e) {
            Log::error('Create vieo error: ' . $e->getMessage());
            return response()->json(['error' => 'Fail to create video information!'], 400);
        }
    }

    // delete video endpoint /video/{id}
    public function deleteVideo($id)
    {
        try {
            $video = Video::find($id);
            if (!$video) {
                return response()->json(['error' => 'Video not found!'], 400);
            }
            if ($video->user_id != Auth::user()->id) {
                return response()->json(['error' => 'You have no permission to remove video!'], 400);
            }
            // delete directory
            if ($video->storage_id) {
                // external storage
                $storage = StorageInfo::find($video->storage_id);
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
                    $client->deleteMatchingObjects($options->BUCKET, 'uploads/' . $video->user_id . '/' . $video->file_name);
                }
            } else {
                File::deleteDirectory(public_path('uploads/' . $video->user_id . '/' . $video->file_name));
            }
            $video->delete();
            return 'success';
        } catch (\Throwable $e) {
            Log::error('Delete video: ' . $e->getMessage());
            return response()->json(['error' => 'Error while deleting video!'], 400);
        }
    }

    public function updateVideo(Request $request, $id)
    {
        try {
            $video = Video::find($id);
            if (!$video) {
                return response()->json(['error' => 'Video not found!'], 400);
            }
            $video->title = $request->title;
            $video->allow_hosts = $request->allow_hosts;
            $video->custom_script = json_encode($request->scripts);
            $video->description = $request->description;
            $video->skip_intro_time = $request->skip_intro_time;
            $video->stg_autopause = $request->stg_autopause;
            $video->stg_autoplay = $request->stg_autoplay;
            $video->stg_loop = $request->stg_loop;
            $video->stg_muted = $request->stg_muted;
            $video->stg_preload_configration = $request->stg_preload_configration;
            $video->permission_error_message = $request->permission_error_message;

            $video->save();
            return response()->json(['video' => $video], 200);
        } catch (\Throwable $e) {
            Log::error('update video :' . $e->getMessage());
            return response()->json(['error' => 'Error while update video information!'], 400);
        }
    }

    public function updatePoster(Request $request, $id)
    {
        try {
            $video = Video::find($id);
            if (!$video) {
                return response()->json(['error' => 'Video not found!'], 400);
            }
            if ($request->hasFile('poster')) {
                $fileName = 'poster.' . request()->file('poster')->getClientOriginalExtension();
                $save_path = Auth::user()->id . '/' . $video->file_name;
                if (File::exists(public_path('uploads/' . $save_path . '/' . $fileName))) {
                    File::delete(public_path('uploads/' . $save_path . '/' . $fileName));
                }
                request()->file('poster')->move(public_path('uploads/' . $save_path), $fileName);
                if ($video->storage_id) {
                    $storage = StorageInfo::find($video->storage_id);
                    if ($storage) {
                        // move to storage
                        if ($storage->type === 'S3') {
                            $options = json_decode($storage->options);
                            $client = new S3Client([
                                'region' => $options->DEFAULT_REGION,
                                'version' => 'latest',
                                'credentials' => [
                                    'key' => $options->ACCESS_KEY_ID,
                                    'secret' => $options->SECRET_ACCESS_KEY,
                                ]
                            ]);
                            $res = $client->putObject([
                                'Bucket' => $options->BUCKET,
                                'Key' => 'uploads/' . $save_path . '/' . $fileName,
                                'Body' => fopen(public_path('uploads/' . $save_path . '/' . $fileName), 'r'),
                                'ACL' => 'public-read',
                            ]);
                            // delete current directory
                            File::deleteDirectory(public_path('uploads/' . $save_path));
                        }
                    }
                }
                $video->poster = $fileName;
                $video->save();
            }
            return $video->poster;
        } catch (\Throwable $e) {
            Log::error('update poster: ' . $e->getMessage());
            return response()->json(['error' => 'fail to update poster!'], 400);
        }
    }

    public function loadTranscode($slug)
    {
        try {
            $video = Video::where('slug', $slug)->first();
            if (!$video) {
                return response()->json(['error' => 'Video not found!'], 400);
            }
            $transcodes = TmpTranscodeProgress::where('video_id', $video->id)->get();

            return response()->json(['video' => $video, 'transcodes' => $transcodes], 200);
        } catch (\Throwable $e) {
            Log::error('load video transcodes: ' . $e->getMessage());
            return response()->json(['error' => 'Error while loading transcodes!'], 400);
        }
    }

    public function createIma(Request $request)
    {
        try {
            $ima = new Videoima();
            $ima->time = $request->time;
            $ima->adsUrl = $request->adsUrl;
            $ima->video_id = $request->video_id;

            $ima->save();

            return response()->json(['ima' => $ima], 201);
        } catch (\Throwable $e) {
            Log::error('create ima: ' . $e->getMessage());
            return response()->json(['error' => 'Error while create IMA'], 400);
        }
    }

    public function updateIma(Request $request, $id)
    {
        try {
            $ima = Videoima::find($id);
            if (!$ima) {
                return response()->json(['error' => 'Ima not found!'], 400);
            }
            $ima->time = $request->time;
            $ima->adsUrl = $request->adsUrl;
            $ima->save();

            return response()->json(['ima' => $ima], 200);
        } catch (\Throwable $e) {
            Log::error('update ima: ' . $e->getMessage());
            return response()->json(['error' => 'Error while update IMA'], 400);
        }
    }

    public function deleteIma($id)
    {
        try {
            $ima = Videoima::find($id);
            if (!$ima) {
                return response()->json(['error' => 'Ima not found!'], 400);
            }
            $video = Video::find($ima->video_id);
            if ($video && $video->user_id != Auth::user()->id) {
                return response()->json(['error' => 'You have no permission to remove Ima!'], 400);
            }
            $ima->delete();
            return true;
        } catch (\Throwable $e) {
            Log::error('delete ima: ' . $e->getMessage());
            return response()->json(['error' => 'Error while delete IMA'], 400);
        }
    }

    public function retryTranscode($id)
    {
        try {
            $video = Video::find($id);
            if (!$video) {
                return response()->json(['error' => 'Video not found!'], 400);
            }
            if ($video->is_transcoded != 2) {
                return response()->json(['error' => 'Video trancode was not failed, Wrong request!'], 400);
            }
            if ($video->user_id != Auth::user()->id) {
                return response()->json(['error' => 'You have no permission for this video!'], 400);
            }
            $video->is_transcoded = 0;
            $video->save();
            $this->createTmpTranscodeEntry($video->original_resolution, $video->file_name, $video->id);
            $job = (new VideoTranscode($video->id))->delay(Carbon::now()->addSeconds(5));
            dispatch($job);
            return true;
        } catch (\Throwable $e) {
            Log::error('retry transcode: ' . $e->getMessage());
            return response()->json(['error' => 'Error while retry transcode'], 400);
        }
    }
}
