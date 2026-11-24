<?php

namespace Tests\Feature;

use App\Models\Auditoria;
use App\Models\Pagamento;
use App\Models\Pedido;
use App\Models\Produto;
use App\Models\User;
use Database\Seeders\DemonstracaoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * O seeder de demonstração usa as regras reais do sistema; este teste garante que ele continua rodando
 * e que os dados gerados são coerentes (estoque, pagamentos e auditoria).
 */
class DemonstracaoSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_gera_seis_meses_de_dados_coerentes(): void
    {
        Storage::fake('public');
        Carbon::setTestNow('2026-11-24 13:00');

        $this->seed(DemonstracaoSeeder::class);

        $this->assertSame(10, User::where('perfil', User::PERFIL_CLIENTE)->count());
        $this->assertSame(32, Produto::count());
        $this->assertGreaterThan(60, Pedido::count());
        $this->assertSame(0, Produto::where('estoque', '<', 0)->count());

        // Toda foto existe e todo pedido vendido tem um pagamento aprovado
        Produto::each(fn (Produto $p) => Storage::disk('public')->assertExists($p->imagem));
        Pedido::whereIn('status', Pedido::VENDA_PAGA)->each(
            fn (Pedido $p) => $this->assertTrue($p->pagamentos()->where('status', Pagamento::APROVADO)->exists()));

        // Pedidos de junho a novembro e auditoria com ações da equipe, de clientes e do sistema
        $this->assertSame(6, Pedido::pluck('created_at')->map->format('Y-m')->unique()->count());
        $this->assertTrue(Auditoria::whereNull('user_id')->exists());
        $this->assertGreaterThan(5, Auditoria::whereIn('acao', ['inativou', 'reativou', 'editou', 'redefiniu_senha'])->count());

        // Não pode haver nada "no futuro"
        $this->assertSame(0, Pedido::where('created_at', '>', now())->count());
    }

    public function test_nao_roda_em_banco_que_ja_tem_pedidos(): void
    {
        Storage::fake('public');
        Carbon::setTestNow('2026-11-24 13:00');
        $this->seed(DemonstracaoSeeder::class);
        $pedidos = Pedido::count();

        $this->seed(DemonstracaoSeeder::class);

        $this->assertSame($pedidos, Pedido::count());
    }
}
