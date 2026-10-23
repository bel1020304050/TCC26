<?php

namespace Database\Factories;

use App\Models\Categoria;
use App\Support\Status;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Categoria>
 */
class CategoriaFactory extends Factory
{
    public function definition(): array
    {
        return [
            'nome' => ucfirst(fake()->unique()->words(2, true)),
            'descricao' => fake()->optional()->sentence(4),
            'status' => Status::ATIVO,
        ];
    }

    public function inativa(): static
    {
        return $this->state(fn () => ['status' => Status::INATIVO]);
    }
}
