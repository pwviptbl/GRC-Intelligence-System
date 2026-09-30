<?php

namespace App\Http\Controllers;

use App\Models\Engagement;
use App\Models\EngagementTest;
use App\Models\Finding;
use App\Models\Software;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class SecurityReportController extends Controller
{
    /**
     * Relatório Consolidado de Segurança por Software (Asset).
     */
    public function softwareReport(Request $request, Software $software)
    {
        $software->load(['engagements.tests.findings', 'modulos']);

        $allFindings = collect();
        foreach ($software->engagements as $eng) {
            foreach ($eng->tests as $test) {
                foreach ($test->findings as $f) {
                    $allFindings->push($f);
                }
            }
        }

        $stats = [
            'total'       => $allFindings->count(),
            'abertos'     => $allFindings->whereIn('status', ['aberto', 'confirmado', 'em_tratamento'])->count(),
            'criticos'    => $allFindings->where('severidade', 'critico')->count(),
            'altos'       => $allFindings->where('severidade', 'alto')->count(),
            'medios'      => $allFindings->where('severidade', 'medio')->count(),
            'baixos'      => $allFindings->where('severidade', 'baixo')->count(),
            'info'        => $allFindings->where('severidade', 'informativo')->count(),
            'fechados'    => $allFindings->where('status', 'fechado')->count(),
            'regressoes'  => $allFindings->where('is_regression', true)->count(),
        ];

        $html = view('reports.security.software', compact('software', 'allFindings', 'stats'))->render();
        $safeName = Str::slug($software->nome) . '_relatorio_seguranca_' . date('Ymd');

        if ($request->boolean('preview') || $request->input('format') === 'html') {
            return response($html);
        }

        return Pdf::loadHTML($html)->setPaper('a4', 'portrait')->download("{$safeName}.pdf");
    }

    /**
     * Relatório Executivo e Técnico por Engajamento.
     */
    public function engagementReport(Request $request, Engagement $engagement)
    {
        $engagement->load(['software', 'tests.findings']);

        $findings = collect();
        foreach ($engagement->tests as $test) {
            foreach ($test->findings as $f) {
                $findings->push($f);
            }
        }

        $stats = [
            'total'      => $findings->count(),
            'abertos'    => $findings->whereIn('status', ['aberto', 'confirmado', 'em_tratamento'])->count(),
            'criticos'   => $findings->where('severidade', 'critico')->count(),
            'altos'      => $findings->where('severidade', 'alto')->count(),
            'medios'     => $findings->where('severidade', 'medio')->count(),
            'baixos'     => $findings->where('severidade', 'baixo')->count(),
            'info'       => $findings->where('severidade', 'informativo')->count(),
            'fechados'   => $findings->where('status', 'fechado')->count(),
            'regressoes' => $findings->where('is_regression', true)->count(),
        ];

        $html = view('reports.security.engagement', compact('engagement', 'findings', 'stats'))->render();
        $safeName = Str::slug($engagement->nome) . '_relatorio_' . date('Ymd');

        if ($request->boolean('preview') || $request->input('format') === 'html') {
            return response($html);
        }

        return Pdf::loadHTML($html)->setPaper('a4', 'portrait')->download("{$safeName}.pdf");
    }

    /**
     * Relatório Técnico por Teste de Engajamento.
     */
    public function testReport(Request $request, EngagementTest $engagementTest)
    {
        $engagementTest->load(['engagement.software', 'findings', 'retestOf']);

        $findings = $engagementTest->findings;

        $stats = [
            'total'      => $findings->count(),
            'abertos'    => $findings->whereIn('status', ['aberto', 'confirmado', 'em_tratamento'])->count(),
            'criticos'   => $findings->where('severidade', 'critico')->count(),
            'altos'      => $findings->where('severidade', 'alto')->count(),
            'medios'     => $findings->where('severidade', 'medio')->count(),
            'baixos'     => $findings->where('severidade', 'baixo')->count(),
            'info'       => $findings->where('severidade', 'informativo')->count(),
            'fechados'   => $findings->where('status', 'fechado')->count(),
            'regressoes' => $findings->where('is_regression', true)->count(),
        ];

        $html = view('reports.security.test', compact('engagementTest', 'findings', 'stats'))->render();
        $safeName = Str::slug($engagementTest->titulo) . '_teste_' . date('Ymd');

        if ($request->boolean('preview') || $request->input('format') === 'html') {
            return response($html);
        }

        return Pdf::loadHTML($html)->setPaper('a4', 'portrait')->download("{$safeName}.pdf");
    }

    /**
     * Ficha Técnica Individual por Achado (Finding).
     */
    public function findingReport(Request $request, Finding $finding)
    {
        $finding->load(['test.engagement.software', 'duplicadoDe', 'regressedFrom']);

        $html = view('reports.security.finding', compact('finding'))->render();
        $safeName = 'achado_' . $finding->id . '_' . Str::slug($finding->titulo);

        if ($request->boolean('preview') || $request->input('format') === 'html') {
            return response($html);
        }

        return Pdf::loadHTML($html)->setPaper('a4', 'portrait')->download("{$safeName}.pdf");
    }
}
