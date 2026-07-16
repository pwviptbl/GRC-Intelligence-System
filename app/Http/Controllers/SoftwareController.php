<?php

namespace App\Http\Controllers;

use App\Models\Software;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;

class SoftwareController extends Controller
{
    public function index()
    {
        $softwares = Software::latest()->get();
        $ratingOptions = Software::RATING_LABELS;

        return view('softwares.index', compact('softwares', 'ratingOptions'));
    }

    public function store(Request $request)
    {
        Software::create($this->persistableData($this->validatedData($request)));

        return redirect()->back()->with($this->successMessage('Software cadastrado com sucesso!'));
    }

    public function update(Request $request, Software $software)
    {
        $software->update($this->persistableData($this->validatedData($request, $software)));

        return redirect()->back()->with($this->successMessage('Software atualizado com sucesso!'));
    }

    public function print()
    {
        $softwares = Software::orderBy('nome')->get();
        return view('softwares.print', compact('softwares'));
    }

    public function destroy(Software $software)
    {
        $software->delete();
        return redirect()->back()->with('success', 'Software removido com sucesso!');
    }

    protected function validatedData(Request $request, ?Software $software = null): array
    {
        return $request->validate([
            'nome' => [
                'required',
                'string',
                'max:255',
                Rule::unique('software', 'nome')->ignore($software?->id),
            ],
            'tecnologia' => 'nullable|string|max:255',
            'ativo' => 'required|boolean',
            'git_url' => 'nullable|url|max:255',
            'exposicao_nivel' => 'nullable|integer|in:1,2,3',
            'exposicao_detalhe' => 'nullable|string|max:255',
            'dados_sensibilidade_nivel' => 'nullable|integer|in:1,2,3',
            'dados_sensibilidade_detalhe' => 'nullable|string|max:255',
            'criticidade_operacional_nivel' => 'nullable|integer|in:1,2,3',
            'criticidade_operacional_detalhe' => 'nullable|string|max:255',
            'autenticacao_nivel' => 'nullable|integer|in:1,2,3',
            'autenticacao_detalhe' => 'nullable|string|max:255',
        ]);
    }

    protected function persistableData(array $data): array
    {
        if ($this->classificationFieldsAvailable()) {
            return $data;
        }

        return collect($data)->except([
            'exposicao_nivel',
            'exposicao_detalhe',
            'dados_sensibilidade_nivel',
            'dados_sensibilidade_detalhe',
            'criticidade_operacional_nivel',
            'criticidade_operacional_detalhe',
            'autenticacao_nivel',
            'autenticacao_detalhe',
        ])->all();
    }

    protected function classificationFieldsAvailable(): bool
    {
        return Schema::hasColumn('software', 'exposicao_nivel');
    }

    protected function successMessage(string $default): array
    {
        if ($this->classificationFieldsAvailable()) {
            return ['success' => $default];
        }

        return [
            'success' => $default,
            'warning' => 'Classificacao do ativo nao foi salva porque a migration das novas colunas ainda nao foi aplicada.',
        ];
    }
}
