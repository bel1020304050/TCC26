<?php

namespace Tests\Feature\Painel;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * "Minha conta" da equipe: nome, telefone e senha (seção 2.2).
 */
class MinhaContaEquipeTest extends TestCase
{
    use RefreshDatabase;

    public function test_funcionario_abre_e_atualiza_nome_e_telefone(): void
    {
        $func = User::factory()->funcionario()->create();

        $this->actingAs($func)->get('/painel/minha-conta')->assertOk()->assertSee($func->email);

        $this->actingAs($func)
            ->put('/painel/minha-conta', ['name' => 'Rafael M.', 'telefone' => '(44) 90000-0000', 'perfil' => 'admin', 'email' => 'outro@x.com'])
            ->assertSessionHas('sucesso');

        $func->refresh();
        $this->assertSame('Rafael M.', $func->name);
        $this->assertSame('(44) 90000-0000', $func->telefone);
        $this->assertTrue($func->isFuncionario());      // perfil não muda por aqui
        $this->assertNotSame('outro@x.com', $func->email); // e-mail também não
    }

    public function test_troca_senha_com_a_senha_atual_correta(): void
    {
        $func = User::factory()->funcionario()->create(['password' => 'antiga1234']);

        $this->actingAs($func)
            ->put('/painel/minha-conta/senha', ['senha_atual' => 'antiga1234', 'password' => 'nova12345', 'password_confirmation' => 'nova12345'])
            ->assertSessionHas('sucesso');

        $this->assertTrue(Hash::check('nova12345', $func->fresh()->password));
    }

    public function test_senha_atual_errada_e_recusada(): void
    {
        $func = User::factory()->funcionario()->create(['password' => 'antiga1234']);

        $this->actingAs($func)
            ->put('/painel/minha-conta/senha', ['senha_atual' => 'errada123', 'password' => 'nova12345', 'password_confirmation' => 'nova12345'])
            ->assertSessionHasErrorsIn('senha', ['senha_atual' => 'A senha atual está incorreta.']);

        $this->assertTrue(Hash::check('antiga1234', $func->fresh()->password));
    }

    public function test_cliente_nao_acessa_minha_conta_do_painel(): void
    {
        $this->actingAs(User::factory()->create())->get('/painel/minha-conta')->assertForbidden();
    }
}
