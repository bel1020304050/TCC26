<?php

// Configurações próprias do sistema Mary Modas.
return [

    // Senha do primeiro Administrador criado pelo seeder (veja database/seeders/AdminSeeder.php)
    'admin_password' => env('SEED_ADMIN_PASSWORD', 'admin12345'),

    // Tempo limite (ms) da consulta à ViaCEP no navegador; depois disso o endereço fica livre para digitar
    'viacep_timeout_ms' => 5000,

    // Pagamento: "simulado" (funciona sem internet) ou "asaas" (sandbox do Asaas, cobranças de teste reais)
    'pagamento' => [
        'provedor' => env('PAGAMENTO_PROVEDOR', 'simulado'),
        'asaas' => [
            'url' => rtrim((string) env('ASAAS_URL', 'https://api-sandbox.asaas.com/v3'), '/'),
            'chave' => env('ASAAS_API_KEY'),
            // Token que o Asaas manda no cabeçalho "asaas-access-token" de cada aviso (webhook)
            'webhook_token' => env('ASAAS_WEBHOOK_TOKEN'),
            'timeout' => 15, // segundos; depois disso o pagamento é tratado como "provedor fora do ar"
        ],
    ],

];
