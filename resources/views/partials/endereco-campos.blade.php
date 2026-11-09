{{-- Campos de endereço com preenchimento pelo CEP (ViaCEP). Usado em "Criar conta", "Editar meus dados" e no Fornecedor.
     $dono = registro com os dados atuais (cliente ou fornecedor); null no cadastro. Precisa de public/js/viacep.js na página. --}}
                <div class="row align-items-start">
                    <div class="col-sm-6 mb-3">
                        <label for="cep" class="form-label">CEP <span class="obrigatorio">*</span></label>
                        <input type="text" id="cep" name="cep" value="{{ old('cep', $dono?->cep) }}" inputmode="numeric" maxlength="9" placeholder="00000-000"
                               class="form-control @error('cep') is-invalid @enderror" required
                               data-viacep data-viacep-timeout="{{ config('mary.viacep_timeout_ms') }}">
                        @error('cep')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                        {{-- Mensagens do JavaScript (incompleto, buscando, não encontrado) --}}
                        <div id="cep-status" class="form-text" @error('cep') hidden @enderror>Ao sair do campo, Rua, Bairro, Cidade e UF são preenchidos pela ViaCEP.</div>
                    </div>
                    <div class="col-sm-6 mb-3 pt-sm-4 mt-sm-2">
                        <button type="button" id="buscar-cep" class="btn btn-outline-secondary">Buscar CEP</button>
                    </div>
                </div>
                <div class="row">
                    <x-campo class="col-sm-8" name="rua" :valor="$dono?->rua" label="Rua/Avenida" obrigatorio maxlength="120" />
                    <x-campo class="col-sm-4" name="numero" :valor="$dono?->numero" label="Número" obrigatorio maxlength="10" />
                    <x-campo class="col-sm-4" name="complemento" :valor="$dono?->complemento" label="Complemento" opcional maxlength="60" />
                    <x-campo class="col-sm-8" name="bairro" :valor="$dono?->bairro" label="Bairro" obrigatorio maxlength="80" />
                    <x-campo class="col-sm-8" name="cidade" :valor="$dono?->cidade" label="Cidade" obrigatorio maxlength="80" />
                    <div class="col-sm-4 mb-3">
                        <label for="uf" class="form-label">UF <span class="obrigatorio">*</span></label>
                        <select id="uf" name="uf" class="form-select @error('uf') is-invalid @enderror" required>
                            <option value=""></option>
                            @foreach (\App\Models\User::UFS as $uf)
                                <option value="{{ $uf }}" @selected(old('uf', $dono?->uf) === $uf)>{{ $uf }}</option>
                            @endforeach
                        </select>
                        @error('uf') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                </div>
