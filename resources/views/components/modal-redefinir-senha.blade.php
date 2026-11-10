{{--
    Botão + janela de confirmação para "Redefinir senha" de alguém da equipe.
    Uso: <x-modal-redefinir-senha :usuario="$usuario" />
--}}
@props(['usuario'])

@php $modalId = 'modal-senha-'.$usuario->id; @endphp

<button type="button" class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#{{ $modalId }}">
    <i class="bi bi-key"></i> Redefinir senha
</button>

<div class="modal fade" id="{{ $modalId }}" tabindex="-1" aria-labelledby="{{ $modalId }}-titulo" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form method="POST" action="{{ route('painel.usuarios.redefinir-senha', $usuario) }}" class="modal-content">
            @csrf
            <div class="modal-header border-0 pb-0">
                <h2 class="modal-title h5" id="{{ $modalId }}-titulo">Redefinir senha?</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>
            <div class="modal-body text-start">
                <p class="fw-semibold mb-2">{{ $usuario->name }} · {{ $usuario->perfilLabel() }}</p>
                <div class="text-muted small">
                    O sistema vai gerar uma <strong>senha provisória</strong>, mostrada uma única vez para você passar à pessoa.
                    A senha atual deixa de funcionar na hora, e no próximo login a pessoa cria a própria senha.
                </div>
            </div>
            <div class="modal-footer border-0 pt-0">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="submit" class="btn btn-mm">Gerar senha provisória</button>
            </div>
        </form>
    </div>
</div>
