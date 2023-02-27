<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class AuthenticatedSessionController extends Controller
{
    /**
     * Handle an incoming authentication request.
     *
     * @param  \App\Http\Requests\Auth\LoginRequest  $request
     * @return \Illuminate\Http\Response
     */
    public function store(LoginRequest $request)
    {
        try {
            $user = User::where('email', $request['email'])->firstOrFail();
            if ($user) {
                $request->authenticate();
                $request->session()->regenerate();

                $token = $user->createToken('authToken', ['*'], Carbon::now()->addDays(7))->plainTextToken;

                return response()->json(['token' => $token, 'user' => $user], 200);
            } else {
                return response()->json(['error' => 'User not found!'], 400);
            }
        } catch (\Throwable $e) {
            Log::error($e->getMessage());
            return response()->json(['error' => $e->getMessage()], 401);
        }
    }

    /**
     * Destroy an authenticated session.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function destroy(Request $request)
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return response()->noContent();
    }
}
