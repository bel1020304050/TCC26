{{-- Selo de status: verde para Ativo, vermelho para Inativo --}}
@props(['status'])
<span class="badge rounded-pill {{ $status === 'ativo' ? 'text-bg-success' : 'text-bg-danger' }}">
    {{ \App\Support\Status::label($status) }}
</span>
