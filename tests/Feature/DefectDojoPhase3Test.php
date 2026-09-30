<?php

namespace Tests\Feature;

use App\Models\Engagement;
use App\Models\EngagementTest;
use App\Models\Finding;
use App\Models\Software;
use App\Models\User;
use App\Services\ScanImportService;
use App\Services\ScanParsers\BanditParser;
use App\Services\ScanParsers\BurpParser;
use App\Services\ScanParsers\GrypeParser;
use App\Services\ScanParsers\NiktoParser;
use App\Services\ScanParsers\NmapParser;
use App\Services\ScanParsers\NucleiParser;
use App\Services\ScanParsers\SemgrepParser;
use App\Services\ScanParsers\TrivyParser;
use App\Services\ScanParsers\ZapParser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DefectDojoPhase3Test extends TestCase
{
    use RefreshDatabase;

    private function makeUser(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    private function makeSoftware(): Software
    {
        return Software::create([
            'nome'       => 'Sistema de Cobrança Cloud',
            'tecnologia' => 'Node/Laravel',
            'ativo'      => true,
        ]);
    }

    private function makeTest(): EngagementTest
    {
        $sw = $this->makeSoftware();
        $eng = Engagement::create([
            'software_id' => $sw->id,
            'nome'        => 'Ciclo de Testes Automatizados',
            'tipo'        => 'dast',
            'status'      => 'ativo',
        ]);
        return EngagementTest::create([
            'engagement_id' => $eng->id,
            'titulo'        => 'Bateria de Scans',
            'tipo_teste'    => 'dast',
            'status'        => 'planejado',
        ]);
    }

    public function test_zap_json_parser(): void
    {
        $json = json_encode([
            'site' => [
                [
                    '@name' => 'https://api.empresa.com',
                    'alerts' => [
                        [
                            'alert' => 'SQL Injection Vulnerability',
                            'riskdesc' => 'High',
                            'cweid' => '89',
                            'desc' => 'Vulnerabilidade clássica no endpoint de autenticação.',
                            'solution' => 'Use prepared statements.',
                            'instances' => [
                                [
                                    'uri' => 'https://api.empresa.com/login',
                                    'method' => 'POST',
                                    'param' => 'username',
                                    'evidence' => "' OR '1'='1",
                                ]
                            ]
                        ]
                    ]
                ]
            ]
        ]);

        $parser = new ZapParser();
        $this->assertTrue($parser->canParse('zap_report.json', $json));

        $findings = $parser->parse($json);
        $this->assertCount(1, $findings);

        $f = $findings->first();
        $this->assertEquals('SQL Injection Vulnerability', $f['titulo']);
        $this->assertEquals('alto', $f['severidade']);
        $this->assertEquals('CWE-89', $f['cwe_id']);
        $this->assertEquals('https://api.empresa.com/login', $f['endpoint']);
        $this->assertEquals('username', $f['parametro']);
        $this->assertEquals('POST', $f['metodo_http']);
    }

    public function test_nuclei_jsonl_parser(): void
    {
        $jsonl = '{"template-id":"cve-2021-44228","info":{"name":"Apache Log4j RCE","severity":"critical","classification":{"cve-id":["CVE-2021-44228"],"cwe-id":["CWE-502"],"cvss-score":10.0},"remediation":"Upgrade log4j"},"type":"http","matched-at":"https://app.empresa.com/search","curl-command":"curl -X GET https://app.empresa.com/search"}';

        $parser = new NucleiParser();
        $this->assertTrue($parser->canParse('nuclei_output.json', $jsonl));

        $findings = $parser->parse($jsonl);
        $this->assertCount(1, $findings);

        $f = $findings->first();
        $this->assertEquals('Apache Log4j RCE', $f['titulo']);
        $this->assertEquals('critico', $f['severidade']);
        $this->assertEquals('CVE-2021-44228', $f['cve_id']);
        $this->assertEquals('CWE-502', $f['cwe_id']);
        $this->assertEquals(10.0, $f['cvss_score']);
        $this->assertEquals('https://app.empresa.com/search', $f['endpoint']);
        $this->assertStringContainsString('curl', $f['prova_conceito']);
    }

    public function test_nmap_xml_parser(): void
    {
        $xml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<nmaprun scanner="nmap" version="7.94">
  <host>
    <address addr="192.168.1.50" addrtype="ipv4"/>
    <ports>
      <port protocol="tcp" portid="22">
        <state state="open"/>
        <service name="ssh" product="OpenSSH" version="8.2p1"/>
      </port>
      <port protocol="tcp" portid="3306">
        <state state="open"/>
        <service name="mysql" product="MySQL" version="5.7"/>
      </port>
    </ports>
  </host>
</nmaprun>
XML;

        $parser = new NmapParser();
        $this->assertTrue($parser->canParse('nmap.xml', $xml));

        $findings = $parser->parse($xml);
        $this->assertCount(2, $findings);

        $ssh = $findings->first();
        $this->assertStringContainsString('22/tcp', $ssh['titulo']);
        $this->assertEquals('192.168.1.50:22', $ssh['endpoint']);
    }

    public function test_nikto_text_parser(): void
    {
        $txt = <<<TXT
- Nikto v2.1.6
+ Target IP:          10.0.0.15
+ Target Hostname:    web.empresa.com
+ /admin/login.php: Administration login page found.
+ /phpinfo.php: Output from the phpinfo() function was found.
TXT;

        $parser = new NiktoParser();
        $this->assertTrue($parser->canParse('nikto.txt', $txt));

        $findings = $parser->parse($txt);
        $this->assertCount(2, $findings);
        $this->assertStringContainsString('Nikto:', $findings->first()['titulo']);
    }

    public function test_semgrep_json_parser(): void
    {
        $json = json_encode([
            'results' => [
                [
                    'check_id' => 'php.lang.security.injection.tainted-sql',
                    'path' => 'app/Http/Controllers/UserController.php',
                    'start' => ['line' => 45],
                    'extra' => [
                        'message' => 'Potential SQL Injection via Request input',
                        'severity' => 'ERROR',
                        'lines' => 'DB::select("SELECT * FROM users WHERE id = " . $id);',
                        'metadata' => [
                            'cwe' => ['CWE-89'],
                            'fix' => 'Use parameter binding: DB::select("SELECT * FROM users WHERE id = ?", [$id]);',
                        ]
                    ]
                ]
            ]
        ]);

        $parser = new SemgrepParser();
        $this->assertTrue($parser->canParse('semgrep.json', $json));

        $findings = $parser->parse($json);
        $this->assertCount(1, $findings);

        $f = $findings->first();
        $this->assertStringContainsString('tainted-sql', $f['titulo']);
        $this->assertEquals('critico', $f['severidade']);
        $this->assertEquals('CWE-89', $f['cwe_id']);
        $this->assertEquals('app/Http/Controllers/UserController.php:45', $f['endpoint']);
    }

    public function test_trivy_json_parser(): void
    {
        $json = json_encode([
            'SchemaVersion' => 2,
            'ArtifactName' => 'node:18-alpine',
            'Results' => [
                [
                    'Target' => 'node:18-alpine (alpine 3.18)',
                    'Vulnerabilities' => [
                        [
                            'VulnerabilityID' => 'CVE-2023-5363',
                            'PkgName' => 'openssl',
                            'InstalledVersion' => '3.1.2-r0',
                            'FixedVersion' => '3.1.4-r0',
                            'Severity' => 'HIGH',
                            'Title' => 'openssl: incorrect cipher key and IV length processing',
                            'Description' => 'OpenSSL vulnerability in cipher processing',
                            'CweIDs' => ['CWE-130'],
                            'CVSS' => [
                                'nvd' => ['V3Score' => 7.5]
                            ]
                        ]
                    ]
                ]
            ]
        ]);

        $parser = new TrivyParser();
        $this->assertTrue($parser->canParse('trivy.json', $json));

        $findings = $parser->parse($json);
        $this->assertCount(1, $findings);

        $f = $findings->first();
        $this->assertEquals('Trivy: openssl (CVE-2023-5363)', $f['titulo']);
        $this->assertEquals('alto', $f['severidade']);
        $this->assertEquals('CVE-2023-5363', $f['cve_id']);
        $this->assertEquals('CWE-130', $f['cwe_id']);
        $this->assertEquals(7.5, $f['cvss_score']);
    }

    public function test_scan_import_endpoint_uploads_and_deduplicates(): void
    {
        Storage::fake('local');
        $user = $this->makeUser();
        $test = $this->makeTest();

        // 1. Primeiro upload de scan com uma falha (ZAP JSON)
        $zapJson = json_encode([
            'site' => [
                [
                    '@name' => 'https://sistema.teste',
                    'alerts' => [
                        [
                            'alert' => 'Cross-Site Scripting (Reflected)',
                            'riskdesc' => 'High',
                            'cweid' => '79',
                            'desc' => 'XSS no campo q',
                            'instances' => [
                                [
                                    'uri' => 'https://sistema.teste/search',
                                    'param' => 'q',
                                    'method' => 'GET',
                                ]
                            ]
                        ]
                    ]
                ]
            ]
        ]);

        $uploadedFile = UploadedFile::fake()->createWithContent('zap_scan.json', $zapJson);

        $response = $this->actingAs($user)->post(route('engagement-tests.import', $test), [
            'arquivo_scan' => $uploadedFile,
            'formato_scan' => 'zap',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        // Valida que o finding foi criado no banco
        $this->assertDatabaseHas('findings', [
            'test_id' => $test->id,
            'titulo'  => 'Cross-Site Scripting (Reflected)',
            'cwe_id'  => 'CWE-79',
            'status'  => 'aberto',
        ]);

        $test->refresh();
        $this->assertEquals('concluido', $test->status);
        $this->assertEquals('zap', $test->formato_scan);

        // 2. Cria um segundo teste e reenvia o mesmo scan (deve ser marcado como DUPLICADO automaticamente!)
        $test2 = EngagementTest::create([
            'engagement_id' => $test->engagement_id,
            'titulo'        => 'Segundo Teste ZAP',
            'tipo_teste'    => 'dast',
            'status'        => 'planejado',
        ]);

        $uploadedFile2 = UploadedFile::fake()->createWithContent('zap_scan2.json', $zapJson);

        $response2 = $this->actingAs($user)->post(route('engagement-tests.import', $test2), [
            'arquivo_scan' => $uploadedFile2,
            'formato_scan' => 'zap',
        ]);

        $response2->assertRedirect();
        $response2->assertSessionHas('success');

        // Valida que o novo achado no segundo teste recebeu status duplicado
        $duplicateFinding = Finding::where('test_id', $test2->id)->first();
        $this->assertNotNull($duplicateFinding);
        $this->assertEquals('duplicado', $duplicateFinding->status);
        $this->assertNotNull($duplicateFinding->duplicado_de_id);
    }
}
