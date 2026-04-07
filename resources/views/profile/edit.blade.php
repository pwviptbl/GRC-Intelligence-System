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
                {{-- Mudamos de 'nome' para 'name' para bater com o Request padrão do Laravel/Breeze --}}
                <input type="text" name="name" class="form-input" value="{{ old('name', $user->name) }}" required />
                <x-input-error class="mt-2" :messages="$errors->get('name')" />
            </div>

            <div class="form-group" style="margin-top:15px">
                <label>E-mail (Login)</label>
                {{-- Campo desabilitado conforme solicitado --}}
                <input type="email" class="form-input opacity-50" value="{{ $user->email }}" disabled />
                <p style="font-size:10px; color:var(--text-3); margin-top:5px">O e-mail não pode ser alterado por questões de segurança.</p>
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
    <div class="card" style="padding:24px" x-data="{ newPassword: '' }">
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
                <input type="password" name="password" x-model="newPassword" class="form-input" required />
                @if($errors->updatePassword->has('password'))
                    <span style="color:var(--red); font-size:10px">{{ $errors->updatePassword->first('password') }}</span>
                @endif

                <!-- Medidor de Força -->
                <div class="password-strength" style="margin-top:8px" x-show="newPassword.length > 0">
                    <div style="display:flex; gap:4px; height:4px">
                        <div :style="newPassword.length >= 1 ? 'flex:1; background:'+(newPassword.length < 6 ? 'var(--red)' : (newPassword.length < 10 ? 'var(--yellow)' : 'var(--green)')) : 'flex:1; background:rgba(255,255,255,0.1)'"></div>
                        <div :style="newPassword.length >= 8 && /[0-9]/.test(newPassword) ? 'flex:1; background:'+(/[!@#$%^&*]/.test(newPassword) ? 'var(--green)' : 'var(--yellow)') : 'flex:1; background:rgba(255,255,255,0.1)'"></div>
                        <div :style="newPassword.length >= 10 && /[!@#$%^&*]/.test(newPassword) ? 'flex:1; background:var(--green)' : 'flex:1; background:rgba(255,255,255,0.1)'"></div>
                    </div>
                    <p style="font-size:10px; margin-top:5px; color:var(--text-3)">
                        <span x-show="newPassword.length < 8">Mínimo 8 caracteres. </span>
                        <span x-show="!/[0-9]/.test(newPassword)">Adicione números. </span>
                        <span x-show="!/[!@#$%^&*]/.test(newPassword)">Adicione símbolos.</span>
                    </p>
                </div>
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
