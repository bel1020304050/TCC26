<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Carrinho do cliente — story 3.30 (apoio).
 * Estados (seção 2.3): aberto → em_pagamento → finalizado.
 * Um cliente tem no máximo um carrinho não finalizado por vez.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('carrinhos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users');
            $table->string('status', 20)->default('aberto')->index();
            $table->timestamps();
        });

        Schema::create('carrinho_itens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('carrinho_id')->constrained('carrinhos')->cascadeOnDelete();
            $table->foreignId('produto_id')->constrained('produtos');
            $table->unsignedInteger('quantidade');
            $table->timestamps();

            $table->unique(['carrinho_id', 'produto_id']); // o mesmo produto vira uma linha só
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('carrinho_itens');
        Schema::dropIfExists('carrinhos');
    }
};
