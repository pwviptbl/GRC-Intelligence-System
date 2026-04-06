@extends('layouts.grc')

@section('title', 'Softwares')
@section('description', 'Catálogo de Softwares')
@section('badge', $softwares->count() . ' Total')

@section('content')
<div class="table-view" x-data="{ showModal: false }">
    <div class="stats-row">
        <div class="stat-card c2">
            <div class="stat-label">Total de Softwares</div>
            <div class="stat-value">{{ $softwares->count() }}</div>
        </div>
    </div>
    
    <div class="table-header">
        <h3>Softwares Cadastrados</h3>
        <button class="btn-add" @click="showModal = true">+ Novo Software</button>
    </div>

    <div class="table-card">
        <table class="data-table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Nome</th>
                    <th>Tecnologia</th>
                    <th>Repositório</th>
                    <th>Ações</th>
                </tr>
            </thead>
            <tbody>
                @forelse($softwares as $s)
                <tr>
                    <td style="color:var(--text-3);font-family:var(--mono);font-size:11px">{{ $s->id }}</td>
                    <td style="font-weight:500;color:var(--text-1)">{{ $s->nome }}</td>
                    <td>
                        @if($s->tecnologia)
                            <span class="tech-badge">{{ $s->tecnologia }}</span>
                        @else
                            <span style="color:var(--text-3)">—</span>
                        @endif
                    </td>
                    <td>
                        @if($s->git_url)
                            <a href="{{ $s->git_url }}" target="_blank" style="color:var(--cyan-dim);font-size:12px">{{ $s->git_url }}</a>
                        @else
                            <span style="color:var(--text-3)">—</span>
                        @endif
                    </td>
                    <td>
                        <form action="{{ route('softwares.destroy', $s) }}" method="POST" onsubmit="return confirm('Deseja remover este software?')">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn-del">🗑</button>
                        </form>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="5">
                        <div class="empty-state">
                            <div class="empty-icon">💾</div>
                            <p>Nenhum software cadastrado ainda.</p>
                        </div>
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <!-- Modal Novo Software -->
    <div class="modal-overlay" x-show="showModal" style="display: none;" x-transition>
        <div class="modal" @click.away="showModal = false">
            <h3>💾 Novo Software</h3>
            <form action="{{ route('softwares.store') }}" method="POST">
                @csrf
                <div class="form-group">
                    <label>Nome do Software</label>
                    <input type="text" name="nome" class="form-input" placeholder="Ex: GRC System" required />
                </div>
                <div class="form-group">
                    <label>Tecnologia</label>
                    <input type="text" name="tecnologia" class="form-input" placeholder="Ex: PHP / Laravel" />
                </div>
                <div class="form-group">
                    <label>URL Git</label>
                    <input type="url" name="git_url" class="form-input" placeholder="https://github.com/..." />
                </div>
                <div class="modal-actions">
                    <button type="button" class="btn-cancel" @click="showModal = false">Cancelar</button>
                    <button type="submit" class="btn-save">Salvar Software</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
