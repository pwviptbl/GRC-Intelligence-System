<?php

namespace Tests\Feature;

use App\Models\Incidente;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SecurityHardeningTest extends TestCase
{
    use RefreshDatabase;

    public function test_web_responses_include_baseline_security_headers(): void
    {
        $this->get('/login')
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('X-Frame-Options', 'DENY')
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
    }

    public function test_incident_evidence_is_private_and_rejects_unsafe_extensions(): void
    {
        Storage::fake('local');
        Storage::fake('public');
        $user = User::factory()->create(['role' => 'admin']);
        $incident = Incidente::create([
            'titulo' => 'Incidente de teste',
            'descricao' => 'Teste de upload seguro.',
            'severidade' => 'Alta',
            'status' => 'aberto',
            'detectado_por' => 'Teste',
            'data_deteccao' => now()->toDateString(),
            'licoes_aprendidas' => '',
        ]);

        $this->actingAs($user)
            ->postJson(route('incidentes.add_evidence', $incident), [
                'evidencia' => UploadedFile::fake()->create('payload.php', 10, 'application/x-php'),
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('evidencia');

        $response = $this->actingAs($user)
            ->postJson(route('incidentes.add_evidence', $incident), [
                'evidencia' => UploadedFile::fake()->create('evidencia.tgz', 10, 'application/gzip'),
            ])
            ->assertOk();

        $path = $response->json('evidencias.0.arquivo_caminho');
        Storage::disk('local')->assertExists($path);
        Storage::disk('public')->assertMissing($path);
    }
}
