<?php

use App\Console\Commands\AuthenticateMobileSentrixCommand;
use App\Console\Commands\RefreshMobileSentrixPartCommand;
use App\Console\Commands\SyncMobileSentrixCategoriesCommand;
use App\Console\Commands\SyncMobileSentrixPartsCommand;
use App\Console\Commands\TestMobileSentrixConnectionCommand;
use App\Http\Middleware\AdminMiddleware;
use App\Http\Middleware\CustomerMiddleware;
use App\Http\Middleware\NoAdminCartMiddleware;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withCommands([
        AuthenticateMobileSentrixCommand::class,
        SyncMobileSentrixCategoriesCommand::class,
        SyncMobileSentrixPartsCommand::class,
        RefreshMobileSentrixPartCommand::class,
        TestMobileSentrixConnectionCommand::class,
    ])
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'admin' => AdminMiddleware::class,
            'customer' => CustomerMiddleware::class,
            'no_admin_cart' => NoAdminCartMiddleware::class,
        ]);

        $middleware->validateCsrfTokens(except: [
            'webhooks/stripe',
            'webhooks/paypal',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );
    })->create();
