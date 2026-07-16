@extends('layouts.grc')

@section('title', 'Backup e Restauração')
@section('description', 'Gestão de backup SQL + uploads e restauração automática')
@section('badge', $backups->count() . ' Backups')

@section('content')
<style>
    .backups-actions {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 20px;
        margin-bottom: 20px;
    }

    .backups-action-card {
        min-width: 0;
        padding: 18px;
    }

    .backups-action-card h3 {
        margin: 0 0 10px;
        color: var(--text-1);
        font-size: 17px;
    }

    .backups-action-card p {
        margin: 0 0 12px;
        color: var(--text-2);
        font-size: 12px;
        line-height: 1.5;
        overflow-wrap: anywhere;
    }

    .backups-restore-form {
        display: grid;
        grid-template-columns: minmax(0, 1fr) auto;
        align-items: center;
        gap: 8px;
    }

    .backups-restore-form .form-input {
        min-width: 0;
        width: 100%;
    }

    .backups-list-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        padding: 16px 18px 4px;
        margin-bottom: 12px;
    }

    .backups-directory {
        min-width: 0;
        color: var(--text-3);
        font-size: 11px;
        overflow-wrap: anywhere;
        text-align: right;
    }

    .backups-filename {
        max-width: 420px;
        color: var(--text-1);
        font-weight: 600;
        overflow-wrap: anywhere;
    }

    .backups-download {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        padding: 6px 10px;
        font-size: 11px;
        text-decoration: none;
        white-space: nowrap;
    }

    .backups-file-actions {
        display: flex;
        align-items: center;
        flex-wrap: wrap;
        gap: 8px;
    }

    .backups-delete-form {
        margin: 0;
    }

    .backups-delete {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        padding: 6px 10px;
        border: 1px solid rgba(255, 83, 112, .3);
        border-radius: 8px;
        background: rgba(255, 83, 112, .08);
        color: var(--red);
        font-family: var(--font);
        font-size: 11px;
        cursor: pointer;
        white-space: nowrap;
    }

    .backups-delete:hover {
        background: rgba(255, 83, 112, .15);
    }

    @media (max-width: 900px) {
        .backups-actions {
            grid-template-columns: minmax(0, 1fr);
        }
    }

    @media (max-width: 640px) {
        .backups-action-card {
            padding: 16px;
        }

        .backups-action-card .btn-save,
        .backups-restore-form .btn-add {
            justify-content: center;
            width: 100%;
        }

        .backups-restore-form {
            grid-template-columns: minmax(0, 1fr);
        }

        .backups-list-header {
            align-items: flex-start;
            flex-direction: column;
            padding: 16px 16px 6px;
        }

        .backups-directory {
            text-align: left;
        }

        .backups-table thead {
            display: none;
        }

        .backups-table,
        .backups-table tbody,
        .backups-table tr,
        .backups-table td {
            display: block;
            width: 100%;
        }

        .backups-table tbody {
            padding: 0 16px 16px;
        }

        .backups-table tr {
            padding: 12px 0;
            border-top: 1px solid var(--border);
        }

        .backups-table td {
            display: grid;
            grid-template-columns: 76px minmax(0, 1fr);
            align-items: start;
            gap: 10px;
            padding: 5px 0;
            border: 0;
            overflow-wrap: anywhere;
        }

        .backups-table td::before {
            content: attr(data-label);
            color: var(--text-3);
            font-size: 10px;
            font-weight: 700;
            text-transform: uppercase;
        }

        .backups-table .backups-filename {
            max-width: none;
        }

        .backups-table .backups-action-cell {
            align-items: center;
        }

        .backups-table .empty-state {
            display: block;
            padding: 28px 12px;
            text-align: center;
        }

        .backups-table .empty-state::before {
            content: none;
        }
    }
</style>

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

    <div class="backups-actions">
        <div class="table-card backups-action-card">
            <h3>Gerar Novo Backup</h3>
            <p>
                Gera um arquivo .zip contendo dump SQL do banco e todos os uploads em storage/app/public.
            </p>
            <form action="{{ route('backups.create') }}" method="POST">
                @csrf
                <button type="submit" class="btn-save">💾 Gerar Backup Completo</button>
            </form>
        </div>

        <div class="table-card backups-action-card">
            <h3>Restaurar Backup</h3>
            <p>
                Envie um backup no formato gerado pelo sistema. A restauração aplica SQL e repõe uploads nos locais corretos.
            </p>
            <form action="{{ route('backups.restore') }}" method="POST" enctype="multipart/form-data" class="backups-restore-form">
                @csrf
                <input type="file" name="backup_file" accept=".zip" class="form-input" required>
                <button type="submit" class="btn-add" onclick="return confirm('A restauração irá sobrescrever dados atuais do banco e uploads. Continuar?')">♻️ Restaurar</button>
            </form>
        </div>
    </div>

    <div class="table-card">
        <div class="backups-list-header">
            <h3 style="margin:0; color:var(--text-1); font-size:15px;">Arquivos de Backup</h3>
            <span class="backups-directory">Diretório: storage/app/backups</span>
        </div>

        <table class="data-table backups-table">
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
                        <td data-label="Arquivo" class="backups-filename">{{ $backup['name'] }}</td>
                        <td data-label="Tamanho">{{ $backup['size_human'] }}</td>
                        <td data-label="Gerado em">{{ \Carbon\Carbon::createFromTimestamp($backup['last_modified'], config('app.timezone'))->format('d/m/Y H:i:s') }}</td>
                        <td data-label="Ação" class="backups-action-cell">
                            <div class="backups-file-actions">
                                <a href="{{ route('backups.download', ['file' => $backup['name']]) }}" class="btn-save backups-download">⬇️ Download</a>
                                <form action="{{ route('backups.destroy', ['file' => $backup['name']]) }}" method="POST" class="backups-delete-form" onsubmit="return confirm('Excluir permanentemente este backup?')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="backups-delete">🗑 Excluir</button>
                                </form>
                            </div>
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
