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
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:'.User::class],
            'password' => ['required', Rules\Password::min(8)->letters()->numbers()->symbols()],
            'role' => ['required', 'in:admin,governanca,operacional,auditor'],
            'nivel_operacional' => ['nullable', 'in:junior,pleno,especialista'],
            'capacidade_semanal_pontos' => ['required', 'integer', 'min:1', 'max:40'],
            'areas_atuacao' => ['nullable', 'string', 'max:2000'],
        ]);

        User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'role' => $request->role,
            'nivel_operacional' => $request->nivel_operacional,
            'capacidade_semanal_pontos' => $request->capacidade_semanal_pontos,
            'disponivel_para_tarefas' => $request->boolean('disponivel_para_tarefas'),
            'areas_atuacao' => $request->areas_atuacao,
            'active' => true,
        ]);

        return redirect()->back()->with('success', 'Usuário criado com sucesso!');
    }

    public function update(Request $request, User $usuario)
    {
        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'role' => ['required', 'in:admin,governanca,operacional,auditor'],
            'nivel_operacional' => ['nullable', 'in:junior,pleno,especialista'],
            'capacidade_semanal_pontos' => ['required', 'integer', 'min:1', 'max:40'],
            'areas_atuacao' => ['nullable', 'string', 'max:2000'],
        ]);

        $data = [
            'name' => $request->name,
            'role' => $request->role,
            'nivel_operacional' => $request->nivel_operacional,
            'capacidade_semanal_pontos' => $request->capacidade_semanal_pontos,
            'disponivel_para_tarefas' => $request->boolean('disponivel_para_tarefas'),
            'areas_atuacao' => $request->areas_atuacao,
            'active' => $request->has('active'),
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
