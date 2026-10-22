<?php

namespace App\Providers;

use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Evita erro de tamanho de índice em versões antigas de MySQL/MariaDB (XAMPP)
        Schema::defaultStringLength(191);

        // Paginação com o visual do Bootstrap 5
        Paginator::useBootstrapFive();
    }
}
