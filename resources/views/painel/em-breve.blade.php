@extends('layouts.painel')

@section('titulo', $modulo)
@section('trilha', 'Painel › '.$modulo)

@section('conteudo')
<div class="mm-card p-5 text-center">
    <i class="bi bi-cone-striped fs-1 text-muted"></i>
    <h2 class="h5 mt-3">Módulo em construção</h2>
    <p class="text-muted mb-0">O módulo <strong>{{ $modulo }}</strong> será desenvolvido na <strong>Fase {{ $fase }}</strong>.</p>
</div>
@endsection
