@extends('layouts.grc')

@section('title', 'Gestão de Usuários')
@section('description', 'Controle de Acesso e Perfis do Sistema')
@section('badge', $users->count() . ' Usuários')

@section('content')
<div class="table-view" x-data="{ 
    showModal: false, 
    editMode: false,
    formAction: '{{ route('usuarios.store') }}',
    form: { id: '', nome: '', email: '', role: 'operacional', active: true, password: '' },

    openCreate() {
        this.editMode = false;
        this.form = { id: '', nome: '', email: '', role: 'operacional', active: true, password: '' };
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
    <div class="table-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
        <h3 style="color:var(--text-1); font-size:16px">👥 Colaboradores do GRC</h3>
        <button class="btn-add" @click="openCreate()">+ Novo Usuário</button>
    </div>

    <div class="table-card">
        <table class="data-table">
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
                    <td>
                        <div style="font-weight:600;color:var(--text-1)">{{ $user->nome }}</div>
                        <div style="font-size:11px;color:var(--text-3)">{{ $user->email }}</div>
                    </td>
                    <td>
                        <span class="badge" style="background:rgba(255,255,255,0.05); color:var(--text-2)">
                            {{ strtoupper($user->role) }}
                        </span>
                    </td>
                    <td>
                        @if($user->active)
                            <span style="color:var(--green); font-size:11px">● Ativo</span>
                        @else
                            <span style="color:var(--red); font-size:11px">● Inativo</span>
                        @endif
                    </td>
                    <td>
                        <div style="display:flex;gap:12px;align-items:center">
                            <button @click="openEdit({{ $user->toJson() }})" style="background:none;border:none;cursor:pointer;font-size:14px" title="Editar">🖊️</button>
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
        <div class="modal" style="width: 500px;">
            <h3>👤 <span x-text="editMode ? 'Editar Usuário' : 'Novo Usuário'"></span></h3>
            <form :action="formAction" method="POST">
                @csrf
                <template x-if="editMode">
                    <input type="hidden" name="_method" value="PATCH">
                </template>

                <div class="form-group">
                    <label>Nome Completo</label>
                    <input type="text" name="nome" x-model="form.nome" class="form-input" required />
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
