<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Cviebrock\EloquentSluggable\Sluggable;
use Illuminate\Support\Facades\Storage;
use App\Models\User;

class Video extends Model
{
    use Sluggable;
    use HasFactory;
    protected $fillable = [
        'user_id',
        'title',
        'slug',
        'description',
        'original_file_url',
        'playback_url',
        'video_duration',
        'original_filesize',
        'original_resolution',
        'original_bitrate',
        'original_video_codec',
        'upload_duration',
        'upload_speed',
        'process_time',
        'poster',
        'file_name',
        'is_transcoded',
        'status',
        'skip_intro_time',
        'sequence',
        'stg_autoplay',
        'stg_muted',
        'stg_loop',
        'stg_autopause',
        'stg_preload_configration',
        'custom_script',
        'permission_error_message'
    ];
    protected $appends = ['video_original_type'];
    public function sluggable(): array
    {
        return [
            'slug' => [
                'source' => ['title', 'id', 'user_id'],
            ]
        ];
    }

    // protected function processTime(): Attribute
    // {
    //     return Attribute::make(
    //         get: fn ($value) => $value > 0 && $value != null ? gmdate("H:i:s", $value) . ' Seconds' : '00:00:00 Seconds',
    //     );
    // }

    protected function getCreatedByAttribute($value)
    {
        return User::find($this->user_id)->name;
    }

    protected function originalFilesize(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => round($value / 1024 / 1024, 2) . ' MB',
        );
    }
    protected function getOriginalFilesizeRawAttribute()
    {
        return (int)$this->attributes['original_filesize'];
    }

    protected function originalBitrate(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => round($value / 1000 / 1000, 2) * 0.125 . ' Mbps',
        );
    }

    protected function getVideoOriginalTypeAttribute()
    {
        $getVideoType = explode('.', $this->attributes['original_file_url']);
        $getVideoType = end($getVideoType);
        return $getVideoType;
    }

    // protected function getVideoDurationAttribute()
    // {
    //     return $this->attributes['video_duration'] > 0 && $this->attributes['video_duration'] != null ? gmdate("H:i:s", $this->attributes['video_duration']) . ' Seconds' : '00:00:00 Seconds';
    // }

    protected function getVideoDurationIsoFormatAttribute()
    {
        $seconds = $this->attributes['video_duration'];

        $hours = floor($seconds / 3600);
        $seconds = $seconds % 3600;

        $minutes = floor($seconds / 60);
        $seconds = $seconds % 60;

        return sprintf('P%02dH%02dM%dS', $hours, $minutes, $seconds);
    }
    protected function getVideoRawDurationAttribute()
    {
        return $this->attributes['video_duration'];
    }

    protected function getPosterAttribute()
    {
        $userId = $this->attributes['user_id'];
        $fileName = $this->attributes['file_name'];
        $img = $this->attributes['poster'];

        $imagepath = "$userId/$fileName/$img";
        return '/uploads/' . $imagepath;
    }
}
