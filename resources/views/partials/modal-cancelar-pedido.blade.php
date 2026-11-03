{{-- 3.21 – Botão + janela de confirmação para cancelar um pedido.
     $acao = rota do formulário; $motivoObrigatorio = true para a equipe (opcional para o cliente). --}}
@php
    $modalId = 'modal-cancelar-'.$pedido->id;
    $pago = in_array($pedido->status, [\App\Models\Pedido::PAGO, \App\Models\Pedido::EM_SEPARACAO], true);
@endphp
<button type="button" class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#{{ $modalId }}">Cancelar</button>

<div class="modal fade" id="{{ $modalId }}" tabindex="-1" aria-labelledby="{{ $modalId }}-titulo" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form method="POST" action="{{ $acao }}" class="modal-content text-start" onsubmit="this.querySelector('button[type=submit]').disabled = true;">
            @csrf @method('PATCH')
            <div class="modal-header border-0 pb-0">
                <h2 class="modal-title h5" id="{{ $modalId }}-titulo">Cancelar pedido {{ $pedido->numero() }}?</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>
            <div class="modal-body">
                <p class="mb-2">Total: {{ $pedido->valor('total') }} · {{ $pedido->statusLabel() }}</p>
                @if ($pago)
                    <div class="alert alert-warning small py-2">
                        O pagamento será <strong>estornado</strong> e os itens voltarão ao estoque.
                    </div>
                @else
                    <p class="text-muted small">O pedido ficará como Cancelado no histórico e o carrinho volta a ficar disponível para alterações.</p>
                @endif
                <label class="form-label" for="motivo-{{ $pedido->id }}">
                    Motivo @if ($motivoObrigatorio) <span class="obrigatorio">*</span> @else <span class="opcional">(opcional)</span> @endif
                </label>
                <textarea id="motivo-{{ $pedido->id }}" name="motivo" rows="2" maxlength="255" class="form-control"
                          @if ($motivoObrigatorio) required minlength="5" @endif
                          placeholder="{{ $motivoObrigatorio ? 'Ex.: cliente pediu o cancelamento por telefone' : 'Ex.: desisti da compra' }}"></textarea>
                <p class="text-muted small mb-0 mt-2">Serão registrados o motivo, a data e o responsável.</p>
            </div>
            <div class="modal-footer border-0 pt-0">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Voltar</button>
                <button type="submit" class="btn btn-danger">Confirmar cancelamento</button>
            </div>
        </form>
    </div>
</div>
