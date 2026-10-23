<?php

namespace Database\Factories;

use App\Models\Fornecedor;
use App\Support\Status;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Fornecedor>
 */
class FornecedorFactory extends Factory
{
    public function definition(): array
    {
        return [
            'nome' => fake()->company(),
            'documento' => null,
            'telefone' => '(44) 3622-'.fake()->numerify('####'),
            'email' => fake()->unique()->companyEmail(),
            'status' => Status::ATIVO,
        ];
    }

    public function inativo(): static
    {
        return $this->state(fn () => ['status' => Status::INATIVO]);
    }
}
