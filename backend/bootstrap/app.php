<?php

use App\Http\Middleware\EnsureAccountRole;
use App\Http\Middleware\EnsureInternPasswordChanged;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Console\Scheduling\Schedule;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->statefulApi();
        $middleware->alias([
            'account-role' => EnsureAccountRole::class,
            'intern.password-changed' => EnsureInternPasswordChanged::class,
        ]);
    })
    ->withSchedule(function (Schedule $schedule): void {
        $schedule->command('company-work-items:send-deadline-notifications')
            ->dailyAt('09:00')
            ->timezone('Asia/Manila')
            ->withoutOverlapping();
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
