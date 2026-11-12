{{--
    Botão + janela de confirmação para Inativar/Reativar (exclusão lógica).
    Uso: <x-modal-status :id="'cat-'.$c->id" :ativo="$c->isAtivo()" :acao-inativar="route(...)" :acao-reativar="route(...)"
                         titulo="Vestidos" :bloqueio="null">texto explicativo</x-modal-status>

    "bloqueio": quando já se sabe que a inativação não é permitida (ex.: categoria com produtos ativos),
    passe o motivo. O modal mostra só o aviso e o botão "Entendi", sem enviar nada ao servidor.
    O servidor continua conferindo a regra de qualquer forma.

    Regra: inativar e reativar pedem um motivo (mínimo de 10 caracteres), que vai para a auditoria.
    O botão de confirmar só libera depois de escrever (public/js/motivo.js), e o servidor confere de novo.
--}}
@props(['id', 'ativo', 'acaoInativar', 'acaoReativar', 'titulo', 'tipo' => 'registro', 'bloqueio' => null])

@php $bloqueado = $ativo && $bloqueio; @endphp

@php
    $modalId = 'modal-status-'.$id;
    // Se o servidor recusou o motivo, a página volta com esta mesma janela aberta e a mensagem de erro
    $reabrir = old('_modal') === $modalId;
@endphp

<button type="button" class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#{{ $modalId }}"
        @if ($bloqueado) title="{{ $bloqueio }}" @endif>
    {{ $ativo ? 'Inativar' : 'Reativar' }}
</button>

<div class="modal fade" id="{{ $modalId }}" tabindex="-1" aria-labelledby="{{ $modalId }}-titulo" aria-hidden="true" @if ($reabrir) data-reabrir @endif>
    <div class="modal-dialog modal-dialog-centered">
        {{-- Bloqueado: só uma caixa de aviso, sem formulário para enviar --}}
        @if ($bloqueado)
        <div class="modal-content">
        @else
        <form method="POST" action="{{ $ativo ? $acaoInativar : $acaoReativar }}" class="modal-content">
            @csrf
            @method('PATCH')
            <input type="hidden" name="_modal" value="{{ $modalId }}">
        @endif
            <div class="modal-header border-0 pb-0">
                <h2 class="modal-title h5" id="{{ $modalId }}-titulo">
                    {{ $bloqueado ? 'Não é possível inativar' : ($ativo ? 'Inativar' : 'Reativar').' '.$tipo.'?' }}
                </h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>
            <div class="modal-body text-start">
                <p class="fw-semibold mb-2">{{ $titulo }}</p>
                @if ($bloqueado)
                    <div class="alert alert-warning mb-0">
                        <i class="bi bi-exclamation-triangle me-1"></i> {{ $bloqueio }}
                    </div>
                @else
                    <div class="text-muted small">{{ $slot }}</div>
                    <label for="{{ $modalId }}-motivo" class="form-label mt-3 mb-1">Motivo <span class="obrigatorio">*</span></label>
                    <textarea id="{{ $modalId }}-motivo" name="motivo" rows="2" maxlength="500" required data-motivo
                              class="form-control @if ($reabrir) @error('motivo') is-invalid @enderror @endif"
                              placeholder="Ex.: Peça descontinuada pelo fornecedor">{{ $reabrir ? old('motivo') : '' }}</textarea>
                    @if ($reabrir)
                        @error('motivo') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    @endif
                    <div class="form-text d-flex justify-content-between">
                        <span>Fica registrado na auditoria, com a data e o usuário responsável.</span>
                        <span data-motivo-contador>0/10</span>
                    </div>
                @endif
            </div>
            <div class="modal-footer border-0 pt-0">
                @if ($bloqueado)
                    {{-- Sem botão de confirmar: a ação não é permitida --}}
                    <button type="button" class="btn btn-mm px-4" data-bs-dismiss="modal">Entendi</button>
                @elseif ($ativo)
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-danger" data-motivo-botao disabled>Confirmar inativação</button>
                @else
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-mm" data-motivo-botao disabled>Confirmar reativação</button>
                @endif
            </div>
        @if ($bloqueado)
        </div>
        @else
        </form>
        @endif
    </div>
</div>
