<!DOCTYPE html>
<html lang="pt-BR">
<head>
    @include('partials.head')
</head>
<body class="d-flex align-items-center justify-content-center" style="min-height: 100vh;">
    <div class="mm-card p-5 text-center" style="max-width: 460px;">
        <i class="bi bi-shield-lock fs-1" style="color: var(--mm-rosa);"></i>
        <h1 class="h4 mt-3">Acesso negado</h1>
        <p class="text-muted">{{ $exception->getMessage() ?: 'Você não tem permissão para acessar esta página.' }}</p>
        <a href="{{ auth()->check() ? route(auth()->user()->homeRoute()) : route('loja.inicio') }}" class="btn btn-mm">Voltar</a>
    </div>
</body>
</html>
