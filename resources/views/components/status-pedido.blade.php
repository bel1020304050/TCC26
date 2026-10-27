{{-- Selo colorido do status do pedido (lista padronizada da seção 2.3) --}}
@props(['status'])
<span class="badge rounded-pill {{ \App\Models\Pedido::CORES[$status] ?? 'text-bg-secondary' }}">
    {{ \App\Models\Pedido::STATUS[$status] ?? $status }}
</span>
