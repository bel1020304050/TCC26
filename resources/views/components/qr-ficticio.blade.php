{{-- QR Code ILUSTRATIVO do PIX simulado: desenhado a partir do código, mas não é lido por bancos --}}
@props(['codigo', 'tamanho' => 168])
@php
    $n = 25; // módulos por lado
    $bits = '';
    for ($i = 0; strlen($bits) < $n * $n; $i++) {
        foreach (str_split(md5($codigo.$i)) as $hex) {
            $bits .= str_pad(base_convert($hex, 16, 2), 4, '0', STR_PAD_LEFT);
        }
    }
    // Os 3 quadrados de canto, como num QR de verdade
    $canto = fn ($x, $y, $ox, $oy) => $x >= $ox && $x < $ox + 7 && $y >= $oy && $y < $oy + 7;
    $preto = function ($x, $y) use ($canto, $bits, $n) {
        foreach ([[0, 0], [$n - 7, 0], [0, $n - 7]] as [$ox, $oy]) {
            if ($canto($x, $y, $ox, $oy)) {
                $dx = $x - $ox; $dy = $y - $oy;
                return $dx === 0 || $dx === 6 || $dy === 0 || $dy === 6 || ($dx >= 2 && $dx <= 4 && $dy >= 2 && $dy <= 4);
            }
        }
        return $bits[$y * $n + $x] === '1';
    };
@endphp
<svg width="{{ $tamanho }}" height="{{ $tamanho }}" viewBox="-2 -2 {{ $n + 4 }} {{ $n + 4 }}" role="img" aria-label="QR Code fictício do PIX simulado" shape-rendering="crispEdges">
    <rect x="-2" y="-2" width="{{ $n + 4 }}" height="{{ $n + 4 }}" fill="#fff"/>
    @for ($y = 0; $y < $n; $y++)
        @for ($x = 0; $x < $n; $x++)
            @if ($preto($x, $y)) <rect x="{{ $x }}" y="{{ $y }}" width="1" height="1" fill="#222"/> @endif
        @endfor
    @endfor
</svg>
