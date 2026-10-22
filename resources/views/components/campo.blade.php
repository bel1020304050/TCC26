{{--
    Campo de formulário padrão (label + input + erro de validação).
    Uso: <x-campo name="email" label="E-mail" type="email" obrigatorio />
--}}
@props([
    'name',
    'label',
    'type' => 'text',
    'obrigatorio' => false,
    'opcional' => false,
    'dica' => null,
    'valor' => null,
])

<div {{ $attributes->only('class')->merge(['class' => 'mb-3']) }}>
    <label for="{{ $name }}" class="form-label">
        {{ $label }}
        @if ($obrigatorio) <span class="obrigatorio">*</span> @endif
        @if ($opcional) <span class="opcional">(opcional)</span> @endif
    </label>
    <input
        type="{{ $type }}"
        id="{{ $name }}"
        name="{{ $name }}"
        value="{{ $type === 'password' ? '' : old($name, $valor) }}"
        {{ $attributes->except('class')->merge(['class' => 'form-control'.($errors->has($name) ? ' is-invalid' : '')]) }}
        @if ($obrigatorio) required @endif
    >
    @error($name)
        <div class="invalid-feedback">{{ $message }}</div>
    @else
        @if ($dica) <div class="form-text">{{ $dica }}</div> @endif
    @enderror
</div>
