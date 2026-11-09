{{-- Botão + janela "Atualizar status". Regra: só o próximo status do fluxo é oferecido --}}
@php
    $modalId = 'modal-status-pedido-'.$pedido->id;
    $proximo = $pedido->proximoStatusManual();
@endphp
<button type="button" class="btn btn-sm btn-mm" data-bs-toggle="modal" data-bs-target="#{{ $modalId }}">Atualizar status</button>

<div class="modal fade" id="{{ $modalId }}" tabindex="-1" aria-labelledby="{{ $modalId }}-titulo" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form method="POST" action="{{ route('painel.pedidos.status', $pedido) }}" class="modal-content text-start" onsubmit="this.querySelector('button[type=submit]').disabled = true;">
            @csrf @method('PATCH')
            <div class="modal-header border-0 pb-0">
                <h2 class="modal-title h5" id="{{ $modalId }}-titulo">Atualizar status · Pedido {{ $pedido->numero() }}</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>
            <div class="modal-body">
                <label class="form-label">Status atual</label>
                <input class="form-control mb-3" value="{{ $pedido->statusLabel() }}" disabled>
                <label class="form-label" for="status-{{ $pedido->id }}">Novo status <span class="obrigatorio">*</span></label>
                <select id="status-{{ $pedido->id }}" name="status" class="form-select">
                    <option value="{{ $proximo }}">{{ \App\Models\Pedido::STATUS[$proximo] }}</option>
                </select>
                <div class="form-text">Só o próximo status do fluxo é oferecido.</div>
                <p class="small text-muted mt-3 mb-0">
                    Fluxo: Aguardando Pagamento → Pago → Em Separação → Enviado → Entregue.
                    “Pago” é definido automaticamente pela confirmação do pagamento. A alteração é registrada com data e responsável.
                </p>
            </div>
            <div class="modal-footer border-0 pt-0">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="submit" class="btn btn-mm">Atualizar status</button>
            </div>
        </form>
    </div>
</div>
