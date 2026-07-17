<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RoleMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     * @param  string  ...$roles
     */
    public function handle(Request $request, Closure $next, ...$roles): Response
    {
        if (!auth()->check()) {
            return redirect()->route('login');
        }

        $user = auth()->user();

        if (! $user->active) {
            auth()->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->withErrors([
                'email' => 'Esta conta está desativada.',
            ]);
        }

        // 1. Admin tem acesso total sempre
        if ($user->role === 'admin') {
            return $next($request);
        }

        // 2. Verifica se o papel do usuário está na lista de papéis permitidos para esta rota
        if (!in_array($user->role, $roles)) {
            abort(403, 'Acesso Negado: Seu perfil (' . $user->role . ') não tem permissão para acessar esta área.');
        }

        // 3. Restrição específica para Auditor: se for auditor, SÓ pode passar se o método for GET (leitura)
        if ($user->role === 'auditor' && !$request->isMethod('get')) {
            abort(403, 'Acesso Negado: O perfil de Auditor possui permissão apenas de leitura (visualização).');
        }

        return $next($request);
    }
}
