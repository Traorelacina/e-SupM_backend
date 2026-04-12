<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class CheckRole
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, ...$roles): Response
    {
        // Vérifier si l'utilisateur est authentifié
        if (!Auth::check()) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Non authentifié. Veuillez vous connecter.',
                    'code' => 'UNAUTHENTICATED'
                ], 401);
            }
            
            return redirect()->route('login');
        }

        $user = Auth::user();

        // Vérifier si le compte est banni
        if ($user->status === 'banned' || $user->isBanned()) {
            Auth::logout();
            
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Votre compte a été suspendu. ' . ($user->ban_reason ?? 'Contactez l\'administrateur.'),
                    'code' => 'ACCOUNT_BANNED'
                ], 403);
            }
            
            return redirect()->route('login')->with('error', 'Compte suspendu');
        }

        // Vérifier si l'utilisateur a l'un des rôles autorisés
        $allowedRoles = func_get_args();
        array_shift($allowedRoles); // Enlever le premier argument ($next)
        
        if (!in_array($user->role, $allowedRoles)) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Accès non autorisé. Vous n\'avez pas les permissions nécessaires.',
                    'required_roles' => $allowedRoles,
                    'user_role' => $user->role,
                    'code' => 'FORBIDDEN'
                ], 403);
            }
            
            abort(403, 'Accès non autorisé.');
        }

        return $next($request);
    }
}