<div class="col-md-4">
    <label class="form-label" for="de">Período</label>
    <div class="input-group">
        <input type="date" id="de" name="de" value="{{ $f['de']->toDateString() }}" class="form-control" aria-label="Data inicial">
        <input type="date" name="ate" value="{{ $f['ate']->toDateString() }}" class="form-control" aria-label="Data final">
    </div>
</div>
<div class="col-md">
    <label class="form-label" for="status">Status</label>
    <select id="status" name="status" class="form-select">
        <option value="">Todos</option>
        @foreach ($statusOpcoes as $valor => $rotulo)
            <option value="{{ $valor }}" @selected($f['status'] === $valor)>{{ $rotulo }}</option>
        @endforeach
    </select>
</div>
<div class="col-md">
    <label class="form-label" for="forma">Forma de pagamento</label>
    <select id="forma" name="forma" class="form-select">
        <option value="">Todas</option>
        @foreach ($formas as $valor => $rotulo)
            <option value="{{ $valor }}" @selected($f['forma'] === $valor)>{{ $rotulo }}</option>
        @endforeach
    </select>
</div>
