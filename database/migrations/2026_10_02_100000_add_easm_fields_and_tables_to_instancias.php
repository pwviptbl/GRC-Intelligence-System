<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Expandir instancia_clientes
        Schema::table('instancia_clientes', function (Blueprint $table) {
            $table->string('nome_ambiente')->nullable()->after('software_id'); // Ex: Produção, Homologação, API Gateway
            $table->string('url_principal')->nullable()->after('branch');       // Ex: https://portal.cliente.com.br
            $table->string('endereco_ip')->nullable()->after('url_principal');   // Ex: 200.189.x.x
            $table->string('infra_provedor')->nullable()->after('endereco_ip'); // Ex: AWS, Azure, On-Premise, Oracle Cloud
            $table->string('status_exposicao')->default('publico')->after('infra_provedor'); // publico, vpn_only, interno
            $table->timestamp('ultimo_scan_em')->nullable()->after('status_exposicao');
        });

        // 2. Tabela de Certificados SSL/TLS
        Schema::create('instancia_ssl_certs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('instancia_cliente_id')->constrained('instancia_clientes')->cascadeOnDelete();
            $table->string('dominio');
            $table->string('emissor')->nullable();
            $table->timestamp('valido_de')->nullable();
            $table->timestamp('valido_ate')->nullable();
            $table->string('status_certificado')->default('ok'); // ok, expirando, expirado, invalido
            $table->integer('dias_restantes')->nullable();
            $table->json('detalhes_json')->nullable();
            $table->timestamps();
        });

        // 3. Tabela de Portas e Serviços Expostos
        Schema::create('instancia_portas_servicos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('instancia_cliente_id')->constrained('instancia_clientes')->cascadeOnDelete();
            $table->integer('porta');
            $table->string('protocolo')->default('tcp'); // tcp, udp
            $table->string('servico')->nullable();      // http, https, ssh, mysql, rdp, etc.
            $table->string('estado')->default('open');   // open, filtered, closed
            $table->string('banner')->nullable();       // Banner do serviço detectado
            $table->timestamp('visto_pela_ultima_vez_em')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('instancia_portas_servicos');
        Schema::dropIfExists('instancia_ssl_certs');
        Schema::table('instancia_clientes', function (Blueprint $table) {
            $table->dropColumn([
                'nome_ambiente',
                'url_principal',
                'endereco_ip',
                'infra_provedor',
                'status_exposicao',
                'ultimo_scan_em',
            ]);
        });
    }
};
