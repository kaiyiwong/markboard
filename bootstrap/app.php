<?php

use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\SyncHub;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        apiPrefix: 'api/v1',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Sync runs first, before route-model binding looks a project up in the index.
        $middleware->web(prepend: [SyncHub::class], append: [HandleInertiaRequests::class]);
        $middleware->api(prepend: [SyncHub::class]);
        // The API trims text the way the checker does (FileEditRequest), and "" must stay "":
        // an empty proof is refused, while null removes it.
        $middleware->trimStrings(except: [fn (Request $request) => $request->is('api/*')]);
        $middleware->convertEmptyStringsToNull(except: [fn (Request $request) => $request->is('api/*')]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
