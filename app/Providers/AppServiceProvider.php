<?php

namespace App\Providers;

use App\Models\User;
use App\Pagamentos\PagamentoSimulado;
use App\Pagamentos\ProvedorPagamento;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Provedor de pagamento (processo 3.31). No MVP é simulado.
        // Integração futura: trocar por \App\Pagamentos\MercadoPagoProvedor (seção 4.1 da documentação).
        $this->app->bind(ProvedorPagamento::class, PagamentoSimulado::class);
    }

    public function boot(): void
    {
        // Relatórios (3.32–3.34): Vendas e Clientes só o Administrador; Estoque também o Funcionário
        Gate::define('relatorio-vendas', fn (User $user) => $user->isAdmin());
        Gate::define('relatorio-clientes', fn (User $user) => $user->isAdmin());
        Gate::define('relatorio-estoque', fn (User $user) => $user->isEquipe());

        // Evita erro de tamanho de índice em versões antigas de MySQL/MariaDB (XAMPP)
        Schema::defaultStringLength(191);

        // Paginação com o visual do Bootstrap 5
        Paginator::useBootstrapFive();

        // URLs em português nos CRUDs: /categorias/novo e /categorias/1/editar
        Route::resourceVerbs(['create' => 'novo', 'edit' => 'editar']);
    }
}
