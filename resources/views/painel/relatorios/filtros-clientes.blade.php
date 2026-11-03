<div class="col-md-4">
    <label class="form-label" for="de">Período de cadastro</label>
    <div class="input-group">
        <input type="date" id="de" name="de" value="{{ $f['de']->toDateString() }}" class="form-control" aria-label="Data inicial">
        <input type="date" name="ate" value="{{ $f['ate']->toDateString() }}" class="form-control" aria-label="Data final">
    </div>
</div>
<div class="col-md">
    <label class="form-label" for="compras">Compras</label>
    <select id="compras" name="compras" class="form-select">
        <option value="">Todos os clientes</option>
        <option value="com" @selected($f['compras'] === 'com')>Com compras pagas</option>
        <option value="sem" @selected($f['compras'] === 'sem')>Sem compras pagas</option>
    </select>
</div>
<div class="col-md">
    <label class="form-label" for="ordem">Ordenar por</label>
    <select id="ordem" name="ordem" class="form-select">
        <option value="cadastro" @selected($f['ordem'] === 'cadastro')>Data de cadastro</option>
        <option value="compras" @selected($f['ordem'] === 'compras')>Volume de compras</option>
    </select>
</div>
