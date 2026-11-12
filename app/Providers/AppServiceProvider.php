<?php

namespace App\Providers;

use App\Models\User;
use App\Pagamentos\Asaas\AsaasProvedor;
use App\Pagamentos\Asaas\ClienteAsaas;
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
        // Provedor de pagamento (Strategy): o .env decide qual classe responde.
        // PAGAMENTO_PROVEDOR=asaas -> sandbox do Asaas; qualquer outro valor -> simulado
        $this->app->bind(ClienteAsaas::class, fn () => ClienteAsaas::daConfiguracao());
        $this->app->bind(ProvedorPagamento::class, fn ($app) => config('mary.pagamento.provedor') === 'asaas'
            ? $app->make(AsaasProvedor::class)
            : $app->make(PagamentoSimulado::class));
    }

    public function boot(): void
    {
        $this->limitesDeTentativas();

        // Segurança: os relatórios de Vendas, Clientes e Estoque são para toda a equipe
        Gate::define('relatorio-vendas', fn (User $user) => $user->isEquipe());
        Gate::define('relatorio-clientes', fn (User $user) => $user->isEquipe());
        Gate::define('relatorio-estoque', fn (User $user) => $user->isEquipe());

        // Segurança: a auditoria (quem mudou o quê na loja) é só para o Administrador
        Gate::define('relatorio-auditoria', fn (User $user) => $user->isAdmin());

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
