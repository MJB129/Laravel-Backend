<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use App\Models\User;
use App\Models\Setting;
use App\Models\Video;
use App\Models\Storage as StorageInfo;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class AdminController extends Controller
{
    //
    public function load()
    {
        try {
            $settings = Setting::all();
            $storages = StorageInfo::all();
            $info1 = null;
            if ($settings && count($settings) > 0) {
                $info1 = $settings[0];
            }
            $info2 = [[
                'id' => null,
                'name' => 'Server Storage',
                'type' => 'server'
            ]];
            if ($storages && count($storages)) {
                foreach ($storages as $s) {
                    $info2[] = [
                        'id' => $s->id,
                        'name' =>  $s->name,
                        'type' => $s->type
                    ];
                }
            }
            $users = User::count();
            $videos = Video::count();
            $transcoded = Video::where('is_transcoded', 1)->count();
            $failed = Video::where('is_transcoded', 0)->count();
            $progress = Video::where('is_transcoded', 2)->count();

            $info3 = [
                'count' => $videos, 'transcoded' => $transcoded, 'failed' => $failed, 'progress' => $progress
            ];

            return response()->json(['setting' => $info1, 'storages' => $info2, 'users' => $users, 'videos' => $info3], 200);
        } catch (\Throwable $e) {
            Log::error('admin load: ' . $e->getMessage());
            return response()->json(['error' => 'Error while loading info!'], 400);
        }
    }

    public function update(Request $request)
    {
        try {
            $settings = Setting::all();
            $setting = $settings[0];
            $setting->max_video_size = $request->max_video_size;
            $setting->video_limit = $request->video_limit;
            $setting->storage_id = $request->storage_id;

            $setting->save();
            return response()->json('Saved', 200);
        } catch (\Throwable $e) {
            Log::error('admin update: ' . $e->getMessage());
            return response()->json(['error' => 'Error while update default configuration!'], 400);
        }
    }

    public function loadUsers()
    {
        try {
            $users = User::all();
            return response()->json(['users' => $users], 200);
        } catch (\Throwable $e) {
            Log::error('admin load user: ' . $e->getMessage());
            return response()->json(['error' => 'Error while loading users!'], 400);
        }
    }
    public function createUser(Request $request)
    {
        try {
            // validate email
            $found = User::where('email', $request->email)->first();
            if ($found) {
                return response()->json(['error' => 'Email is already taken!'], 400);
            }
            $user = new User();
            $user->email = $request->email;
            $user->first_name = $request->first_name;
            $user->last_name = $request->last_name;
            $user->role = $request->role;
            $user->password = Hash::make($request->password);
            $user->save();

            return response()->json(['user' => $user], 201);
        } catch (\Throwable $e) {
            Log::error('create user: ' . $e->getMessage());
            return response()->json(['error' => 'Eror while creating user!'], 400);
        }
    }

    public function updateUser(Request $request, $id)
    {
        try {
            $user = User::find($id);
            if (!$user) {
                return response()->json(['error' => 'User not found!']);
            }
            $user->email = $request->email;
            $user->first_name = $request->first_name;
            $user->last_name = $request->last_name;
            $user->role = $request->role;
            if ($request->password) {
                $user->password = Hash::make($request->password);
            }
            $user->save();

            return response()->json(['user' => $user], 200);
        } catch (\Throwable $e) {
            Log::error('update user: ' . $e->getMessage());
            return response()->json(['error' => 'Error while updating user!'], 400);
        }
    }

    public function deleteUser($id)
    {
        try {
            $user = User::find($id);
            if (!$user) {
                return response()->json(['error' => 'User not found!']);
            }
            // delete user's videos
            File::deleteDirectory(public_path('uploads/' . $id));
            $user->delete();
            $users = User::all();
            return response()->json(['users' => $users], 200);
        } catch (\Throwable $e) {
            Log::error('update user: ' . $e->getMessage());
            return response()->json(['error' => 'Error while deleting user!'], 400);
        }
    }

    public function loadStorages()
    {
        try {
            $storages = StorageInfo::all();
            $settings = Setting::all();
            $setting = $settings[0];
            return response()->json(['storages' => $storages, 'defaultId' => $setting->storage_id], 200);
        } catch (\Throwable $e) {
            Log::error('admin load storages: ' . $e->getMessage());
            return response()->json(['error' => 'Error while loading storages!'], 400);
        }
    }

    public function createStorage(Request $request)
    {
        try {
            $storage = new StorageInfo();
            $storage->type = $request->type;
            $storage->name = $request->name;
            $storage->options = $request->options;
            $storage->save();

            return response()->json(['storage' => $storage], 201);
        } catch (\Throwable $e) {
            Log::error('create storage: ' . $e->getMessage());
            return response()->json(['error' => 'Error while creating storage!'], 400);
        }
    }

    public function updateStorage(Request $request, $id)
    {
        try {
            $storage = StorageInfo::find($id);
            if (!$storage) {
                return response()->json(['error' => 'Storage not found!']);
            }
            $storage->type = $request->type;
            $storage->name = $request->name;
            $storage->options = $request->options;
            $storage->save();

            return response()->json(['storage' => $storage], 200);
        } catch (\Throwable $e) {
            Log::error('update storage: ' . $e->getMessage());
            return response()->json(['error' => 'Error while updating storage!'], 400);
        }
    }

    public function deleteStorage($id)
    {
        try {
            $storage = StorageInfo::find($id);
            if (!$storage) {
                return response()->json(['error' => 'Storage not found!'], 400);
            }
            $settings = Setting::all();
            $setting = $settings[0];
            if ($setting->storage_id === $id) {
                $setting->storage_id = null;
                $setting->save();
            }
            $storage->delete();
            return true;
        } catch (\Throwable $e) {
            Log::error('delete storage: ' . $e->getMessage());
            return response()->json(['error' => 'Error while deleting storage!'], 400);
        }
    }
}
