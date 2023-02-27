<?php

use App\Http\Controllers\VideoController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\UsersController;

use App\Models\Video;
use App\Models\Storage as StorageInfo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use ProtoneMedia\LaravelFFMpeg\Support\FFMpeg;


/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::middleware(['auth:sanctum'])->get('/user', function (Request $request) {
    return $request->user();
});

Route::get('/videos/by-filename/{filename}', [VideoController::class, 'loadByName'])->name('video.loadbyname');

Route::prefix('videos')->group(function () {
    Route::middleware(['auth:sanctum'])->group(function () {
        Route::get('/', [VideoController::class, 'loadAll'])->name('video.all');
        Route::get('/by-slug/{slug}/{show?}', [VideoController::class, 'loadBySlug'])->name('video.load');
        Route::get('/transcodes/{slug}', [VideoController::class, 'loadTranscode'])->name('video.transcodes');
        Route::put('/{id}', [VideoController::class, 'updateVideo'])->name('video.update');
        Route::post('/{id}/update-poster', [VideoController::class, 'updatePoster'])->name('video.updatepoaster');
        Route::delete('/{id}', [VideoController::class, 'deleteVideo'])->name('video.delete');
        Route::post('/file-upload', [VideoController::class, 'fileUpload'])->name('video.fileupload');
        Route::post('/create-video', [VideoController::class, 'createVideo'])->name('video.create');
        Route::get('/retry-transcode/{id}', [VideoController::class, 'retryTranscode'])->name('video.transcoderetry');
    });
});

Route::prefix('videoimas')->group(function () {
    Route::middleware(['auth:sanctum'])->group(function () {
        Route::post('/', [VideoController::class, 'createIma'])->name('ima.create');
        Route::put('/{id}', [VideoController::class, 'updateIma'])->name('ima.update');
        Route::delete('/{id}', [VideoController::class, 'deleteIma'])->name('ima.delete');
    });
});
Route::prefix('User')->group(function () {
    Route::middleware(['auth:sanctum'])->group(function () {
        Route::post('/Password/Change', [UsersController::class, 'change_password'])->name('users.change_password');
        Route::post('uploadAvatar', [UsersController::class, 'uploadAvatar'])->name('users.uploadAvatar');
        Route::get('Current', [UsersController::class, 'get_current_user'])->name('users.get_current_user');
        Route::post('Update/Profile', [UsersController::class, 'UpdateProfile'])->name('users.UpdateProfile');
    });
});
// Route::get('/videos/playback/{userid}/{filename}/{playlist}', function ($userid, $filename, $playlist) {
//     $video = Video::where('file_name', $filename)->first();
//     if ($video->storage_id) {

//         $storage = StorageInfo::find($video->storage_id);
//         if (!$storage) {
//             return false;
//         }
//         $driver = '';
//         if ($storage->type === 'S3') {
//             $driver = 's3';
//         }
//         $options = json_decode($storage->options);
//         Log::info($driver);

//         config(['filesystems.disk.' . $filename => [
//             'driver' => $driver,
//             'key' => $options->ACCESS_KEY_ID,
//             'secret' => $options->SECRET_ACCESS_KEY,
//             'region' => $options->DEFAULT_REGION,
//             'bucket' => $options->BUCKET,
//             'url' => $options->AWS_URL,
//             'endpoint' => $options->AWS_ENDPOINT,
//         ]]);

//         // using other storage
//         $full_url = $video->playback_prefix . '/uploads/' . $userid . '/' . $filename;
//         return FFMpeg::dynamicHLSPlaylist()->fromDisk($filename)->open("uploads/{$userid}/{$filename}/{$playlist}")
//             ->setKeyUrlResolver(function ($key) use ($userid, $filename) {
//                 Log::info('fetching key');
//                 return Storage::disk($filename)->download("uploads/{$userid}/{$filename}/{$key}");
//             })
//             ->setPlaylistUrlResolver(function ($playlistFilename) use ($userid, $filename) {
//                 Log::info('fetching video url');
//                 return route('video.playback', ['userid' => $userid, 'filename' => $filename, 'playlist' => $playlistFilename]);
//             })
//             ->setMediaUrlResolver(function ($mediaFilename) use ($full_url) {
//                 Log::info('fetching media url');
//                 return $full_url . '/' . $mediaFilename;
//             });
//     } else {
//         // using local storage
//         return FFMpeg::dynamicHLSPlaylist()->fromDisk('uploads')->open("{$userid}/{$filename}/{$playlist}")
//             ->setKeyUrlResolver(function ($key) use ($userid, $filename) {
//                 return route('video.key', ['userid' => $userid, 'filename' => $filename, 'key' => $key]);
//                 // $video = Video::where('file_name', $filename)->first();
//                 // return $video->playback_url . '/uploads/' . $userid . '/' . $filename . '/' . $key;
//             })
//             ->setPlaylistUrlResolver(function ($playlistFilename) use ($userid, $filename, $playlist) {
//                 return route('video.playback', ['userid' => $userid, 'filename' => $filename, 'playlist' => $playlistFilename]);
//             })
//             ->setMediaUrlResolver(function ($mediaFilename) use ($userid, $filename) {
//                 // Log::info('url : ' . url("uploads/{$userid}/{$filename}/{$mediaFilename}"));
//                 // $video = Video::where('file_name', $filename)->first();
//                 // return $video->plakback_url . '/uploads/' . $userid . '/' . $filename . '/' . $mediaFilename;
//                 return url("uploads/{$userid}/{$filename}/{$mediaFilename}");
//             });
//     }
// })->name('video.playback');

// Route::get('/videos/secret/{userid}/{filename}/{key}', function ($userid, $filename, $key) {
//     // get video by file name
//     // $video = Video::where('file_name', $filename)->first();
//     // return $video->playback_url . '/uploads/' . $userid . '/' . $filename . '/' . $key;
//     $key = $userid . '/' . $filename . '/' . $key;
//     return Storage::disk('uploads')->download($key);
// })->name('video.key');

Route::prefix('/admin')->group(function () {
    Route::middleware(['auth:sanctum', 'admin'])->group(function () {
        Route::get('/load', [AdminController::class, 'load'])->name('admin.load');
        Route::post('/setting', [AdminController::class, 'update'])->name('admin.update');

        Route::get('/users', [AdminController::class, 'loadUsers'])->name('admin.users');
        Route::post('/user', [AdminController::class, 'createUser']);
        Route::put('/user/{id}', [AdminController::class, 'updateUser']);
        Route::delete('/user/{id}', [AdminController::class, 'deleteUser']);
        Route::get('/storages', [AdminController::class, 'loadStorages'])->name('admin.storages');
        Route::post('/storage', [AdminController::class, 'createStorage']);
        Route::put('/storage/{id}', [AdminController::class, 'updateStorage']);
        Route::delete('/storage/{id}', [AdminController::class, 'deleteStorage']);
    });
});
