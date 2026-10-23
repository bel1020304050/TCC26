{{--
    Botão + janela de confirmação para Inativar/Reativar (exclusão lógica).
    Uso: <x-modal-status :id="'cat-'.$c->id" :ativo="$c->isAtivo()" :acao-inativar="route(...)" :acao-reativar="route(...)"
                         titulo="Vestidos">texto explicativo</x-modal-status>
--}}
@props(['id', 'ativo', 'acaoInativar', 'acaoReativar', 'titulo', 'tipo' => 'registro'])

@php $modalId = 'modal-status-'.$id; @endphp

<button type="button" class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#{{ $modalId }}">
    {{ $ativo ? 'Inativar' : 'Reativar' }}
</button>

<div class="modal fade" id="{{ $modalId }}" tabindex="-1" aria-labelledby="{{ $modalId }}-titulo" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form method="POST" action="{{ $ativo ? $acaoInativar : $acaoReativar }}" class="modal-content">
            @csrf
            @method('PATCH')
            <div class="modal-header border-0 pb-0">
                <h2 class="modal-title h5" id="{{ $modalId }}-titulo">{{ $ativo ? 'Inativar' : 'Reativar' }} {{ $tipo }}?</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>
            <div class="modal-body text-start">
                <p class="fw-semibold mb-2">{{ $titulo }}</p>
                <div class="text-muted small">{{ $slot }}</div>
                <p class="text-muted small mb-0 mt-2">Serão registrados a data e o usuário responsável.</p>
            </div>
            <div class="modal-footer border-0 pt-0">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                @if ($ativo)
                    <button type="submit" class="btn btn-danger">Confirmar inativação</button>
                @else
                    <button type="submit" class="btn btn-mm">Confirmar reativação</button>
                @endif
            </div>
        </form>
    </div>
</div>
