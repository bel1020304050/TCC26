{{--
    Botão + janela de confirmação para Inativar/Reativar (exclusão lógica).
    Uso: <x-modal-status :id="'cat-'.$c->id" :ativo="$c->isAtivo()" :acao-inativar="route(...)" :acao-reativar="route(...)"
                         titulo="Vestidos" :bloqueio="null">texto explicativo</x-modal-status>

    "bloqueio": quando já se sabe que a inativação não é permitida (ex.: categoria com produtos ativos),
    passe o motivo. O modal mostra só o aviso e o botão "Entendi", sem enviar nada ao servidor.
    O servidor continua conferindo a regra de qualquer forma.
--}}
@props(['id', 'ativo', 'acaoInativar', 'acaoReativar', 'titulo', 'tipo' => 'registro', 'bloqueio' => null])

@php $bloqueado = $ativo && $bloqueio; @endphp

@php $modalId = 'modal-status-'.$id; @endphp

<button type="button" class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#{{ $modalId }}"
        @if ($bloqueado) title="{{ $bloqueio }}" @endif>
    {{ $ativo ? 'Inativar' : 'Reativar' }}
</button>

<div class="modal fade" id="{{ $modalId }}" tabindex="-1" aria-labelledby="{{ $modalId }}-titulo" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        {{-- Bloqueado: só uma caixa de aviso, sem formulário para enviar --}}
        @if ($bloqueado)
        <div class="modal-content">
        @else
        <form method="POST" action="{{ $ativo ? $acaoInativar : $acaoReativar }}" class="modal-content">
            @csrf
            @method('PATCH')
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
                    <p class="text-muted small mb-0 mt-2">Serão registrados a data e o usuário responsável.</p>
                @endif
            </div>
            <div class="modal-footer border-0 pt-0">
                @if ($bloqueado)
                    {{-- Sem botão de confirmar: a ação não é permitida --}}
                    <button type="button" class="btn btn-mm px-4" data-bs-dismiss="modal">Entendi</button>
                @elseif ($ativo)
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-danger">Confirmar inativação</button>
                @else
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-mm">Confirmar reativação</button>
                @endif
            </div>
        @if ($bloqueado)
        </div>
        @else
        </form>
        @endif
    </div>
</div>
