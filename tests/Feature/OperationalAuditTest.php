<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OperationalAuditTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_mutation_is_audited_without_request_payload(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->patch('/profile', [
            'name' => 'Nome atualizado',
        ])->assertRedirect('/profile');

        $this->assertDatabaseHas('audit_events', [
            'user_id' => $user->id,
            'action' => 'web.mutation',
            'source' => 'web',
            'route_name' => 'profile.update',
            'status_code' => 302,
        ]);
    }

    public function test_failed_login_records_only_a_fingerprint(): void
    {
        $this->post('/login', [
            'email' => 'nao-existe@example.test',
            'password' => 'senha-incorreta',
        ]);

        $event = \App\Models\AuditEvent::query()->where('action', 'auth.login_failed')->firstOrFail();
        $this->assertArrayHasKey('email_fingerprint', $event->context);
        $this->assertArrayNotHasKey('password', $event->context);
    }
}
