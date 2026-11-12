<!DOCTYPE html>
<html lang="pt-BR">
<head>
    @include('partials.head')
</head>
<body>
    @php
        $usuario = auth()->user();

        // Menu lateral. "perfis" = quem vê o item; "ativo" = rotas que deixam o item destacado.
        // Segurança: esconder o item é só visual; cada rota também confere a permissão no servidor.
        $menu = [
            ['titulo' => 'Início', 'icone' => 'house', 'rota' => 'painel.inicio', 'perfis' => ['admin', 'gerente', 'atendente']],
            ['titulo' => 'Produtos', 'icone' => 'tag', 'rota' => 'painel.produtos.index', 'ativo' => 'painel.produtos.*', 'perfis' => ['admin', 'gerente', 'atendente']],
            ['titulo' => 'Categorias', 'icone' => 'grid', 'rota' => 'painel.categorias.index', 'ativo' => 'painel.categorias.*', 'perfis' => ['admin', 'gerente', 'atendente']],
            ['titulo' => 'Pedidos', 'icone' => 'receipt', 'rota' => 'painel.pedidos.index', 'ativo' => 'painel.pedidos.*', 'perfis' => ['admin', 'gerente', 'atendente']],
            ['titulo' => 'Promoções', 'icone' => 'percent', 'rota' => 'painel.promocoes.index', 'ativo' => 'painel.promocoes.*', 'perfis' => ['admin', 'gerente', 'atendente']],
            ['titulo' => 'Fornecedores', 'icone' => 'truck', 'rota' => 'painel.fornecedores.index', 'ativo' => 'painel.fornecedores.*', 'perfis' => ['admin', 'gerente', 'atendente']],
            ['titulo' => 'Usuários', 'icone' => 'people', 'rota' => 'painel.usuarios.index', 'ativo' => 'painel.usuarios.*', 'perfis' => ['admin', 'gerente']],
            ['titulo' => 'Relatórios', 'icone' => 'bar-chart', 'rota' => 'painel.relatorios.index', 'ativo' => 'painel.relatorios.*', 'perfis' => ['admin', 'gerente', 'atendente']],
        ];
    @endphp

    {{-- Topo do painel --}}
    <nav class="navbar navbar-dark mm-topo px-3">
        <a class="navbar-brand" href="{{ route('painel.inicio') }}">Mary Modas<small>Painel administrativo</small></a>
        <div class="d-flex align-items-center gap-3">
            <a class="nav-link text-white small" href="{{ route('loja.inicio') }}" title="Ver a loja"><i class="bi bi-shop"></i> Ver loja</a>
            <div class="text-end text-white lh-sm">
                <div class="fw-semibold small">{{ $usuario->name }}</div>
                <div class="small opacity-75">{{ $usuario->perfilLabel() }}</div>
            </div>
        </div>
    </nav>

    <div class="d-flex">
        <aside class="mm-lateral py-3 d-none d-md-block">
            <nav class="nav flex-column">
                @foreach ($menu as $item)
                    @continue(! in_array($usuario->perfil, $item['perfis'], true))
                    <a class="nav-link {{ request()->routeIs($item['ativo'] ?? $item['rota']) ? 'active' : '' }}" href="{{ route($item['rota']) }}">
                        <span><i class="bi bi-{{ $item['icone'] }} me-2"></i>{{ $item['titulo'] }}</span>
                    </a>
                @endforeach
                <hr class="mx-3">
                <a class="nav-link {{ request()->routeIs('painel.minha-conta*') ? 'active' : '' }}" href="{{ route('painel.minha-conta') }}">
                    <span><i class="bi bi-person me-2"></i>Minha conta</span>
                </a>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="nav-link border-0 bg-transparent w-100 text-start">
                        <span><i class="bi bi-box-arrow-right me-2"></i>Sair</span>
                    </button>
                </form>
            </nav>
        </aside>

        <main class="flex-grow-1 p-4 main-painel">
            <div class="mm-crumb mb-1">@yield('trilha', 'Início')</div>
            <h1 class="h4 mb-3">@yield('titulo', 'Início')</h1>
            @include('partials.alertas')
            @yield('conteudo')
        </main>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="{{ asset('js/email.js') }}"></script>
    <script src="{{ asset('js/motivo.js') }}"></script>
    @stack('scripts')
</body>
</html>
