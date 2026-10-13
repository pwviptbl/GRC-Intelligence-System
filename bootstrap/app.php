<?php

use App\Http\Middleware\OperationalAuditMiddleware;
use App\Http\Middleware\SecurityHeadersMiddleware;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->append(SecurityHeadersMiddleware::class);
        $middleware->append(OperationalAuditMiddleware::class);

        $middleware->validateCsrfTokens(except: [
            'mcp',
        ]);

        $middleware->alias([
            'role' => App\Http\Middleware::class.'\RoleMiddleware',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
