<?php

namespace HiEvents\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckTokenAndIp
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $token = config('app.api_access_token', env('API_ACCESS_TOKEN'));
        $allowedIps = config('app.api_allowed_ips', env('API_ALLOWED_IPS'));

        // Check Token
        if ($token && $request->header('X-API-TOKEN') !== $token) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        // Check IP
        if ($allowedIps) {
            $ips = array_map('trim', explode(',', $allowedIps));
            if (!in_array('*', $ips) && !in_array($request->ip(), $ips)) {
                 return response()->json(['message' => 'Forbidden'], 403);
            }
        }

        return $next($request);
    }
}
