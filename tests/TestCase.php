<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Http;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Segurança dos testes: nenhuma chamada de internet "escapa" (ex.: Asaas de verdade).
        // Teste que precisa de uma resposta externa a imita com Http::fake().
        Http::preventStrayRequests();
    }
}
