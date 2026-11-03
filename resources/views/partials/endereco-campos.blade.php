{{-- Campos de endereço com ViaCEP (stories 3.9 e 3.11). $usuario = dados atuais (null no cadastro). --}}
                <div class="row align-items-start">
                    <div class="col-sm-6 mb-3">
                        <label for="cep" class="form-label">CEP <span class="obrigatorio">*</span></label>
                        <input type="text" id="cep" name="cep" value="{{ old('cep', $usuario?->cep) }}" inputmode="numeric" maxlength="9" placeholder="00000-000"
                               class="form-control @error('cep') is-invalid @enderror" required
                               data-viacep data-viacep-timeout="{{ config('mary.viacep_timeout_ms') }}">
                        @error('cep')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @else
                            <div id="cep-status" class="form-text">Ao sair do campo, Rua, Bairro, Cidade e UF são preenchidos pela ViaCEP.</div>
                        @enderror
                    </div>
                    <div class="col-sm-6 mb-3 pt-sm-4 mt-sm-2">
                        <button type="button" id="buscar-cep" class="btn btn-outline-secondary">Buscar CEP</button>
                    </div>
                </div>
                <div class="row">
                    <x-campo class="col-sm-8" name="rua" :valor="$usuario?->rua" label="Rua/Avenida" obrigatorio />
                    <x-campo class="col-sm-4" name="numero" :valor="$usuario?->numero" label="Número" obrigatorio />
                    <x-campo class="col-sm-4" name="complemento" :valor="$usuario?->complemento" label="Complemento" opcional />
                    <x-campo class="col-sm-8" name="bairro" :valor="$usuario?->bairro" label="Bairro" obrigatorio />
                    <x-campo class="col-sm-8" name="cidade" :valor="$usuario?->cidade" label="Cidade" obrigatorio />
                    <div class="col-sm-4 mb-3">
                        <label for="uf" class="form-label">UF <span class="obrigatorio">*</span></label>
                        <select id="uf" name="uf" class="form-select @error('uf') is-invalid @enderror" required>
                            <option value=""></option>
                            @foreach (\App\Models\User::UFS as $uf)
                                <option value="{{ $uf }}" @selected(old('uf', $usuario?->uf) === $uf)>{{ $uf }}</option>
                            @endforeach
                        </select>
                        @error('uf') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                </div>
