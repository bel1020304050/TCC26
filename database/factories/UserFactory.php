<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    protected static ?string $password;

    // Padrão: cliente ativo com endereço completo
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'telefone' => '(44) 9'.fake()->numerify('####-####'),
            'perfil' => User::PERFIL_CLIENTE,
            'status' => User::STATUS_ATIVO,
            'cep' => fake()->numerify('#####-###'),
            'rua' => 'Rua '.fake()->lastName(),
            'numero' => (string) fake()->numberBetween(1, 2000),
            'bairro' => 'Centro',
            'cidade' => 'Umuarama',
            'uf' => 'PR',
            'remember_token' => Str::random(10),
        ];
    }

    public function admin(): static
    {
        return $this->state(fn () => [
            'perfil' => User::PERFIL_ADMIN,
            'cep' => null, 'rua' => null, 'numero' => null, 'bairro' => null, 'cidade' => null, 'uf' => null,
        ]);
    }

    public function gerente(): static
    {
        return $this->state(fn () => [
            'perfil' => User::PERFIL_GERENTE,
            'cep' => null, 'rua' => null, 'numero' => null, 'bairro' => null, 'cidade' => null, 'uf' => null,
        ]);
    }

    public function atendente(): static
    {
        return $this->state(fn () => [
            'perfil' => User::PERFIL_ATENDENTE,
            'cep' => null, 'rua' => null, 'numero' => null, 'bairro' => null, 'cidade' => null, 'uf' => null,
        ]);
    }

    public function inativo(): static
    {
        return $this->state(fn () => ['status' => User::STATUS_INATIVO]);
    }

    public function encerrado(): static
    {
        return $this->state(fn () => ['status' => User::STATUS_ENCERRADO, 'encerrada_em' => now()]);
    }
}
