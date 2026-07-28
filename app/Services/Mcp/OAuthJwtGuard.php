<?php

namespace App\Services\Mcp;

use Firebase\JWT\CachedKeySet;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use GuzzleHttp\Psr7\HttpFactory;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use stdClass;

/**
 * Guarda OAuth 2.1 para o endpoint MCP.
 *
 * Responsabilidades:
 * - Buscar e memorizar JWKS do provedor de identidade (Auth0 ou compatível).
 * - Validar assinatura JWT usando a biblioteca firebase/php-jwt.
 * - Validar claims: iss, aud, exp, nbf, iat.
 * - Extrair e retornar os scopes do token para avaliação pelo controller.
 *
 * Não realiza chamadas de rede na própria validação; o JWKS é buscado uma
 * única vez e armazenado em cache (padrão: 600s).
 */
final class OAuthJwtGuard
{
    /**
     * Resultado de uma validação bem-sucedida de token.
     */
    public readonly string $subject;

    /** @var list<string> */
    public readonly array $scopes;

    public readonly string $tokenFingerprint;

    private function __construct(string $subject, array $scopes, string $tokenFingerprint)
    {
        $this->subject = $subject;
        $this->scopes = $scopes;
        $this->tokenFingerprint = $tokenFingerprint;
    }

    // -------------------------------------------------------------------
    // Factory / validação
    // -------------------------------------------------------------------

    /**
     * Valida o JWT e retorna uma instância com as claims extraídas.
     *
     * @throws OAuthJwtException Em qualquer falha de validação.
     */
    public static function validate(string $rawToken, array $config): self
    {
        $issuer   = rtrim((string) ($config['issuer'] ?? ''), '/').'/';
        $audience = (string) ($config['audience'] ?? '');
        $leeway   = (int) ($config['leeway_seconds'] ?? 30);

        if ($issuer === '/' || $audience === '') {
            throw new OAuthJwtException('oauth_not_configured', 'MCP OAuth não está configurado.');
        }

        // Ajusta clock skew tolerado pelo firebase/jwt
        JWT::$leeway = $leeway;

        $keys = self::fetchKeys($config);

        try {
            /** @var stdClass $payload */
            $payload = JWT::decode($rawToken, $keys);
        } catch (\Firebase\JWT\ExpiredException $e) {
            throw new OAuthJwtException('token_expired', 'Token expirado.', $e);
        } catch (\Firebase\JWT\BeforeValidException $e) {
            throw new OAuthJwtException('token_not_yet_valid', 'Token ainda não é válido.', $e);
        } catch (\Firebase\JWT\SignatureInvalidException $e) {
            throw new OAuthJwtException('invalid_signature', 'Assinatura JWT inválida.', $e);
        } catch (\UnexpectedValueException $e) {
            throw new OAuthJwtException('invalid_token', 'Token JWT malformado: '.$e->getMessage(), $e);
        } catch (\Throwable $e) {
            throw new OAuthJwtException('invalid_token', 'Erro ao decodificar token.', $e);
        }

        // Validar issuer manualmente (firebase/jwt valida apenas se especificado em options)
        $tokenIss = rtrim((string) ($payload->iss ?? ''), '/').'/';
        if ($tokenIss !== $issuer) {
            throw new OAuthJwtException(
                'invalid_issuer',
                "Issuer inválido. Esperado: {$issuer}, recebido: {$tokenIss}."
            );
        }

        // Validar audience (pode ser string ou array)
        $tokenAud = $payload->aud ?? null;
        $audArray = is_array($tokenAud) ? $tokenAud : [(string) $tokenAud];
        if (! in_array($audience, $audArray, true)) {
            throw new OAuthJwtException(
                'invalid_audience',
                "Audience inválida. Esperada: {$audience}."
            );
        }

        // Extrair subject e scopes
        $subject = (string) ($payload->sub ?? '');
        $scopeString = (string) ($payload->scope ?? '');
        $scopes = $scopeString !== '' ? explode(' ', $scopeString) : [];

        $fingerprint = substr(hash('sha256', $rawToken), 0, 16);

        return new self($subject, $scopes, $fingerprint);
    }

    /**
     * Verifica se o token contém determinado scope.
     */
    public function hasScope(string $scope): bool
    {
        return in_array($scope, $this->scopes, true);
    }

    // -------------------------------------------------------------------
    // JWKS / cache
    // -------------------------------------------------------------------

    /**
     * Retorna as chaves públicas para validação, usando cache para evitar
     * uma chamada HTTP a cada requisição MCP.
     *
     * @return array<string, Key>
     * @throws OAuthJwtException
     */
    private static function fetchKeys(array $config): array
    {
        $issuer  = rtrim((string) ($config['issuer'] ?? ''), '/').'/';
        $jwksUri = (string) ($config['jwks_uri'] ?? '');
        $cacheTtl = (int) ($config['jwks_cache_ttl'] ?? 600);

        if ($jwksUri === '') {
            $jwksUri = $issuer.'.well-known/jwks.json';
        }

        $cacheKey = 'mcp.oauth.jwks.'.md5($jwksUri);

        $jwks = Cache::remember($cacheKey, $cacheTtl, function () use ($jwksUri) {
            try {
                $response = Http::timeout(5)->get($jwksUri);
                if (! $response->successful()) {
                    throw new \RuntimeException(
                        "JWKS endpoint retornou HTTP {$response->status()}: {$jwksUri}"
                    );
                }

                return $response->json();
            } catch (\Throwable $e) {
                Log::error('MCP OAuth: falha ao buscar JWKS', [
                    'uri'   => $jwksUri,
                    'error' => $e->getMessage(),
                ]);
                throw new OAuthJwtException('jwks_fetch_failed', 'Falha ao buscar chaves JWKS: '.$e->getMessage(), $e);
            }
        });

        if (! is_array($jwks) || empty($jwks['keys'])) {
            // Invalida cache corrompido e rejeita
            Cache::forget($cacheKey);
            throw new OAuthJwtException('jwks_invalid', 'JWKS inválido ou vazio.');
        }

        try {
            return self::parseJwks($jwks);
        } catch (\Throwable $e) {
            Cache::forget($cacheKey);
            throw new OAuthJwtException('jwks_parse_failed', 'Falha ao interpretar JWKS: '.$e->getMessage(), $e);
        }
    }

    /**
     * Transforma o array JWKS em um mapa kid → Key compatível com firebase/jwt.
     *
     * @return array<string, Key>
     * @throws \InvalidArgumentException
     */
    private static function parseJwks(array $jwks): array
    {
        $keys = [];
        foreach ($jwks['keys'] as $keyData) {
            if (! isset($keyData['kid'], $keyData['kty'])) {
                continue;
            }

            $kid = (string) $keyData['kid'];
            $kty = strtoupper((string) $keyData['kty']);
            $alg = (string) ($keyData['alg'] ?? match ($kty) {
                'RSA' => 'RS256',
                'EC'  => 'ES256',
                default => '',
            });

            if ($alg === '') {
                continue;
            }

            // Reconstrói chave pública via OpenSSL
            $pem = self::jwkToPem($keyData);
            if ($pem !== null) {
                $keys[$kid] = new Key($pem, $alg);
            }
        }

        if ($keys === []) {
            throw new \InvalidArgumentException('Nenhuma chave utilizável encontrada no JWKS.');
        }

        return $keys;
    }

    /**
     * Converte uma entrada JWK (RSA ou EC) em PEM de chave pública.
     */
    private static function jwkToPem(array $key): ?string
    {
        $kty = strtoupper((string) ($key['kty'] ?? ''));

        if ($kty === 'RSA') {
            return self::rsaJwkToPem($key);
        }

        if ($kty === 'EC') {
            return self::ecJwkToPem($key);
        }

        return null;
    }

    private static function rsaJwkToPem(array $key): ?string
    {
        if (! isset($key['n'], $key['e'])) {
            return null;
        }

        $n = self::base64UrlDecode($key['n']);
        $e = self::base64UrlDecode($key['e']);

        if ($n === false || $e === false) {
            return null;
        }

        // Monta DER da chave pública RSA (SubjectPublicKeyInfo)
        $der = self::encodeDerRsaPublicKey($n, $e);
        if ($der === null) {
            return null;
        }

        return "-----BEGIN PUBLIC KEY-----\n".
            wordwrap(base64_encode($der), 64, "\n", true).
            "\n-----END PUBLIC KEY-----";
    }

    private static function ecJwkToPem(array $key): ?string
    {
        if (! isset($key['crv'], $key['x'], $key['y'])) {
            return null;
        }

        $crv = (string) $key['crv'];
        $x = self::base64UrlDecode($key['x']);
        $y = self::base64UrlDecode($key['y']);

        if ($x === false || $y === false) {
            return null;
        }

        // Parâmetros de curva para P-256, P-384 e P-521
        $oids = [
            'P-256' => "\x2a\x86\x48\xce\x3d\x03\x01\x07",
            'P-384' => "\x2b\x81\x04\x00\x22",
            'P-521' => "\x2b\x81\x04\x00\x23",
        ];

        if (! isset($oids[$crv])) {
            return null;
        }

        $curveOid = $oids[$crv];
        $ecOid    = "\x2a\x86\x48\xce\x3d\x02\x01"; // id-ecPublicKey

        // UncompressedPoint = 0x04 || x || y
        $point = "\x04".$x.$y;

        // AlgorithmIdentifier: SEQUENCE { OID id-ecPublicKey, OID curve }
        $algId = self::derSequence(
            self::derOid($ecOid).self::derOid($curveOid)
        );

        // SubjectPublicKeyInfo: SEQUENCE { AlgorithmIdentifier, BIT STRING }
        $der = self::derSequence($algId.self::derBitString($point));

        return "-----BEGIN PUBLIC KEY-----\n".
            wordwrap(base64_encode($der), 64, "\n", true).
            "\n-----END PUBLIC KEY-----";
    }

    // -------------------------------------------------------------------
    // Helpers DER
    // -------------------------------------------------------------------

    private static function encodeDerRsaPublicKey(string $n, string $e): ?string
    {
        // Remove zero byte de sinal e readiciona se necessário
        $nDer = self::derInteger($n);
        $eDer = self::derInteger($e);

        // RSAPublicKey ::= SEQUENCE { modulus INTEGER, publicExponent INTEGER }
        $rsaKey = self::derSequence($nDer.$eDer);

        // AlgorithmIdentifier: SEQUENCE { OID rsaEncryption, NULL }
        $rsaOid = "\x2a\x86\x48\x86\xf7\x0d\x01\x01\x01"; // rsaEncryption OID
        $algId  = self::derSequence(self::derOid($rsaOid)."\x05\x00");

        // SubjectPublicKeyInfo: SEQUENCE { AlgorithmIdentifier, BIT STRING }
        return self::derSequence($algId.self::derBitString($rsaKey));
    }

    private static function derSequence(string $content): string
    {
        return "\x30".self::derLength(strlen($content)).$content;
    }

    private static function derOid(string $oid): string
    {
        return "\x06".self::derLength(strlen($oid)).$oid;
    }

    private static function derBitString(string $content): string
    {
        return "\x03".self::derLength(strlen($content) + 1)."\x00".$content;
    }

    private static function derInteger(string $bytes): string
    {
        // Garante que o byte mais significativo não seja interpretado como negativo
        if (ord($bytes[0]) > 0x7f) {
            $bytes = "\x00".$bytes;
        }

        return "\x02".self::derLength(strlen($bytes)).$bytes;
    }

    private static function derLength(int $length): string
    {
        if ($length < 128) {
            return chr($length);
        }

        if ($length < 256) {
            return "\x81".chr($length);
        }

        return "\x82".chr($length >> 8).chr($length & 0xff);
    }

    private static function base64UrlDecode(string $data): string|false
    {
        $pad = 4 - (strlen($data) % 4);
        if ($pad < 4) {
            $data .= str_repeat('=', $pad);
        }

        return base64_decode(strtr($data, '-_', '+/'));
    }
}
