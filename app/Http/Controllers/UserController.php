<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;

class UserController extends Controller
{
    public function index()
    {
        // Apenas admin acessa essa tela (Desativado temporariamente)
        /* if (!auth()->user()->isAdmin()) {
            abort(403, 'Acesso negado.');
        } */

        $users = User::latest()->get();
        return view('usuarios.index', compact('users'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'nome' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:'.User::class],
            'password' => ['required', Rules\Password::min(8)->letters()->numbers()->symbols()],
            'role' => ['required', 'string'],
        ]);

        User::create([
            'nome' => $request->nome,
            'name' => $request->nome,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'role' => $request->role,
            'active' => true,
        ]);

        return redirect()->back()->with('success', 'Usuário criado com sucesso!');
    }

    public function update(Request $request, User $usuario)
    {
        $request->validate([
            'nome' => ['required', 'string', 'max:255'],
            'role' => ['required', 'string'],
        ]);

        $data = [
            'nome' => $request->nome,
            'name' => $request->nome,
            'role' => $request->role,
            'active' => $request->has('active')
        ];

        if ($request->filled('password')) {
            $data['password'] = Hash::make($request->password);
        }

        $usuario->update($data);

        return redirect()->back()->with('success', 'Usuário atualizado!');
    }

    public function destroy(User $usuario)
    {
        // Deletar aqui apenas desativa o usuário (Soft Delete Manual)
        $usuario->update(['active' => false]);
        return redirect()->back()->with('success', 'Usuário desativado.');
    }
}
