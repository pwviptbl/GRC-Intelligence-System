<?php

namespace Tests;

use App\Support\PostureCache;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Cache estático por requisição: não deve vazar entre testes.
        PostureCache::flush();
    }
}
