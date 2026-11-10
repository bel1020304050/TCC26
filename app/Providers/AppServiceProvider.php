<?php

namespace App\Providers;

use App\Models\User;
use App\Pagamentos\PagamentoSimulado;
use App\Pagamentos\ProvedorPagamento;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Provedor de pagamento: hoje é o simulado. Para usar um provedor real,
        // basta ligar aqui outra classe que implemente ProvedorPagamento.
        $this->app->bind(ProvedorPagamento::class, PagamentoSimulado::class);
    }

    public function boot(): void
    {
        $this->limitesDeTentativas();

        // Segurança: os relatórios de Vendas, Clientes e Estoque são para toda a equipe
        Gate::define('relatorio-vendas', fn (User $user) => $user->isEquipe());
        Gate::define('relatorio-clientes', fn (User $user) => $user->isEquipe());
        Gate::define('relatorio-estoque', fn (User $user) => $user->isEquipe());

        // Evita erro de tamanho de índice em versões antigas de MySQL/MariaDB (XAMPP)
        Schema::defaultStringLength(191);

        // Paginação com o visual do Bootstrap 5
        Paginator::useBootstrapFive();

        // URLs em português nos CRUDs: /categorias/novo e /categorias/1/editar
        Route::resourceVerbs(['create' => 'novo', 'edit' => 'editar']);
    }

    /**
     * Segurança: limites de tentativas por minuto, cada rota com o seu contador.
     * (Com o "throttle:5,1" sem nome, todas as rotas somavam no mesmo contador: 3 cliques num link
     * vencido bloqueavam o primeiro "Reenviar e-mail".)
     */
    private function limitesDeTentativas(): void
    {
        // Visitante: conta por endereço IP
        RateLimiter::for('esqueci-senha', fn (Request $r) => Limit::perMinute(5)->by($r->ip()));
        RateLimiter::for('redefinir-senha', fn (Request $r) => Limit::perMinute(5)->by($r->ip()));

        // Cliente logado: conta por conta
        RateLimiter::for('confirmar-email', fn (Request $r) => Limit::perMinute(6)->by((string) $r->user()?->id));
        RateLimiter::for('reenviar-email', fn (Request $r) => Limit::perMinute(3)->by((string) $r->user()?->id));
    }
}
