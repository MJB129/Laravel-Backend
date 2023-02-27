<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\File;

class UsersController extends Controller
{
    public function change_password(Request $request)
    {
        $response = Validator::make($request->all(),[
            'current' => 'required',
            'password' => 'required|min:8|confirmed'
        ]);
        if($response->fails()){
            return response()->json([
                'success' => 'false',
                'array' => 'true',
                'message' => json_encode($response->messages())
            ]);
        }
		$user = User::where('id',$request->user()->id)->first();
		$user->setVisible(['password']);
        if(Hash::check($request->post('current'),$user->password)){
            User::where('id',$user->id)->update([
                'password' => Hash::make($request->post('password'))
            ]);
			return response()->json([
				'success' => 'true',
				'message' => 'password changed'
			]);
        }else{
            return response()->json([
                'success' => 'false',
                'message' => 'current password is wrong'
            ]);
        }

    }

    public function uploadAvatar(Request $request)
    {
        if ($request->hasFile('avatar')) {
            $fileName = 'avatar.' . request()->file('avatar')->getClientOriginalExtension();
            $save_path = Auth::user()->id . '/avatar';
            if (File::exists(public_path('uploads/' . $save_path . '/' . $fileName))) {
                File::delete(public_path('uploads/' . $save_path . '/' . $fileName));
            }
            request()->file('avatar')->move(public_path('uploads/' . $save_path), $fileName);
            User::where('id',$request->user()->id)->update([
                'avatar' => 'uploads/' . $save_path . '/' . $fileName
            ]);
            $user = User::where('id',$request->user()->id)->first([
                'email',
                'first_name',
                'last_name',
                'avatar',
                'role'
            ]);

            return response()->json([
                'success' => 'true',
                'message' => 'file has been uploaded',
                'url' => 'uploads/' . $save_path . '/' . $fileName,
                'user' => $user
            ]);
        }else{
            return response()->json([
                'success' => 'false',
                'message' => 'file not found'
            ]);
        }
    }
    public function get_current_user(Request $request)
    {
        $user = User::where('id',$request->user()->id)->first([
            'email',
            'first_name',
            'last_name',
            'avatar',
            'role'
        ]);
        return response()->json(['profileInfo' => $user]);
    }
    public function UpdateProfile(Request $request)
    {
        $response = Validator::make($request->all(),[
            'first_name' => 'required',
            'last_name' => 'required',
            'email' => 'required|email'
        ]);
        if($response->fails()){
            return response()->json([
                'success' => 'false',
                'message' => json_encode($response->messages())
            ]);
        }

        User::where('id',$request->user()->id)->update([
            'first_name' => $request->post('first_name'),
            'last_name' => $request->post('last_name'),
            'email' => $request->post('email'),
        ]);
        $user = User::where('id',$request->user()->id)->first([
            'email',
            'first_name',
            'last_name',
            'avatar',
            'role'
        ]);
        return response()->json([
            'success' => 'true',
            'message' => 'profile updated',
            'user' => $user
        ]);
    }
}
