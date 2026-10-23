<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * php artisan db:seed
     * - Sempre: cria o primeiro Administrador (dono da loja).
     * - Só em ambiente local: usuários de teste e catálogo de exemplo (categorias, fornecedores, produtos).
     */
    public function run(): void
    {
        $this->call(AdminSeeder::class);

        if (app()->environment('local')) {
            $this->call([UsuariosTesteSeeder::class, CatalogoTesteSeeder::class]);
        }
    }
}
