@extends('layouts.loja')

@section('titulo', 'Minha conta')

@section('conteudo')
{{-- "Meus dados" do cliente e a janela de "Encerrar minha conta" --}}
<h1 class="h3 mb-3">Minha conta</h1>

<div class="row g-3">
    <div class="col-lg-3">@include('loja.conta._menu')</div>

    <div class="col-lg-9 d-flex flex-column gap-3">
        <div class="mm-card p-4">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h2 class="h6 fw-semibold mb-0">Dados pessoais</h2>
                <a href="{{ route('loja.conta.edit') }}" class="btn btn-sm btn-outline-secondary">Editar dados</a>
            </div>
            <div class="row g-3">
                <div class="col-md-4"><div class="text-muted small">Nome</div>{{ $cliente->name }}</div>
                <div class="col-md-4">
                    <div class="text-muted small">E-mail</div>
                    {{ $cliente->email }}
                    @if ($cliente->hasVerifiedEmail())
                        <div class="small text-success"><i class="bi bi-patch-check"></i> Confirmado</div>
                    @else
                        <form method="POST" action="{{ route('verification.send') }}" class="small">
                            @csrf
                            <span class="text-danger">Não confirmado</span> ·
                            <button type="submit" class="btn btn-link btn-sm p-0 align-baseline">Reenviar link</button>
                        </form>
                    @endif
                </div>
                <div class="col-md-4"><div class="text-muted small">Telefone</div>{{ $cliente->telefone ?: '—' }}</div>
                <div class="col-md-4">
                    <div class="text-muted small">CPF</div>
                    @if ($cliente->cpf)
                        {{ $cliente->cpfFormatado() }}
                    @else
                        {{-- Contas antigas, criadas antes do CPF ser obrigatório --}}
                        <span class="text-danger">Não informado</span>
                        <a href="{{ route('loja.conta.edit') }}" class="small ms-1">Informar</a>
                    @endif
                </div>
            </div>
        </div>

        <div class="mm-card p-4">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h2 class="h6 fw-semibold mb-0">Endereço de entrega</h2>
                <a href="{{ route('loja.conta.edit') }}#endereco" class="btn btn-sm btn-outline-secondary">Editar endereço</a>
            </div>
            <div class="row g-3">
                <div class="col-md-4"><div class="text-muted small">Endereço</div>{{ $cliente->rua }}, {{ $cliente->numero }}@if ($cliente->complemento) – {{ $cliente->complemento }}@endif</div>
                <div class="col-md-4"><div class="text-muted small">Bairro / Cidade / UF</div>{{ $cliente->bairro }} · {{ $cliente->cidade }}/{{ $cliente->uf }}</div>
                <div class="col-md-4"><div class="text-muted small">CEP</div>{{ $cliente->cep }}</div>
            </div>
        </div>

        <div class="mm-card p-4 d-flex flex-wrap align-items-center gap-2">
            <div class="me-auto">
                <h2 class="h6 fw-semibold mb-0">Segurança</h2>
                <div class="text-muted small">Cliente desde {{ $cliente->created_at->format('d/m/Y') }} · a senha nunca é exibida</div>
            </div>
            <a href="{{ route('loja.conta.edit') }}#senha" class="btn btn-sm btn-outline-secondary">Alterar senha</a>
            <button type="button" class="btn btn-sm btn-link text-danger text-decoration-none fw-semibold" data-bs-toggle="modal" data-bs-target="#modal-encerrar">Encerrar minha conta</button>
        </div>
    </div>
</div>

{{-- Janela de encerrar a conta: aviso de que não pode ser desfeito + senha atual para confirmar --}}
<div class="modal fade" id="modal-encerrar" tabindex="-1" aria-labelledby="modal-encerrar-titulo" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form method="POST" action="{{ route('loja.conta.destroy') }}" class="modal-content" novalidate>
            @csrf @method('DELETE')
            <div class="modal-header border-0 pb-0">
                <h2 class="modal-title h5" id="modal-encerrar-titulo">Encerrar minha conta?</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-warning small">
                    <strong>Esta ação não pode ser desfeita.</strong><br>
                    Seu login será bloqueado e seus dados pessoais (nome, e-mail, CPF, telefone e endereço) serão removidos do cadastro.
                </div>
                <p class="small">Seus pedidos continuam registrados para fins fiscais, com o endereço de entrega que já estava salvo neles. Depois, o mesmo e-mail poderá ser usado em uma conta nova.</p>
                @if ($emAndamento)
                    <div class="alert alert-danger small py-2">
                        Você tem {{ $emAndamento }} pedido(s) em andamento. <a href="{{ route('loja.pedidos.index') }}" class="alert-link">Ver meus pedidos</a>
                    </div>
                @endif
                <label for="senha_encerrar" class="form-label">Senha atual <span class="obrigatorio">*</span></label>
                <input type="password" id="senha_encerrar" name="senha_encerrar" autocomplete="current-password"
                       class="form-control {{ $errors->encerrar->has('senha_encerrar') ? 'is-invalid' : '' }}">
                @if ($errors->encerrar->has('senha_encerrar'))
                    <div class="invalid-feedback">{{ $errors->encerrar->first('senha_encerrar') }}</div>
                @else
                    <div class="form-text">Confirme sua senha para continuar.</div>
                @endif
                <p class="small text-muted mt-3 mb-0">Não é possível encerrar com pedidos Aguardando Pagamento, Pago, Em Separação ou Enviado.</p>
            </div>
            <div class="modal-footer border-0 pt-0">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Voltar</button>
                <button type="submit" class="btn btn-danger">Encerrar conta</button>
            </div>
        </form>
    </div>
</div>
@endsection

@if ($errors->encerrar->any())
    @push('scripts')
        {{-- Senha errada: reabre a janela já com a mensagem --}}
        <script>new bootstrap.Modal(document.getElementById('modal-encerrar')).show();</script>
    @endpush
@endif
