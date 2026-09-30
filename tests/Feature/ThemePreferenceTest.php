<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ThemePreferenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_update_theme_preference_via_api(): void
    {
        $user = User::factory()->create([
            'theme_preference' => 'dark',
        ]);

        $response = $this->actingAs($user)->patchJson(route('profile.theme'), [
            'theme' => 'light',
        ]);

        $response->assertOk()
            ->assertJson([
                'ok' => true,
                'theme' => 'light',
            ]);

        $this->assertSame('light', $user->fresh()->theme_preference);
    }

    public function test_theme_preference_rejects_invalid_values(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->patchJson(route('profile.theme'), [
            'theme' => 'neon_blue',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['theme']);
    }

    public function test_views_render_theme_toggle_and_anti_flicker_script(): void
    {
        $user = User::factory()->create([
            'role' => 'admin',
            'theme_preference' => 'light',
        ]);

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertOk()
            ->assertSee('Alternar Tema')
            ->assertSee('grc_theme');

        $profileResponse = $this->actingAs($user)->get(route('profile.edit'));

        $profileResponse->assertOk()
            ->assertSee('Preferência de Tema (Aparência)')
            ->assertSee('Modo Escuro (Padrão)')
            ->assertSee('Modo Claro');
    }
}
