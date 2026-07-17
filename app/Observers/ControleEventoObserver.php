<?php

namespace App\Observers;

use App\Models\ControleEvento;
use App\Models\ControleEventoHistorico;

class ControleEventoObserver
{
    private const IGNORED_FIELDS = ['updated_at', 'created_at'];

    public function created(ControleEvento $event): void
    {
        $this->record($event, 'criado', collect($event->getAttributes())
            ->except(self::IGNORED_FIELDS)
            ->map(fn ($value) => ['de' => null, 'para' => $value])
            ->all());
    }

    public function updated(ControleEvento $event): void
    {
        $changes = collect($event->getChanges())
            ->except(self::IGNORED_FIELDS)
            ->map(fn ($value, $field) => ['de' => $event->getRawOriginal($field), 'para' => $value])
            ->all();

        if ($changes !== []) {
            $this->record($event, 'atualizado', $changes);
        }
    }

    public function deleted(ControleEvento $event): void
    {
        $this->record($event, 'excluido', null);
    }

    private function record(ControleEvento $event, string $action, ?array $changes): void
    {
        ControleEventoHistorico::create([
            'controle_evento_id' => $event->id,
            'user_id' => auth()->id(),
            'evento_titulo' => $event->acao_controle_snapshot ?: 'Card sem título',
            'acao' => $action,
            'origem' => request()->is('mcp')
                ? 'mcp'
                : (request()->route() ? 'web' : 'sistema'),
            'alteracoes' => $changes,
        ]);
    }
}
