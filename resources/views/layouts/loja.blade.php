<!DOCTYPE html>
<html lang="pt-BR">
<head>
    @include('partials.head')
</head>
<body>
    {{-- Topo da loja (área pública e do cliente) --}}
    <nav class="navbar navbar-expand-md navbar-dark mm-topo">
        <div class="container">
            <a class="navbar-brand" href="{{ route('loja.inicio') }}">Mary Modas</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#menuLoja" aria-label="Abrir menu">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="menuLoja">
                {{-- Pesquisa rápida: leva para a vitrine filtrada (story 3.2) --}}
                <form class="d-flex flex-grow-1 my-2 my-md-0 mx-md-4" role="search" method="GET" action="{{ route('loja.inicio') }}">
                    <input class="form-control form-control-sm mm-busca" type="search" name="busca" value="{{ request('busca') }}"
                           placeholder="Pesquisar produtos..." aria-label="Pesquisar produtos">
                </form>
                <ul class="navbar-nav ms-auto align-items-md-center">
                    <li class="nav-item"><a class="nav-link" href="{{ route('loja.inicio') }}">Início</a></li>
                    {{-- Carrinho: só clientes têm (3.30). Visitante é levado ao login. Equipe não compra pelo painel. --}}
                    @if (! auth()->check() || auth()->user()->isCliente())
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('loja.carrinho') ? 'fw-semibold' : '' }}" href="{{ route('loja.carrinho') }}">
                                <i class="bi bi-bag"></i> Carrinho
                                @if (auth()->check())
                                    <span class="badge rounded-pill text-bg-light">{{ \App\Models\Carrinho::quantidadeDoCliente(auth()->user()) }}</span>
                                @endif
                            </a>
                        </li>
                    @endif
                    @auth
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                <i class="bi bi-person-circle"></i> {{ \Illuminate\Support\Str::of(auth()->user()->name)->before(' ') }}
                            </a>
                            <ul class="dropdown-menu dropdown-menu-end">
                                @if (auth()->user()->isEquipe())
                                    <li><a class="dropdown-item" href="{{ route('painel.inicio') }}">Painel administrativo</a></li>
                                    <li><a class="dropdown-item" href="{{ route('painel.minha-conta') }}">Minha conta</a></li>
                                @else
                                    <li><a class="dropdown-item" href="{{ route('loja.pedidos.index') }}">Meus pedidos</a></li>
                                    <li><span class="dropdown-item-text text-muted small">Minha conta (Fase 9)</span></li>
                                @endif
                                <li><hr class="dropdown-divider"></li>
                                <li>
                                    <form method="POST" action="{{ route('logout') }}">
                                        @csrf
                                        <button class="dropdown-item" type="submit">Sair</button>
                                    </form>
                                </li>
                            </ul>
                        </li>
                    @else
                        <li class="nav-item"><a class="nav-link" href="{{ route('login') }}">Entrar</a></li>
                        <li class="nav-item"><a class="btn btn-sm btn-light ms-md-2" href="{{ route('register') }}">Criar conta</a></li>
                    @endauth
                </ul>
            </div>
        </div>
    </nav>

    <main class="container py-4">
        @include('partials.alertas')
        @yield('conteudo')
    </main>

    <footer class="text-center text-muted small py-4">
        © {{ date('Y') }} Mary Modas · Umuarama/PR
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    @stack('scripts')
</body>
</html>
