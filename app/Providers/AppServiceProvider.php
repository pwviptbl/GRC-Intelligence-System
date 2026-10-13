<?php

namespace App\Providers;

use App\Models\ControleEvento;
use App\Models\Engagement;
use App\Models\EngagementTest;
use App\Models\Finding;
use App\Models\InstanciaCliente;
use App\Models\Software;
use App\Observers\ControleEventoObserver;
use App\Support\PostureCache;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        ControleEvento::observe(ControleEventoObserver::class);

        // Os cálculos de postura (security_score/test_cycle_status) ficam em cache durante a
        // requisição; qualquer escrita nos dados de que dependem invalida o cache.
        foreach ([Software::class, InstanciaCliente::class, Engagement::class, EngagementTest::class, Finding::class] as $model) {
            $model::saved(fn () => PostureCache::flush());
            $model::deleted(fn () => PostureCache::flush());
        }

        if (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') {
            URL::forceScheme('https');
        }
    }
}
