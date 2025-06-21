<?php
namespace App\Http\Middleware;

use Closure;


use Illuminate\Container\Attributes\Log;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;


class CorsMiddleware
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Log untuk debugging
        // Log::info('CORS Middleware - Request:', [
        //     'method' => $request->getMethod(),
        //     'url' => $request->fullUrl(),
        //     'headers' => $request->headers->all(),
        //     'has_auth' => $request->hasHeader('Authorization'),
        //     'has_ngrok_header' => $request->hasHeader('ngrok-skip-browser-warning'),
        // ]);

        // Handle preflight OPTIONS request
        if ($request->getMethod() === "OPTIONS") {
            return response('', 200)
                ->header('Access-Control-Allow-Origin', '*')
                ->header('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')
                ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With, Accept, Origin, X-CSRF-TOKEN, ngrok-skip-browser-warning, User-Agent')
                ->header('Access-Control-Max-Age', '86400');
        }

        $response = $next($request);

        // Add CORS headers to response
        $response->headers->set('Access-Control-Allow-Origin', '*');
        $response->headers->set('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS');
        $response->headers->set('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With, Accept, Origin, X-CSRF-TOKEN, ngrok-skip-browser-warning, User-Agent');
        $response->headers->set('Access-Control-Expose-Headers', 'Authorization');

        // Log response untuk debugging
        // Log::info('CORS Middleware - Response:', [
        //     'status' => $response->getStatusCode(),
        //     'content_type' => $response->headers->get('Content-Type'),
        // ]);

        return $response;
    }
}


// namespace App\Http\Middleware;

// use Closure;
// use Illuminate\Http\Request;
// use Symfony\Component\HttpFoundation\Response;

// class CorsMiddleware
// {
//     /**
//      * Handle an incoming request.
//      */
//     public function handle(Request $request, Closure $next): Response
//     {
//         // Handle preflight OPTIONS request
//         if ($request->getMethod() === "OPTIONS") {
//             return response('', 200)
//                 ->header('Access-Control-Allow-Origin', '*')
//                 ->header('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')
//                 ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With, Accept, Origin, X-CSRF-TOKEN')
//                 ->header('Access-Control-Max-Age', '86400');
//         }

//         $response = $next($request);

//         // Add CORS headers to response
//         $response->headers->set('Access-Control-Allow-Origin', '*');
//         $response->headers->set('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS');
//         $response->headers->set('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With, Accept, Origin, X-CSRF-TOKEN');
//         $response->headers->set('Access-Control-Expose-Headers', 'Authorization');

//         return $response;
//     }
// }

