<?php

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\HandleCors;
use Spatie\Permission\Exceptions\UnauthorizedException;
use Spatie\Permission\Middleware\RoleMiddleware;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->prepend(HandleCors::class);
        $middleware->alias([
            'role' => RoleMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (AuthenticationException $exception) {
            return response()->json([
                'data' => null,
                'message' => 'Unauthenticated.',
                'status' => 401,
            ], 401);
        });

        $exceptions->render(function (AuthorizationException $exception) {
            return response()->json([
                'data' => null,
                'message' => 'No tienes permiso para realizar esta acción.',
                'status' => 403,
            ], 403);
        });

        $exceptions->render(function (UnauthorizedException $exception) {
            return response()->json([
                'data' => null,
                'message' => 'No tienes permiso para acceder a esta sección.',
                'status' => 403,
            ], 403);
        });

        $exceptions->render(function (NotFoundHttpException $exception) {
            return response()->json([
                'data' => null,
                'message' => 'Recurso no encontrado.',
                'status' => 404,
            ], 404);
        });

        $exceptions->render(function (HttpException $exception) {
            if ($exception->getStatusCode() !== 419) {
                return null;
            }

            return response()->json([
                'data' => null,
                'message' => 'CSRF token mismatch.',
                'status' => 419,
            ], 419);
        });
    })->create();
