{{-- Selo colorido com o status do pedido (cores definidas em Pedido::CORES) --}}
@props(['status'])
<span class="badge rounded-pill {{ \App\Models\Pedido::CORES[$status] ?? 'text-bg-secondary' }}">
    {{ \App\Models\Pedido::STATUS[$status] ?? $status }}
</span>
