<!DOCTYPE html>
<html lang="pt-BR">
<head>
    @include('partials.head')
</head>
<body class="d-flex align-items-center justify-content-center" style="min-height: 100vh;">
    <div class="mm-card p-5 text-center" style="max-width: 460px;">
        <h1 class="h4">Página não encontrada</h1>
        <p class="text-muted">O endereço acessado não existe.</p>
        <a href="{{ route('loja.inicio') }}" class="btn btn-mm">Ir para a loja</a>
    </div>
</body>
</html>
