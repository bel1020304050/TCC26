<?php

namespace Database\Factories;

use App\Models\Categoria;
use App\Models\Produto;
use App\Support\Status;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Produto>
 */
class ProdutoFactory extends Factory
{
    public function definition(): array
    {
        return [
            'nome' => ucfirst(fake()->unique()->words(2, true)),
            'categoria_id' => Categoria::factory(),
            'fornecedor_id' => null,
            'preco' => fake()->randomFloat(2, 30, 300),
            'estoque' => fake()->numberBetween(0, 30),
            'tamanho' => fake()->randomElement(['P', 'M', 'G']),
            'cor' => fake()->randomElement(['Preto', 'Branco', 'Azul']),
            'status' => Status::ATIVO,
        ];
    }

    public function inativo(): static
    {
        return $this->state(fn () => ['status' => Status::INATIVO]);
    }
}
