<?php

namespace Database\Factories;

use App\Models\Promocao;
use App\Support\Status;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Promocao>
 */
class PromocaoFactory extends Factory
{
    // Padrão: promoção automática de 10%, geral, ativa e vigente
    public function definition(): array
    {
        return [
            'nome' => 'Promo '.fake()->unique()->word(),
            'modo' => Promocao::AUTOMATICA,
            'codigo' => null,
            'tipo' => Promocao::PERCENTUAL,
            'valor' => 10,
            'data_inicio' => today()->subDays(5),
            'data_fim' => today()->addDays(5),
            'aplicacao' => Promocao::GERAL,
            'status' => Status::ATIVO,
        ];
    }

    public function cupom(string $codigo): static
    {
        return $this->state(fn () => ['modo' => Promocao::CUPOM, 'codigo' => $codigo]);
    }
}
