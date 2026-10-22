<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * php artisan db:seed
     * - Sempre: cria o primeiro Administrador (dono da loja).
     * - Só em ambiente local: cria usuários de teste para cada perfil.
     */
    public function run(): void
    {
        $this->call(AdminSeeder::class);

        if (app()->environment('local')) {
            $this->call(UsuariosTesteSeeder::class);
        }
    }
}
