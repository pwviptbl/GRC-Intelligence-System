@extends('layouts.grc')

@section('title', 'Gestão de Usuários')
@section('description', 'Controle de Acesso e Perfis do Sistema')
@section('badge', $users->count() . ' Usuários')

@section('content')
<style>
    .users-header { display:flex; align-items:center; justify-content:space-between; gap:16px; margin-bottom:20px; }
    .users-header h3 { margin:0; color:var(--text-1); font-size:16px; }
    .users-identity { min-width:0; overflow-wrap:anywhere; }
    .users-identity strong { display:block; color:var(--text-1); font-weight:600; }
    .users-identity span { color:var(--text-3); font-size:11px; }
    .users-actions { display:flex; align-items:center; gap:8px; }
    .users-edit { display:inline-flex; align-items:center; justify-content:center; width:30px; height:30px; padding:0; border:0; border-radius:6px; background:transparent; font-size:14px; cursor:pointer; }
    .users-edit:hover { background:var(--bg-hover); }
    .users-modal { width:min(500px, calc(100vw - 32px)) !important; max-width:none; max-height:calc(100vh - 32px); overflow-y:auto; }

    @media (max-width:680px) {
        .users-header { align-items:stretch; flex-direction:column; }
        .users-header .btn-add { justify-content:center; width:100%; }
        .users-table thead { display:none; }
        .users-table, .users-table tbody, .users-table tr, .users-table td { display:block; width:100%; }
        .users-table tbody { padding:0 16px 16px; }
        .users-table tr { padding:12px 0; border-bottom:1px solid var(--border); }
        .users-table tr:last-child { border-bottom:0; }
        .users-table td { display:grid; grid-template-columns:78px minmax(0,1fr); align-items:center; gap:10px; padding:5px 0; border:0; overflow-wrap:anywhere; }
        .users-table td::before { content:attr(data-label); color:var(--text-3); font-size:10px; font-weight:700; text-transform:uppercase; }
        .users-modal { width:calc(100vw - 20px) !important; max-height:calc(100vh - 20px); padding:18px; }
        .users-modal .modal-actions { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); }
        .users-modal .modal-actions button { justify-content:center; width:100%; }
    }
</style>

<div class="table-view" x-data="{ 
    showModal: false, 
    editMode: false,
    formAction: '{{ route('usuarios.store') }}',
    form: { id: '', name: '', email: '', role: 'operacional', active: true, password: '' },

    openCreate() {
        this.editMode = false;
        this.form = { id: '', name: '', email: '', role: 'operacional', active: true, password: '' };
        this.formAction = '{{ route('usuarios.store') }}';
        this.showModal = true;
    },

    openEdit(u) {
        this.editMode = true;
        this.form = { ...u, password: '' };
        this.formAction = `/usuarios/${u.id}`;
        this.showModal = true;
    }
}">
    <div class="users-header">
        <h3>👥 Colaboradores do GRC</h3>
        <button class="btn-add" @click="openCreate()">+ Novo Usuário</button>
    </div>

    <div class="table-card">
        <table class="data-table users-table">
            <thead>
                <tr>
                    <th>Nome / E-mail</th>
                    <th>Perfil</th>
                    <th>Status</th>
                    <th width="120">Ações</th>
                </tr>
            </thead>
            <tbody>
                @foreach($users as $user)
                <tr style="{{ !$user->active ? 'opacity: 0.5' : '' }}">
                    <td data-label="Usuário">
                        <div class="users-identity"><strong>{{ $user->name }}</strong><span>{{ $user->email }}</span></div>
                    </td>
                    <td data-label="Perfil">
                        <span class="badge" style="background:rgba(255,255,255,0.05); color:var(--text-2)">
                            {{ strtoupper($user->role) }}
                        </span>
                    </td>
                    <td data-label="Status">
                        @if($user->active)
                            <span style="color:var(--green); font-size:11px">● Ativo</span>
                        @else
                            <span style="color:var(--red); font-size:11px">● Inativo</span>
                        @endif
                    </td>
                    <td data-label="Ações">
                        <div class="users-actions">
                            <button @click="openEdit({{ $user->toJson() }})" class="users-edit" title="Editar">🖊️</button>
                            @if($user->active)
                            <form action="{{ route('usuarios.destroy', $user) }}" method="POST" style="margin:0" onsubmit="return confirm('Desativar este usuário?')">
                                @csrf @method('DELETE')
                                <button type="submit" class="btn-del" title="Desativar">🚫</button>
                            </form>
                            @endif
                        </div>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <!-- Modal Novo/Editar -->
    <div class="modal-overlay" x-show="showModal" style="display: none;" x-transition>
        <div class="modal users-modal">
            <h3>👤 <span x-text="editMode ? 'Editar Usuário' : 'Novo Usuário'"></span></h3>
            <form :action="formAction" method="POST">
                @csrf
                <template x-if="editMode">
                    <input type="hidden" name="_method" value="PATCH">
                </template>

                <div class="form-group">
                    <label>Nome Completo</label>
                    <input type="text" name="name" x-model="form.name" class="form-input" required />
                </div>

                <div class="form-group" style="margin-top:10px">
                    <label>Perfil de Acesso</label>
                    <select name="role" x-model="form.role" class="form-select">
                        <option value="admin">Administrador</option>
                        <option value="governanca">Governança</option>
                        <option value="operacional">Operacional</option>
                        <option value="auditor">Auditor (Apenas Leitura)</option>
                    </select>
                </div>

                <div class="form-group" style="margin-top:10px">
                    <label>E-mail</label>
                    <input type="email" name="email" x-model="form.email" class="form-input" :disabled="editMode" required />
                </div>

                <div class="form-group" style="margin-top:10px">
                    <label x-text="editMode ? 'Nova Senha (deixe em branco para manter)' : 'Senha'"></label>
                    <input type="password" name="password" x-model="form.password" class="form-input" :required="!editMode" />
                    
                    <!-- Medidor de Força -->
                    <div class="password-strength" style="margin-top:8px" x-show="form.password.length > 0">
                        <div style="display:flex; gap:4px; height:4px">
                            <div :style="form.password.length >= 1 ? 'flex:1; background:'+(form.password.length < 6 ? 'var(--red)' : (form.password.length < 10 ? 'var(--yellow)' : 'var(--green)')) : 'flex:1; background:rgba(255,255,255,0.1)'"></div>
                            <div :style="form.password.length >= 8 && /[0-9]/.test(form.password) ? 'flex:1; background:'+(/[!@#$%^&*]/.test(form.password) ? 'var(--green)' : 'var(--yellow)') : 'flex:1; background:rgba(255,255,255,0.1)'"></div>
                            <div :style="form.password.length >= 10 && /[!@#$%^&*]/.test(form.password) ? 'flex:1; background:var(--green)' : 'flex:1; background:rgba(255,255,255,0.1)'"></div>
                        </div>
                        <p style="font-size:10px; margin-top:5px; color:var(--text-3)">
                            <span x-show="form.password.length < 8">Mínimo 8 caracteres. </span>
                            <span x-show="!/[0-9]/.test(form.password)">Adicione números. </span>
                            <span x-show="!/[!@#$%^&*]/.test(form.password)">Adicione símbolos.</span>
                        </p>
                    </div>
                </div>

                <div class="form-group" style="margin-top:15px; display:flex; align-items:center; gap:10px" x-show="editMode">
                    <input type="checkbox" name="active" x-model="form.active" id="user_active" value="1">
                    <label for="user_active" style="margin:0">Usuário Ativo</label>
                </div>

                <div class="modal-actions" style="margin-top:20px">
                    <button type="button" class="btn-cancel" @click="showModal = false">Cancelar</button>
                    <button type="submit" class="btn-save" x-text="editMode ? 'Atualizar' : 'Criar Conta'"></button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
