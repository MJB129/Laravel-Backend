<?php

namespace App\Http\Middleware;

use App\Models\Video;
use Closure;
use Illuminate\Http\Request;

class DomainMiddleware
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
        try {
            if ($request->header('ActualDomain')) {
                $host = $request->header('ActualDomain');
            } else {
                $host = $request->getHost();
            }
            $domain = parse_url(config('app.url', PHP_URL_HOST));

            $fileName = $request->route('filename');

            $getHost = Video::select('allow_hosts')->where('file_name', $fileName)->first();

            $allowHosts = explode(',', $getHost->allow_hosts);

            if ($getHost->allow_hosts == null || $getHost->allow_hosts == '') {
                return $next($request);
            } else if ($getHost->allow_hosts != null || $getHost->allow_hosts != '') {
                if (in_array($host, $allowHosts)) {
                    return $next($request);
                } else if ($domain == $host) {
                    return $next($request);
                } else {
                    abort(503, 'authorization failed!');
                }
            } else {
                abort(503, 'authorization failed!');
            }
        } catch (\Throwable $e) {
            abort(503, 'authorization failed!');
        }
        return $next($request);
    }
}
