<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RoleMiddleware
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (!$user) {
            return response()->json(['message' => 'Non authentifié'], 401);
        }

        if (!in_array($user->role, $roles)) {
            return response()->json(['message' => 'Accès refusé. Droits insuffisants.'], 403);
        }

        if ($user->isBanned()) {
            return response()->json(['message' => 'Compte suspendu'], 403);
        }

        return $next($request);
    }
}
