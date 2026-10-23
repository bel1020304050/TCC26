<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Registro de auditoria (seção 2.3): quem fez o quê e quando.
     * Ex.: "Mary Oliveira inativou a categoria Acessórios em 22/10/2026 14:50".
     */
    public function up(): void
    {
        Schema::create('auditorias', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('acao', 30);            // criou | editou | inativou | reativou
            $table->string('entidade', 50);        // ex.: categoria, fornecedor
            $table->unsignedBigInteger('entidade_id');
            $table->json('detalhes')->nullable();  // valores antes/depois, quando fizer sentido
            $table->timestamp('created_at')->useCurrent();

            $table->index(['entidade', 'entidade_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('auditorias');
    }
};
