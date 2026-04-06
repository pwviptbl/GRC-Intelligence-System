@extends('layouts.grc')

@section('title', 'Meu Perfil')
@section('description', 'Gerenciar informações da conta e segurança')

@section('content')
<div class="grid-view" style="display:grid; grid-template-columns: 1fr 1fr; gap:30px">
    
    <!-- Seção Informações do Perfil -->
    <div class="card" style="padding:24px">
        <h3 style="color:var(--text-1); font-size:16px; margin-bottom:20px; border-bottom:1px solid rgba(255,255,255,.05); padding-bottom:10px">
            👤 Informações do Perfil
        </h3>
        
        <form method="post" action="{{ route('profile.update') }}">
            @csrf
            @method('patch')

            <div class="form-group">
                <label>Nome Completo</label>
                <input type="text" name="nome" class="form-input" value="{{ old('nome', $user->nome) }}" required />
            </div>

            <div class="form-group" style="margin-top:15px">
                <label>E-mail</label>
                <input type="email" name="email" class="form-input" value="{{ old('email', $user->email) }}" required />
            </div>

            <div class="modal-actions" style="margin-top:20px; justify-content:flex-start">
                <button type="submit" class="btn-save">Salvar Alterações</button>
                @if (session('status') === 'profile-updated')
                    <span style="font-size:12px; color:var(--green); margin-left:15px">✅ Salvo com sucesso!</span>
                @endif
            </div>
        </form>
    </div>

    <!-- Seção Segurança / Troca de Senha -->
    <div class="card" style="padding:24px">
        <h3 style="color:var(--text-1); font-size:16px; margin-bottom:20px; border-bottom:1px solid rgba(255,255,255,.05); padding-bottom:10px">
            🔐 Segurança (Alterar Senha)
        </h3>
        
        <form method="post" action="{{ route('password.update') }}">
            @csrf
            @method('patch')

            <div class="form-group">
                <label>Senha Atual</label>
                <input type="password" name="current_password" class="form-input" required />
                @if($errors->updatePassword->has('current_password'))
                    <span style="color:var(--red); font-size:10px">{{ $errors->updatePassword->first('current_password') }}</span>
                @endif
            </div>

            <div class="form-group" style="margin-top:15px">
                <label>Nova Senha</label>
                <input type="password" name="password" class="form-input" required />
                @if($errors->updatePassword->has('password'))
                    <span style="color:var(--red); font-size:10px">{{ $errors->updatePassword->first('password') }}</span>
                @endif
            </div>

            <div class="form-group" style="margin-top:15px">
                <label>Confirmar Nova Senha</label>
                <input type="password" name="password_confirmation" class="form-input" required />
            </div>

            <div class="modal-actions" style="margin-top:20px; justify-content:flex-start">
                <button type="submit" class="btn-save" style="background:var(--cyan); color:var(--bg-1)">Atualizar Senha</button>
                @if (session('status') === 'password-updated')
                    <span style="font-size:12px; color:var(--green); margin-left:15px">✅ Senha atualizada!</span>
                @endif
            </div>
        </form>
    </div>
</div>
@endsection
