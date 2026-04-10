@extends('layouts.grc')

@section('title', 'Backup e Restauração')
@section('description', 'Gestão de backup SQL + uploads e restauração automática')
@section('badge', $backups->count() . ' Backups')

@section('content')
<div class="table-view">
    @if (session('success'))
        <div style="margin-bottom:14px; padding:10px 12px; border-radius:8px; border:1px solid rgba(0,255,159,.35); background:rgba(0,255,159,.08); color:#d7ffef; font-size:13px;">
            {{ session('success') }}
        </div>
    @endif

    @if (session('error'))
        <div style="margin-bottom:14px; padding:10px 12px; border-radius:8px; border:1px solid rgba(255,83,112,.35); background:rgba(255,83,112,.08); color:#ffd7de; font-size:13px;">
            {{ session('error') }}
        </div>
    @endif

    @if ($errors->any())
        <div style="margin-bottom:14px; padding:10px 12px; border-radius:8px; border:1px solid rgba(255,83,112,.35); background:rgba(255,83,112,.08); color:#ffd7de; font-size:13px;">
            {{ $errors->first() }}
        </div>
    @endif

    <div style="display:grid; grid-template-columns: 1fr 1fr; gap:20px; margin-bottom:20px;">
        <div class="table-card" style="padding:18px;">
            <h3 style="margin:0 0 10px 0; color:var(--text-1);">Gerar Novo Backup</h3>
            <p style="margin:0 0 12px 0; color:var(--text-2); font-size:12px; line-height:1.5;">
                Gera um arquivo .zip contendo dump SQL do banco e todos os uploads em storage/app/public.
            </p>
            <form action="{{ route('backups.create') }}" method="POST">
                @csrf
                <button type="submit" class="btn-save">💾 Gerar Backup Completo</button>
            </form>
        </div>

        <div class="table-card" style="padding:18px;">
            <h3 style="margin:0 0 10px 0; color:var(--text-1);">Restaurar Backup</h3>
            <p style="margin:0 0 12px 0; color:var(--text-2); font-size:12px; line-height:1.5;">
                Envie um backup no formato gerado pelo sistema. A restauração aplica SQL e repõe uploads nos locais corretos.
            </p>
            <form action="{{ route('backups.restore') }}" method="POST" enctype="multipart/form-data" style="display:flex; gap:8px; align-items:center;">
                @csrf
                <input type="file" name="backup_file" accept=".zip" class="form-input" required>
                <button type="submit" class="btn-add" onclick="return confirm('A restauração irá sobrescrever dados atuais do banco e uploads. Continuar?')">♻️ Restaurar</button>
            </form>
        </div>
    </div>

    <div class="table-card">
        <div class="table-header" style="display:flex; justify-content:space-between; align-items:center; margin-bottom:12px;">
            <h3 style="margin:0; color:var(--text-1); font-size:15px;">Arquivos de Backup</h3>
            <span style="font-size:11px; color:var(--text-3);">Diretório: storage/app/backups</span>
        </div>

        <table class="data-table">
            <thead>
                <tr>
                    <th>Arquivo</th>
                    <th>Tamanho</th>
                    <th>Gerado em</th>
                    <th width="120">Ação</th>
                </tr>
            </thead>
            <tbody>
                @forelse($backups as $backup)
                    <tr>
                        <td style="font-weight:600; color:var(--text-1);">{{ $backup['name'] }}</td>
                        <td>{{ $backup['size_human'] }}</td>
                        <td>{{ \Carbon\Carbon::createFromTimestamp($backup['last_modified'], config('app.timezone'))->format('d/m/Y H:i:s') }}</td>
                        <td>
                            <a href="{{ route('backups.download', ['file' => $backup['name']]) }}" class="btn-save" style="padding:6px 10px; font-size:11px; text-decoration:none; display:inline-block;">⬇️ Download</a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="empty-state">Nenhum backup gerado ainda.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
