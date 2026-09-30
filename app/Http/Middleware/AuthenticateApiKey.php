<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthenticateApiKey
{
    public function handle(Request $request, Closure $next)
    {
        // Se já autenticado pela sessão web
        if (Auth::check()) {
            return $next($request);
        }

        // Busca token no header X-API-Key, Bearer ou query string
        $token = $request->header('X-API-Key')
            ?: $request->bearerToken()
            ?: $request->query('api_token');

        if (! $token) {
            return response()->json([
                'error'   => 'Unauthorized',
                'message' => 'Token de API não fornecido. Use o header "X-API-Key" ou "Authorization: Bearer <token>".',
            ], 401);
        }

        $user = User::where('api_token', $token)->first();

        if (! $user) {
            return response()->json([
                'error'   => 'Unauthorized',
                'message' => 'Token de API inválido.',
            ], 401);
        }

        Auth::setUser($user);

        return $next($request);
    }
}
