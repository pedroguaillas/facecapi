<?php

namespace App\Http\Middleware;

use Closure;
use Exception;
use Tymon\JWTAuth\Http\Middleware\BaseMiddleware;
use Tymon\JWTAuth\Facades\JWTAuth;

class JwtMiddleware extends BaseMiddleware
{

    /**
     * Handle an incoming request.
     *
     * @param \Illuminate\Http\Request $request
     * @param \Closure $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        try {
            $user = JWTAuth::parseToken()->authenticate();
            if (!$user) throw new Exception('User Not Found');
        } catch (Exception $e) {
            if ($e instanceof \Tymon\JWTAuth\Exceptions\TokenInvalidException) {
                return response()->json(['message' => 'Token Invalid'], 401);
            } else if ($e instanceof \Tymon\JWTAuth\Exceptions\TokenExpiredException) {

                // return app(\Tymon\JWTAuth\Http\Middleware\RefreshToken::class)->handle($request, function ($request) use ($next) {         //JWT middleware
                //     return $next($request);
                // });

                // If the token is expired, then it will be refreshed and added to the headers
                // try {
                //     $refreshed = JWTAuth::refresh(JWTAuth::getToken());
                //     $user = JWTAuth::setToken($refreshed)->toUser();
                //     header('Authorization: ' . $refreshed);
                // } catch (\Tymon\JWTAuth\Exceptions\JWTException $e) {
                //     return response()->json(['message'   => 'Cannot refrest again'], 402);
                // }

                return response()->json(['message' => 'TOKEN_EXPIRED'], 402);
            } else {
                if ($e->getMessage() === 'User Not Found') {
                    return response()->json(['message' => 'User Not Found'], 403);
                }
                return response()->json(['message' => 'Authorization Token not found'], 404);
            }
        }

        return $next($request);
    }
}
