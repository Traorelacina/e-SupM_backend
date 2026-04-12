<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use App\Http\Middleware\CheckRole;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {

        $middleware->alias([
            'role' => CheckRole::class,
        ]);

        // CORRECTION : EnsureFrontendRequestsAreStateful est pour les SPA
        // qui utilisent les cookies de session (mode stateful de Sanctum).
        // Ton app utilise des tokens Bearer → ce middleware est inutile
        // et peut provoquer le 419 en tentant de valider le CSRF sur les
        // requêtes qui n'ont pas de cookie de session valide.
        // On le retire et on exclut explicitement les routes API du CSRF.
        $middleware->validateCsrfTokens(except: [
            'api/*',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Forcer les réponses JSON sur toutes les erreurs en /api/*
        // Evite que Laravel retourne du HTML sur une 404/500/419
        $exceptions->shouldRenderJsonWhen(function (Request $request, Throwable $e) {
            return $request->is('api/*') || $request->expectsJson();
        });
    })->create();