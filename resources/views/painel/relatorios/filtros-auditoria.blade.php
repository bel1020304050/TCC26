<div class="col-md-4">
    <label class="form-label" for="de">Período</label>
    <div class="input-group">
        <input type="date" id="de" name="de" value="{{ $f['de']->toDateString() }}" class="form-control" aria-label="Data inicial">
        <input type="date" name="ate" value="{{ $f['ate']->toDateString() }}" class="form-control" aria-label="Data final">
    </div>
</div>
<div class="col-md">
    <label class="form-label" for="usuario">Quem</label>
    <select id="usuario" name="usuario" class="form-select">
        <option value="">Todos</option>
        <optgroup label="Equipe">
            @foreach ($equipe as $pessoa)
                <option value="{{ $pessoa->id }}" @selected($f['usuario'] === (string) $pessoa->id)>{{ $pessoa->name }} ({{ $pessoa->perfilLabel() }})</option>
            @endforeach
        </optgroup>
        <option value="clientes" @selected($f['usuario'] === 'clientes')>Clientes</option>
        <option value="sistema" @selected($f['usuario'] === 'sistema')>Sistema (automático)</option>
    </select>
</div>
<div class="col-md">
    <label class="form-label" for="acao">Ação</label>
    <select id="acao" name="acao" class="form-select">
        <option value="">Todas</option>
        @foreach ($acoes as $valor => $rotulo)
            <option value="{{ $valor }}" @selected($f['acao'] === $valor)>{{ $rotulo }}</option>
        @endforeach
    </select>
</div>
<div class="col-md">
    <label class="form-label" for="area">Área</label>
    <select id="area" name="area" class="form-select">
        <option value="">Todas</option>
        @foreach ($areas as $valor => $rotulo)
            <option value="{{ $valor }}" @selected($f['area'] === $valor)>{{ $rotulo }}</option>
        @endforeach
    </select>
</div>
