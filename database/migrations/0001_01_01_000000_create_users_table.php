<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tabela única de usuários para os 3 perfis (cliente, funcionário e administrador).
     * Documentação: seções 2.2 (perfis), 3.5–3.8 (usuários internos) e 3.9–3.12 (conta do cliente).
     */
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->string('email', 150)->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->string('telefone', 20)->nullable();

            // Perfil define as permissões; cargo é apenas informativo (ex.: Subgerente)
            $table->string('perfil', 20)->default('cliente')->index();   // cliente | funcionario | admin
            $table->string('cargo', 60)->nullable();

            // Exclusão lógica: ninguém é apagado do banco
            $table->string('status', 20)->default('ativo')->index();     // ativo | inativo | encerrado
            $table->timestamp('encerrada_em')->nullable();                // conta de cliente encerrada (3.12)

            // Endereço de entrega (obrigatório só para clientes, validado no formulário)
            $table->string('cep', 9)->nullable();
            $table->string('rua', 120)->nullable();
            $table->string('numero', 10)->nullable();
            $table->string('complemento', 60)->nullable();
            $table->string('bairro', 80)->nullable();
            $table->string('cidade', 80)->nullable();
            $table->char('uf', 2)->nullable();

            $table->rememberToken();
            $table->timestamps();
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('sessions');
    }
};
