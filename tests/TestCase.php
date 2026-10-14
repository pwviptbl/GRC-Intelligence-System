<?php

namespace Tests;

use App\Support\PostureCache;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    protected function refreshApplication()
    {
        parent::refreshApplication();

        // Trava de segurança: com a config em cache (artisan optimize), o phpunit.xml
        // é ignorado e o RefreshDatabase rodaria contra o banco de produção. O
        // phpunit.xml já aponta APP_CONFIG_CACHE para outro arquivo; isto é a segunda camada.
        $database = config('database.connections.'.config('database.default').'.database');
        if (! $this->app->environment('testing') || ! in_array($database, ['testing', ':memory:'], true)) {
            throw new RuntimeException(
                "Testes abortados: ambiente '{$this->app->environment()}' e banco '{$database}'. "
                .'Os testes só rodam com APP_ENV=testing e o banco testing. Verifique a config em cache.'
            );
        }
    }

    protected function setUp(): void
    {
        parent::setUp();

        // Cache estático por requisição: não deve vazar entre testes.
        PostureCache::flush();
    }
}
