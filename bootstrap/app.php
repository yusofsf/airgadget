<?php

use App\Http\Middleware\EnforceHttps;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\LogStoreActivity;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\SetSeoHeaders;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->web(
            prepend: [EnforceHttps::class, LogStoreActivity::class],
            append: [
                SecurityHeaders::class,
                SetSeoHeaders::class,
                HandleInertiaRequests::class,
                AddLinkHeadersForPreloadedAssets::class,
            ],
        );

        //
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
