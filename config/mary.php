<?php

// Configurações próprias do sistema Mary Modas.
return [

    // Senha do primeiro Administrador criado pelo seeder (veja database/seeders/AdminSeeder.php)
    'admin_password' => env('SEED_ADMIN_PASSWORD', 'admin12345'),

    // Tempo limite (ms) da consulta à ViaCEP no navegador — RNF.02 da story 3.9
    'viacep_timeout_ms' => 5000,

];
