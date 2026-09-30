<?php

use App\Console\Commands\AddUserToGroupCommand;
use App\Console\Commands\CreateGroupCommand;
use App\Console\Commands\RatingsDetailCommand;
use App\Http\Middleware\EnsureActiveGroup;
use App\Http\Middleware\EnsureUserIsOrganizer;
use App\Support\ActiveGroup;
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
        RatingsDetailCommand::class,
        CreateGroupCommand::class,
        AddUserToGroupCommand::class,
    ])
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->trustProxies(at: [
            '10.0.0.0/8',
            '172.16.0.0/12',
            '192.168.0.0/16',
        ]);

        $middleware->alias([
            'organizer' => EnsureUserIsOrganizer::class,
            'group' => EnsureActiveGroup::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })
    ->withSingletons([
        ActiveGroup::class,
    ])->create();
