<?php

namespace App\Support;

use Closure;

/**
 * Memoriza, durante a requisição, os cálculos de postura de segurança
 * (security_score e test_cycle_status) de softwares e instâncias.
 *
 * Esses atributos estão em $appends e eram recalculados, com várias consultas,
 * a cada serialização do mesmo model. O cache é limpo sempre que um dado de
 * que eles dependem é salvo ou removido (ver AppServiceProvider) e em refresh().
 */
class PostureCache
{
    /** @var array<string, mixed> */
    private static array $values = [];

    public static function remember(string $key, Closure $compute): mixed
    {
        if (! array_key_exists($key, self::$values)) {
            self::$values[$key] = $compute();
        }

        return self::$values[$key];
    }

    public static function flush(): void
    {
        self::$values = [];
    }
}
