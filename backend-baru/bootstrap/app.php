<?php

// use Illuminate\Foundation\Application;
// use Illuminate\Foundation\Configuration\Exceptions;
// use Illuminate\Foundation\Configuration\Middleware;

// return Application::configure(basePath: dirname(__DIR__))
//     ->withRouting(
//         api: __DIR__ . '/../routes/api.php',
//         web: __DIR__ . '/../routes/web.php',
//         commands: __DIR__ . '/../routes/console.php',
//         health: '/up',
//     )
//     ->withMiddleware(function (Middleware $middleware) {
//         // Tambahkan CorsMiddleware sebelum middleware lainnya
//         $middleware->prepend(\App\Http\Middleware\CorsMiddleware::class);

//         // API middleware - tambahkan Sanctum untuk authentication
//         $middleware->api(prepend: [
//             \Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful::class,
//         ]);

//         // DISABLE CSRF untuk API routes tertentu
//         $middleware->validateCsrfTokens(except: [
//             'api/*',  // Disable CSRF untuk semua API routes
//         ]);

//         // Alias untuk middleware
//         $middleware->alias([
//             'role' => \App\Http\Middleware\CheckRole::class,
//             'cors' => \App\Http\Middleware\AddCorsHeaders::class,
//             'verified' => \Illuminate\Auth\Middleware\EnsureEmailIsVerified::class,
//         ]);
//     })
//     ->withExceptions(function (Exceptions $exceptions) {
//         //
//     })->create();



use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        api: __DIR__ . '/../routes/api.php',
        web: __DIR__ . '/../routes/web.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // HANYA gunakan satu CORS middleware - pilih CorsMiddleware yang sudah diperbaiki
        $middleware->prepend(\App\Http\Middleware\CorsMiddleware::class);

        // API middleware - tambahkan Sanctum untuk authentication
        $middleware->api(prepend: [
            \Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful::class,
        ]);

        // DISABLE CSRF untuk API routes
        $middleware->validateCsrfTokens(except: [
            'api/*',  // Disable CSRF untuk semua API routes
        ]);

        // Alias untuk middleware - HAPUS alias 'cors' untuk menghindari konflik
        $middleware->alias([
            'role' => \App\Http\Middleware\CheckRole::class,
            'verified' => \Illuminate\Auth\Middleware\EnsureEmailIsVerified::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();

