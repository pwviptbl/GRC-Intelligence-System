@extends('layouts.grc')

@section('title', 'Meu Perfil')
@section('description', 'Gerenciar informações da conta e segurança')

@section('content')
<style>
    .profile-view { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); align-items:start; gap:24px; height:100%; padding:24px 28px; overflow-y:auto; }
    .profile-card { min-width:0; padding:24px; border:1px solid var(--border); border-radius:8px; background:var(--bg-surface); }
    .profile-card h3 { margin:0 0 20px; padding-bottom:10px; border-bottom:1px solid rgba(255,255,255,.05); color:var(--text-1); font-size:16px; }
    .profile-actions { display:flex; align-items:center; flex-wrap:wrap; gap:12px; margin-top:20px; }
    .profile-success { color:var(--green); font-size:12px; overflow-wrap:anywhere; }

    @media (max-width:850px) {
        .profile-view { grid-template-columns:minmax(0,1fr); }
    }

    @media (max-width:560px) {
        .profile-view { gap:16px; padding:16px; }
        .profile-card { padding:16px; }
        .profile-actions { align-items:stretch; flex-direction:column; }
        .profile-actions .btn-save { justify-content:center; width:100%; }
    }
</style>

<div class="grid-view profile-view">
    
    <!-- Seção Informações do Perfil -->
    <div class="card profile-card">
        <h3>
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

            <div class="modal-actions profile-actions">
                <button type="submit" class="btn-save">Salvar Alterações</button>
                @if (session('status') === 'profile-updated')
                    <span class="profile-success">✅ Salvo com sucesso!</span>
                @endif
            </div>
        </form>
    </div>

    <!-- Seção Segurança / Troca de Senha -->
    <div class="card profile-card" x-data="{ newPassword: '' }">
        <h3>
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

            <div class="modal-actions profile-actions">
                <button type="submit" class="btn-save" style="background:var(--cyan); color:var(--bg-1)">Atualizar Senha</button>
                @if (session('status') === 'password-updated')
                    <span class="profile-success">✅ Senha atualizada!</span>
                @endif
            </div>
        </form>
    </div>

    <!-- Seção Aparência / Tema -->
    <div class="card profile-card" style="grid-column: span 2;" x-data="{
        theme: '{{ $user->theme_preference ?? 'dark' }}',
        saving: false,
        saved: false,
        async setTheme(val) {
            this.theme = val;
            this.saving = true;
            this.saved = false;
            document.documentElement.setAttribute('data-theme', val);
            localStorage.setItem('grc_theme', val);
            try {
                await fetch('{{ route('profile.theme') }}', {
                    method: 'PATCH',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({ theme: val })
                });
                this.saved = true;
                setTimeout(() => this.saved = false, 3000);
            } catch(e) {} finally {
                this.saving = false;
            }
        }
    }">
        <h3>
            🎨 Preferência de Tema (Aparência)
        </h3>
        <p style="font-size:12px; color:var(--text-3); margin-top:-10px; margin-bottom:16px">
            Escolha como prefere visualizar a interface do GRC. A preferência é salva na sua conta e lembrada neste navegador.
        </p>

        <div style="display:grid; grid-template-columns:repeat(2, minmax(0, 1fr)); gap:16px;">
            <div x-on:click="setTheme('dark')" 
                 style="cursor:pointer; padding:16px; border-radius:10px; border:2px solid; transition:all .2s;"
                 :style="theme === 'dark' ? 'border-color:var(--cyan); background:rgba(0, 229, 255, 0.06)' : 'border-color:var(--border); background:var(--bg-base)'">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:8px">
                    <strong style="color:var(--text-1); font-size:14px">🌙 Modo Escuro (Padrão)</strong>
                    <span x-show="theme === 'dark'" style="color:var(--cyan); font-weight:700">✓ Ativo</span>
                </div>
                <p style="font-size:11px; color:var(--text-3); margin:0; line-height:1.4">
                    Tema escuro clássico em tons de azul marinho profundo (Cyberpunk / Modern SOC), ideal para ambientes de baixa luminosidade.
                </p>
            </div>

            <div x-on:click="setTheme('light')" 
                 style="cursor:pointer; padding:16px; border-radius:10px; border:2px solid; transition:all .2s;"
                 :style="theme === 'light' ? 'border-color:var(--cyan); background:rgba(0, 229, 255, 0.06)' : 'border-color:var(--border); background:var(--bg-base)'">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:8px">
                    <strong style="color:var(--text-1); font-size:14px">☀️ Modo Claro</strong>
                    <span x-show="theme === 'light'" style="color:var(--cyan); font-weight:700">✓ Ativo</span>
                </div>
                <p style="font-size:11px; color:var(--text-3); margin:0; line-height:1.4">
                    Tema claro corporativo com alto contraste, excelente legibilidade para relatórios, planilhas e uso durante o dia.
                </p>
            </div>
        </div>

        <div style="margin-top:14px; font-size:12px; color:var(--green); display:none" x-show="saved">
            ✅ Preferência de tema atualizada na sua conta com sucesso!
        </div>
    </div>

    {{-- Card de API Token (DefectDojo REST API) --}}
    <div class="data-card" style="margin-bottom: 24px;">
        <h3 style="color:var(--text-1); font-size:16px; margin-bottom:8px">🔑 Chave de Acesso API (REST API / CI-CD)</h3>
        <p style="color:var(--text-3); font-size:12px; margin-bottom:16px">
            Utilize este token pessoal para integrar pipelines CI/CD, scanners automáticos ou consumir a API REST de Engajamentos e Achados do GRC.
        </p>

        @if(session('status') === 'api-token-generated')
            <div style="background:rgba(34,197,94,0.15);border:1px solid rgba(34,197,94,0.4);color:#22c55e;padding:10px 14px;border-radius:6px;margin-bottom:14px;font-size:12px">
                ✅ Novo token de API gerado com sucesso!
            </div>
        @endif

        @if(auth()->user()->api_token)
            <div style="margin-bottom:16px">
                <label style="font-size:11px;color:var(--text-3);display:block;margin-bottom:4px">Seu Token de API:</label>
                <div style="display:flex;gap:8px;align-items:center">
                    <input type="text" readonly value="{{ auth()->user()->api_token }}" class="form-input" style="width:100%;font-family:monospace;font-size:12px;padding:8px" id="api-token-input">
                    <button type="button" onclick="navigator.clipboard.writeText(document.getElementById('api-token-input').value); alert('Token copiado para a área de transferência!');"
                            style="padding:8px 14px;background:rgba(0,229,255,0.15);color:var(--cyan);border:1px solid rgba(0,229,255,0.3);border-radius:6px;cursor:pointer;font-size:12px;white-space:nowrap">
                        📋 Copiar
                    </button>
                </div>
                <div style="margin-top:6px;font-size:11px;color:var(--text-3)">
                    Exemplo de uso: <code>curl -H "X-API-Key: {{ substr(auth()->user()->api_token, 0, 10) }}..." {{ url('/api/v1/engagements') }}</code>
                </div>
            </div>
        @else
            <div style="margin-bottom:16px;font-size:12px;color:var(--text-3)">
                Você ainda não gerou um token de API para esta conta.
            </div>
        @endif

        <form method="POST" action="{{ route('profile.api_token') }}">
            @csrf
            <button type="submit" class="btn-primary" style="padding:9px 18px;background:var(--cyan);color:#0d1628;font-weight:600;border:none;border-radius:6px;cursor:pointer;font-size:13px">
                {{ auth()->user()->api_token ? '🔄 Renovar / Gerar Novo Token' : '⚡ Gerar Meu Token de API' }}
            </button>
        </form>
    </div>

</div>
@endsection
