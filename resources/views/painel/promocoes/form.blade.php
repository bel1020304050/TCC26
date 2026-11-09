@extends('layouts.painel')

@php
    $editando = $promocao->exists;
    $usada = $usada ?? false;
@endphp

@section('titulo', $editando ? 'Editar promoção' : 'Nova promoção')
@section('trilha', 'Painel › Promoções › '.($editando ? $promocao->nome.' › Editar' : 'Nova promoção'))

@section('conteudo')
{{-- Formulário de promoção (o mesmo para cadastrar e editar) --}}
@php
    $modo = old('modo', $promocao->modo ?? 'automatica');
    $aplicacao = old('aplicacao', $promocao->aplicacao ?? 'geral');
    $valor = old('valor', $promocao->valor !== null ? rtrim(rtrim(number_format((float) $promocao->valor, 2, ',', ''), '0'), ',') : '');
@endphp

@if ($usada)
    <div class="alert alert-warning small"><i class="bi bi-lock"></i> Esta promoção já foi usada em pedido(s), por isso <strong>modo e código não podem ser alterados</strong>. Descontos já registrados não mudam.</div>
@endif

<div class="mm-card p-4">
    <form method="POST" action="{{ $editando ? route('painel.promocoes.update', $promocao) : route('painel.promocoes.store') }}" novalidate>
        @csrf
        @if ($editando) @method('PUT') @endif

        <div class="row g-3">
            <div class="col-md-4">
                <x-campo name="nome" label="Nome" obrigatorio maxlength="80" :valor="$promocao->nome" placeholder="Ex.: Semana Mary" />
            </div>
            <div class="col-md-4">
                <label for="modo" class="form-label">Modo <span class="obrigatorio">*</span></label>
                <select id="modo" name="modo" class="form-select @error('modo') is-invalid @enderror" @disabled($usada)>
                    <option value="automatica" @selected($modo === 'automatica')>Automática</option>
                    <option value="cupom" @selected($modo === 'cupom')>Cupom</option>
                </select>
                <div class="form-text">Automática vale sozinha; Cupom só com o código.</div>
                @error('modo') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
            <div class="col-md-4">
                <x-campo name="codigo" label="Código do cupom" maxlength="30" :valor="$promocao->codigo" placeholder="MARY20"
                         class="campo-cupom" :disabled="$usada" dica="Obrigatório e único no modo Cupom; vazio no modo Automática" />
            </div>

            <div class="col-md-4">
                <label for="tipo" class="form-label">Tipo de desconto <span class="obrigatorio">*</span></label>
                <select id="tipo" name="tipo" class="form-select @error('tipo') is-invalid @enderror">
                    <option value="percentual" @selected(old('tipo', $promocao->tipo) === 'percentual')>Percentual (%)</option>
                    <option value="valor" @selected(old('tipo', $promocao->tipo) === 'valor')>Valor fixo (R$)</option>
                </select>
                @error('tipo') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
            <div class="col-md-4">
                <x-campo name="valor" label="Valor do desconto" obrigatorio inputmode="decimal" :valor="$valor" placeholder="20"
                         dica="Percentual: de 1% a 50% · Valor fixo: maior que zero" />
            </div>
            <div class="col-md-4">
                @if ($editando)
                    <label class="form-label">Status</label>
                    <input class="form-control" value="{{ $promocao->isAtivo() ? 'Ativa' : 'Inativa' }} · {{ $promocao->situacaoLabel() }}" disabled>
                    <div class="form-text">Alterado só pela ação Inativar/Reativar.</div>
                @else
                    <label for="status" class="form-label">Status manual <span class="obrigatorio">*</span></label>
                    <select id="status" name="status" class="form-select @error('status') is-invalid @enderror">
                        <option value="ativo" @selected(old('status', 'ativo') === 'ativo')>Ativa</option>
                        <option value="inativo" @selected(old('status') === 'inativo')>Inativa</option>
                    </select>
                    @error('status') <div class="invalid-feedback">{{ $message }}</div> @enderror
                @endif
            </div>

            <div class="col-md-4">
                <x-campo name="data_inicio" label="Data inicial" type="date" obrigatorio :valor="$promocao->data_inicio?->toDateString()" />
            </div>
            <div class="col-md-4">
                <x-campo name="data_fim" label="Data final" type="date" obrigatorio :valor="$promocao->data_fim?->toDateString()" dica="Não pode ser anterior à data inicial" />
            </div>
            <div class="col-md-4"></div>

            <div class="col-md-4">
                <label for="aplicacao" class="form-label">Aplicação <span class="obrigatorio">*</span></label>
                <select id="aplicacao" name="aplicacao" class="form-select @error('aplicacao') is-invalid @enderror">
                    <option value="geral" @selected($aplicacao === 'geral')>Geral (todos os produtos)</option>
                    <option value="categoria" @selected($aplicacao === 'categoria')>Categoria</option>
                    <option value="produto" @selected($aplicacao === 'produto')>Produto</option>
                </select>
                @error('aplicacao') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
            <div class="col-md-4 alvo alvo-categoria">
                <label for="categoria_id" class="form-label">Categoria <span class="obrigatorio">*</span></label>
                <select id="categoria_id" name="categoria_id" class="form-select @error('categoria_id') is-invalid @enderror">
                    <option value="">Selecione...</option>
                    @foreach ($categorias as $categoria)
                        <option value="{{ $categoria->id }}" @selected((int) old('categoria_id', $promocao->categoria_id) === $categoria->id)>{{ $categoria->nome }}</option>
                    @endforeach
                </select>
                @error('categoria_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
            <div class="col-md-4 alvo alvo-produto">
                <label for="produto_id" class="form-label">Produto <span class="obrigatorio">*</span></label>
                <select id="produto_id" name="produto_id" class="form-select @error('produto_id') is-invalid @enderror">
                    <option value="">Selecione...</option>
                    @foreach ($produtos as $produto)
                        <option value="{{ $produto->id }}" @selected((int) old('produto_id', $promocao->produto_id) === $produto->id)>{{ $produto->nome }} ({{ $produto->variacao() }})</option>
                    @endforeach
                </select>
                @error('produto_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
            <div class="col-md-4 alvo alvo-geral">
                <label class="form-label">Alvo</label>
                <input class="form-control" value="Todos os produtos" disabled>
            </div>
        </div>

        <p class="text-muted small mt-3 mb-0">Agendada, Vigente e Expirada são calculadas automaticamente pelas datas. Descontos não se somam: no carrinho vale só o maior.</p>

        <div class="d-flex justify-content-end gap-2 mt-3">
            <a href="{{ route('painel.promocoes.index') }}" class="btn btn-outline-secondary">Cancelar</a>
            <button type="submit" class="btn btn-mm">{{ $editando ? 'Salvar alterações' : 'Cadastrar' }}</button>
        </div>
    </form>
</div>
@endsection

@push('scripts')
<script>
    // Mostra só o campo de alvo da aplicação escolhida e libera o código só no modo Cupom
    (function () {
        const aplicacao = document.getElementById('aplicacao');
        const modo = document.getElementById('modo');
        const codigo = document.getElementById('codigo');
        const usada = @json($usada);
        function atualizar() {
            document.querySelectorAll('.alvo').forEach(el => el.classList.toggle('d-none', !el.classList.contains('alvo-' + aplicacao.value)));
            if (!usada) {
                codigo.disabled = modo.value !== 'cupom';
                if (codigo.disabled) codigo.value = '';
            }
        }
        aplicacao.addEventListener('change', atualizar);
        modo.addEventListener('change', atualizar);
        atualizar();
    })();
</script>
@endpush
