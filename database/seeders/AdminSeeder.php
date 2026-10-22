<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Primeiro Administrador do sistema (seção 2.2 da documentação).
 * O cadastro público só cria clientes, então o dono da loja nasce por aqui.
 */
class AdminSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::firstOrNew(['email' => 'mary@marymodas.com.br']);
        $admin->fill([
            'name' => 'Mary Oliveira',
            'cargo' => 'Proprietária',
            'telefone' => '(44) 3622-0000',
        ]);
        $admin->perfil = User::PERFIL_ADMIN;
        $admin->status = User::STATUS_ATIVO;

        if (! $admin->exists) {
            $admin->password = config('mary.admin_password'); // vira hash automaticamente (cast "hashed")
        }

        $admin->save();
    }
}
