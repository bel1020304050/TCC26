<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Usuários fictícios para testar os perfis no XAMPP (mesmos nomes dos wireframes).
 * Só roda com APP_ENV=local.
 */
class UsuariosTesteSeeder extends Seeder
{
    public function run(): void
    {
        $usuarios = [
            [
                'dados' => ['name' => 'Rafael Martins', 'email' => 'rafael@marymodas.com.br', 'cargo' => 'Subgerente', 'telefone' => '(44) 99876-1020'],
                'perfil' => User::PERFIL_FUNCIONARIO, 'status' => User::STATUS_ATIVO, 'senha' => 'func12345',
            ],
            [
                'dados' => ['name' => 'Juliana Lima', 'email' => 'juliana@marymodas.com.br', 'cargo' => 'Vendedora'],
                'perfil' => User::PERFIL_FUNCIONARIO, 'status' => User::STATUS_INATIVO, 'senha' => 'func12345',
            ],
            [
                'dados' => [
                    'name' => 'Ana Souza', 'email' => 'ana@email.com', 'telefone' => '(44) 99999-0000',
                    'cep' => '87501-000', 'rua' => 'Avenida Brasil', 'numero' => '120', 'complemento' => 'Apto 32',
                    'bairro' => 'Centro', 'cidade' => 'Umuarama', 'uf' => 'PR',
                ],
                'perfil' => User::PERFIL_CLIENTE, 'status' => User::STATUS_ATIVO, 'senha' => 'cliente123',
            ],
        ];

        foreach ($usuarios as $u) {
            $usuario = User::firstOrNew(['email' => $u['dados']['email']]);
            $usuario->fill($u['dados']);
            $usuario->perfil = $u['perfil'];
            $usuario->status = $u['status'];
            if (! $usuario->exists) {
                $usuario->password = $u['senha'];
            }
            $usuario->save();
        }
    }
}
