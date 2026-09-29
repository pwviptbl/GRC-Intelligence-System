<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Remove as tabelas do módulo Chat GRC, descontinuado.
     * O Chat foi substituído pela integração via MCP.
     */
    public function up(): void
    {
        // chat_messages primeiro por causa da FK para chat_conversations
        Schema::dropIfExists('chat_messages');
        Schema::dropIfExists('chat_conversations');
    }

    /**
     * Rollback: recriar as tabelas.
     */
    public function down(): void
    {
        Schema::create('chat_conversations', function (\Illuminate\Database\Schema\Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('titulo')->nullable();
            $table->timestamps();
        });

        Schema::create('chat_messages', function (\Illuminate\Database\Schema\Blueprint $table) {
            $table->id();
            $table->foreignId('chat_conversation_id')->constrained()->cascadeOnDelete();
            $table->enum('role', ['user', 'assistant']);
            $table->text('content');
            $table->timestamps();
        });
    }
};
