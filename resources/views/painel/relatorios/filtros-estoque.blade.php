<div class="col-md-3">
    <label class="form-label" for="categoria">Categoria</label>
    <select id="categoria" name="categoria" class="form-select">
        <option value="">Todas</option>
        @foreach ($categorias as $categoria)
            <option value="{{ $categoria->id }}" @selected($f['categoria'] === $categoria->id)>{{ $categoria->nome }}</option>
        @endforeach
    </select>
</div>
<div class="col-md-3">
    <label class="form-label" for="fornecedor">Fornecedor</label>
    <select id="fornecedor" name="fornecedor" class="form-select">
        <option value="">Todos</option>
        @foreach ($fornecedores as $fornecedor)
            <option value="{{ $fornecedor->id }}" @selected($f['fornecedor'] === $fornecedor->id)>{{ $fornecedor->nome }}</option>
        @endforeach
    </select>
</div>
<div class="col-md-2">
    <label class="form-label" for="status">Status</label>
    <select id="status" name="status" class="form-select">
        <option value="">Todos</option>
        <option value="ativo" @selected($f['status'] === 'ativo')>Ativos</option>
        <option value="inativo" @selected($f['status'] === 'inativo')>Inativos</option>
    </select>
</div>
<div class="col-md-2">
    <label class="form-label" for="tamanho">Tamanho</label>
    <select id="tamanho" name="tamanho" class="form-select">
        <option value="">Todos</option>
        @foreach ($tamanhos as $tamanho)
            <option value="{{ $tamanho }}" @selected($f['tamanho'] === $tamanho)>{{ $tamanho }}</option>
        @endforeach
    </select>
</div>
<div class="col-md-2">
    <label class="form-label" for="cor">Cor</label>
    <select id="cor" name="cor" class="form-select">
        <option value="">Todas</option>
        @foreach ($cores as $cor)
            <option value="{{ $cor }}" @selected($f['cor'] === $cor)>{{ $cor }}</option>
        @endforeach
    </select>
</div>
<div class="col-md-auto">
    <div class="form-check mb-2 mt-md-2">
        <input class="form-check-input" type="checkbox" name="baixo" value="1" id="baixo" @checked($f['baixo'])>
        <label class="form-check-label small" for="baixo">Só estoque baixo</label>
    </div>
</div>
