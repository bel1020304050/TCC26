<!DOCTYPE html>
<html lang="pt-BR">
<head>
    @include('partials.head')
</head>
<body>
    @php
        $usuario = auth()->user();

        // Menu lateral (Figura 1 da documentação). "fase" = módulo ainda não desenvolvido.
        // "perfis" = quem vê o item. O Funcionário não vê Usuários (seção 2.2).
        $menu = [
            ['titulo' => 'Início', 'icone' => 'house', 'rota' => 'painel.inicio', 'perfis' => ['admin', 'funcionario']],
            ['titulo' => 'Produtos', 'icone' => 'tag', 'rota' => 'painel.produtos.index', 'ativo' => 'painel.produtos.*', 'perfis' => ['admin', 'funcionario']],
            ['titulo' => 'Categorias', 'icone' => 'grid', 'rota' => 'painel.categorias.index', 'ativo' => 'painel.categorias.*', 'perfis' => ['admin', 'funcionario']],
            ['titulo' => 'Pedidos', 'icone' => 'receipt', 'fase' => 5, 'perfis' => ['admin', 'funcionario']],
            ['titulo' => 'Promoções', 'icone' => 'percent', 'fase' => 8, 'perfis' => ['admin', 'funcionario']],
            ['titulo' => 'Fornecedores', 'icone' => 'truck', 'rota' => 'painel.fornecedores.index', 'ativo' => 'painel.fornecedores.*', 'perfis' => ['admin', 'funcionario']],
            ['titulo' => 'Usuários', 'icone' => 'people', 'rota' => 'painel.usuarios.index', 'ativo' => 'painel.usuarios.*', 'perfis' => ['admin']],
            ['titulo' => 'Relatórios', 'icone' => 'bar-chart', 'fase' => 10, 'perfis' => ['admin', 'funcionario']],
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
                    @php
                        $ativo = isset($item['rota']) && request()->routeIs($item['ativo'] ?? $item['rota']);
                        $link = isset($item['rota']) ? route($item['rota']) : null;
                    @endphp
                    @if ($link)
                        <a class="nav-link {{ $ativo ? 'active' : '' }}" href="{{ $link }}">
                            <span><i class="bi bi-{{ $item['icone'] }} me-2"></i>{{ $item['titulo'] }}</span>
                            @isset($item['fase']) <span class="badge badge-fase">Fase {{ $item['fase'] }}</span> @endisset
                        </a>
                    @else
                        <span class="nav-link disabled" title="Disponível na Fase {{ $item['fase'] }}">
                            <span><i class="bi bi-{{ $item['icone'] }} me-2"></i>{{ $item['titulo'] }}</span>
                            <span class="badge badge-fase">Fase {{ $item['fase'] }}</span>
                        </span>
                    @endif
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
    @stack('scripts')
</body>
</html>
