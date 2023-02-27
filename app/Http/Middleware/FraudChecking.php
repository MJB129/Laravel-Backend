<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class FraudChecking
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): (\Illuminate\Http\Response|\Illuminate\Http\RedirectResponse)  $next
     * @return \Illuminate\Http\Response|\Illuminate\Http\RedirectResponse
     */
    public function handle(Request $request, Closure $next)
    {
        $isPass = false;
        $mobile_agents = '!(tablet|pad|mobile|phone|symbian|android|ipod|ios|blackberry|webos)!i';
        if (isset($_SERVER['HTTP_RANGE']) && !isset($_SERVER['HTTP_COOKIE'])) {
            //\Log::info('HTTP_RANGE');
            $isPass = false;
        } elseif (strpos($_SERVER['HTTP_USER_AGENT'], 'MSIE') !== FALSE) {
            $isPass = true;
        } elseif (strpos($_SERVER['HTTP_USER_AGENT'], 'Firefox') !== FALSE) {
            $isPass = true;
        } elseif (strpos($_SERVER['HTTP_USER_AGENT'], 'Chrome') !== FALSE) {
            $isPass = true;
        } elseif (strpos($_SERVER['HTTP_USER_AGENT'], 'Safari') !== FALSE) {
            $isPass = true;
        } elseif (strpos($_SERVER['HTTP_USER_AGENT'], 'Opera') !== FALSE) {
            $isPass = true;
        } elseif (strpos($_SERVER['HTTP_USER_AGENT'], 'UCBrowser') !== FALSE) {
            $isPass = true;
        } elseif (preg_match($mobile_agents, $_SERVER['HTTP_USER_AGENT'])) {
            $isPass = true;
        } else {
            $proxy_headers = array(
                'HTTP_VIA',
                'HTTP_X_FORWARDED_FOR',
                'HTTP_FORWARDED_FOR',
                'HTTP_X_FORWARDED',
                'HTTP_FORWARDED',
                'HTTP_CLIENT_IP',
                'HTTP_FORWARDED_FOR_IP',
                'VIA',
                'X_FORWARDED_FOR',
                'FORWARDED_FOR',
                'X_FORWARDED',
                'FORWARDED',
                'CLIENT_IP',
                'FORWARDED_FOR_IP',
                'HTTP_PROXY_CONNECTION'
            );
            foreach ($proxy_headers as $x) {
                if (isset($_SERVER[$x])) {
                    $isPass = false;
                } else {
                    $isPass = true;
                }
            }
        }


        if (!$isPass) {
            //\Log::info('FraudChecking: Hey cheating, I caught you');
            abort(503, 'Hey cheating, I caught you');
        } else {
            return $next($request);
        }
    }
}
