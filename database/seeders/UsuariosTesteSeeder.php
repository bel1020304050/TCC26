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
                'dados' => ['name' => 'Bruno Costa', 'email' => 'bruno@marymodas.com.br', 'telefone' => '(44) 99777-3030'],
                'perfil' => User::PERFIL_GERENTE, 'status' => User::STATUS_ATIVO, 'senha' => 'gerente123',
            ],
            [
                'dados' => ['name' => 'Rafael Martins', 'email' => 'rafael@marymodas.com.br', 'telefone' => '(44) 99876-1020'],
                'perfil' => User::PERFIL_ATENDENTE, 'status' => User::STATUS_ATIVO, 'senha' => 'func12345',
            ],
            [
                'dados' => ['name' => 'Juliana Lima', 'email' => 'juliana@marymodas.com.br'],
                'perfil' => User::PERFIL_ATENDENTE, 'status' => User::STATUS_INATIVO, 'senha' => 'func12345',
            ],
            [
                'dados' => [
                    'name' => 'Ana Souza', 'email' => 'ana@email.com', 'telefone' => '(44) 99999-0000', 'cpf' => '123.456.789-09',
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
                $usuario->email_verified_at = now(); // contas de teste já entram com o e-mail confirmado
            }
            $usuario->save();
        }
    }
}
