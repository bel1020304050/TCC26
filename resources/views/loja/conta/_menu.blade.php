{{-- Menu lateral de "Minha conta" do cliente (Figura 11) --}}
<div class="mm-card py-2 mm-menu-conta">
    <nav class="nav flex-column">
        <a class="nav-link {{ request()->routeIs('loja.conta*') ? 'active' : '' }}" href="{{ route('loja.conta') }}"><i class="bi bi-person me-2"></i>Meus dados</a>
        <a class="nav-link {{ request()->routeIs('loja.pedidos.*') ? 'active' : '' }}" href="{{ route('loja.pedidos.index') }}"><i class="bi bi-receipt me-2"></i>Meus pedidos</a>
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="nav-link btn btn-link text-start w-100"><i class="bi bi-box-arrow-right me-2"></i>Sair</button>
        </form>
    </nav>
</div>
