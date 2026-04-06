@extends('layouts.grc')

@section('title', 'Incidentes')
@section('description', 'Monitoramento e Resposta a Incidentes')
@section('badge', $incidentes->count() . ' Ocorrências')

@section('content')
<div class="table-view" x-data="{ showModal: false }">
    <div class="table-header">
        <h3>🚨 Registro de Incidentes</h3>
        <button class="btn-add" @click="showModal = true">+ Registrar Incidente</button>
    </div>

    <div class="table-card">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Severidade</th>
                    <th>Título</th>
                    <th>Status</th>
                    <th>Data Detecção</th>
                    <th>Ações</th>
                </tr>
            </thead>
            <tbody>
                @forelse($incidentes as $i)
                <tr>
                    <td>
                        <span class="badge" style="{{ $i->severidade === 'Alta' ? 'background:rgba(255,83,112,.12);color:var(--red);' : '' }}">
                            {{ $i->severidade }}
                        </span>
                    </td>
                    <td style="font-weight:500;color:var(--text-1)">{{ $i->titulo }}</td>
                    <td><span class="badge">{{ $i->status }}</span></td>
                    <td style="color:var(--text-3);font-size:12px">{{ $i->data_deteccao }}</td>
                    <td>
                        <form action="{{ route('incidentes.destroy', $i) }}" method="POST">
                            @csrf @method('DELETE')
                            <button type="submit" class="btn-del">🗑</button>
                        </form>
                    </td>
                </tr>
                @empty
                <tr><td colspan="5" class="empty-state">Nenhum incidente registrado ainda.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <!-- Modal Novo Incidente -->
    <div class="modal-overlay" x-show="showModal" style="display: none;" x-transition>
        <div class="modal">
            <h3>🚨 Novo Incidente</h3>
            <form action="{{ route('incidentes.store') }}" method="POST">
                @csrf
                <div class="form-group">
                    <label>Título do Incidente</label>
                    <input type="text" name="titulo" class="form-input" placeholder="Ex: Vazamento de credenciais" required />
                </div>
                <div class="form-group">
                    <label>Severidade</label>
                    <select name="severidade" class="form-select">
                        <option>Baixa</option><option>Media</option><option>Alta</option><option>Critica</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Data de Detecção</label>
                    <input type="date" name="data_deteccao" class="form-input" value="{{ date('Y-m-d') }}" required />
                </div>
                <div class="modal-actions">
                    <button type="button" class="btn-cancel" @click="showModal = false">Cancelar</button>
                    <button type="submit" class="btn-save">Registrar</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
