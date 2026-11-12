@extends('layouts.app')
@section('title', $form->title)
@section('heading')<span x-data x-text="$store.builder ? $store.builder.title : @js($form->title)">{{ $form->title }}</span>@endsection
@section('crumb')<a href="{{ route('forms.index') }}">Formulários</a> / Construtor @endsection
@section('actions')
    <span class="save-state mr-2" x-data x-show="$store.builder" x-cloak>
        <template x-if="$store.builder && $store.builder.status === 'saving'"><span><i class="fa-solid fa-spinner fa-spin"></i> Salvando…</span></template>
        <template x-if="$store.builder && $store.builder.status === 'dirty'"><span><i class="fa-solid fa-circle has-text-warning is-size-8"></i> Alterações não salvas</span></template>
        <template x-if="$store.builder && $store.builder.status === 'saved'"><span><i class="fa-solid fa-cloud has-text-success"></i> Salvo <span x-text="$store.builder.savedAt"></span></span></template>
        <template x-if="$store.builder && $store.builder.status === 'error'"><span class="has-text-danger"><i class="fa-solid fa-triangle-exclamation"></i> <span x-text="$store.builder.error"></span></span></template>
    </span>
    @include('partials.form-actions')
@endsection

@section('fullwidth')
<div x-data="builder()" x-init="init()" @keydown.window.ctrl.s.prevent="save()" @keydown.window.meta.s.prevent="save()">
    <div class="fp-content" style="padding-bottom:0">@include('partials.form-header')</div>

    <div class="builder" style="margin-top:-1.6rem">
        {{-- Paleta --}}
        <aside class="builder-col builder-palette">
            <p class="is-size-7 has-text-muted mb-2">Clique ou arraste para adicionar</p>
            @foreach ($fieldTypes as $group => $types)
                <div class="palette-group">{{ $group }}</div>
                <div class="palette-list" data-group="{{ $group }}">
                    @foreach ($types as $type => $meta)
                        <button type="button" class="palette-item" data-type="{{ $type }}" @click="add('{{ $type }}')">
                            <i class="fa-solid {{ $meta['icon'] }}"></i> {{ $meta['label'] }}
                        </button>
                    @endforeach
                </div>
            @endforeach
        </aside>

        {{-- Canvas --}}
        <section class="builder-col builder-canvas" @click.self="selected = null">
            <div class="builder-head" @click="selected = null">
                <input class="title-input" x-model="meta.title" placeholder="Título do formulário" maxlength="200" aria-label="Título">
                <textarea x-model="meta.description" rows="2" placeholder="Descrição (opcional) — explique o objetivo e quanto tempo leva" aria-label="Descrição" x-init="$el.style.height = $el.scrollHeight + 'px'" @input="$el.style.height = 'auto'; $el.style.height = $el.scrollHeight + 'px'"></textarea>
            </div>

            <div x-ref="list" class="q-list">
                <template x-for="(f, i) in fields" :key="f.key">
                    <div class="q-card" :class="{ 'is-selected': selected === f.key }" @click="select(f.key)" :data-key="f.key">
                        <span class="handle" title="Arraste para reordenar"><i class="fa-solid fa-grip-vertical"></i></span>
                        <span class="q-type" :title="types[f.type]?.label"><i class="fa-solid" :class="types[f.type]?.icon"></i></span>
                        <div class="q-body">
                            <div class="q-label">
                                <span class="has-text-muted mr-1" x-show="f.type !== 'statement' && f.type !== 'hidden'" x-text="numberOf(f) + '.'"></span>
                                <span x-text="f.label || 'Sem título'"></span>
                                <span class="has-text-danger" x-show="f.required">*</span>
                            </div>
                            <div class="q-meta">
                                <span x-text="types[f.type]?.label"></span>
                                <span x-show="f.settings.logic && f.settings.logic.enabled" class="has-text-primary"><i class="fa-solid fa-code-branch"></i> Condicional</span>
                                <span x-show="f.type === 'hidden'" class="mono">?<span x-text="f.settings.param || f.key"></span>=</span>
                            </div>
                            <div class="q-preview" x-show="f.type === 'matrix' && (f.settings.matrix_rows || []).length">
                                <template x-for="r in (f.settings.matrix_rows || []).slice(0, 4)"><span x-text="r"></span></template>
                            </div>
                            <div class="q-preview" x-show="(f.options || []).length && f.type !== 'matrix'">
                                <template x-for="o in (f.options || []).slice(0, 6)"><span x-text="o"></span></template>
                                <span x-show="f.options.length > 6" x-text="'+' + (f.options.length - 6)"></span>
                            </div>
                        </div>
                        <div class="q-actions">
                            <button type="button" @click.stop="move(i, -1)" title="Subir" :disabled="i === 0"><i class="fa-solid fa-arrow-up"></i></button>
                            <button type="button" @click.stop="move(i, 1)" title="Descer" :disabled="i === fields.length - 1"><i class="fa-solid fa-arrow-down"></i></button>
                            <button type="button" @click.stop="duplicate(i)" title="Duplicar"><i class="fa-regular fa-copy"></i></button>
                            <button type="button" @click.stop="remove(i)" title="Excluir"><i class="fa-regular fa-trash-can"></i></button>
                        </div>
                    </div>
                </template>
            </div>
            <div class="drop-zone mt-2" x-show="fields.length === 0">
                <i class="fa-solid fa-hand-pointer is-size-4 mb-2"></i><br>
                Arraste um tipo de pergunta da esquerda para cá — ou clique nele.
            </div>
            <div class="has-text-centered mt-4" x-show="fields.length">
                <div class="dropdown is-up" :class="{ 'is-active': addMenu }" @click.outside="addMenu = false">
                    <button type="button" class="button is-small is-primary is-light" @click="addMenu = !addMenu"><i class="fa-solid fa-plus mr-2"></i> Adicionar pergunta</button>
                    <div class="dropdown-menu" style="min-width:240px"><div class="dropdown-content" style="max-height:340px;overflow:auto">
                        <template x-for="(t, k) in types" :key="k">
                            <a class="dropdown-item" @click="add(k); addMenu = false"><i class="fa-solid mr-2 has-text-primary" :class="t.icon"></i> <span x-text="t.label"></span></a>
                        </template>
                    </div></div>
                </div>
            </div>
        </section>

        {{-- Propriedades --}}
        <aside class="builder-col builder-props">
            <div class="tabs is-small is-fullwidth mb-3">
                <ul>
                    <li :class="{ 'is-active': panel === 'field' }"><a @click="panel = 'field'"><i class="fa-solid fa-sliders mr-1"></i> Pergunta</a></li>
                    <li :class="{ 'is-active': panel === 'design' }"><a @click="panel = 'design'"><i class="fa-solid fa-palette mr-1"></i> Design</a></li>
                </ul>
            </div>

            {{-- Painel da pergunta --}}
            <div x-show="panel === 'field'">
                <template x-if="!current">
                    <div class="has-text-centered has-text-muted py-6 is-size-7">
                        <i class="fa-solid fa-arrow-pointer is-size-4 mb-3"></i><br>Selecione uma pergunta para editar suas propriedades.
                    </div>
                </template>
                <template x-if="current">
                    <div>
                        <div class="field">
                            <label class="label is-small">Tipo</label>
                            <div class="select is-small is-fullwidth">
                                <select x-model="current.type" @change="retype(current)">
                                    <template x-for="(t, k) in types" :key="k"><option :value="k" x-text="t.label" :selected="k === current.type"></option></template>
                                </select>
                            </div>
                        </div>
                        <div class="field">
                            <label class="label is-small" x-text="current.type === 'statement' ? 'Título da seção' : 'Pergunta'"></label>
                            <textarea class="textarea is-small" rows="2" x-model="current.label" maxlength="500"></textarea>
                        </div>
                        <div class="field" x-show="current.type !== 'hidden'">
                            <label class="label is-small">Descrição / ajuda</label>
                            <textarea class="textarea is-small" rows="2" x-model="current.description" placeholder="Texto de apoio exibido abaixo da pergunta"></textarea>
                        </div>
                        <div class="field" x-show="['short_text','long_text','email','phone','url','number'].includes(current.type)">
                            <label class="label is-small">Placeholder</label>
                            <input class="input is-small" x-model="current.placeholder">
                        </div>
                        <div class="field" x-show="!['statement','hidden'].includes(current.type)">
                            <label class="checkbox is-size-7 has-text-weight-semibold"><input type="checkbox" x-model="current.required"> Resposta obrigatória</label>
                        </div>

                        {{-- Opções --}}
                        <template x-if="['radio','checkbox','select','ranking','matrix'].includes(current.type)">
                            <div>
                                <template x-if="current.type === 'matrix'">
                                    <div class="field">
                                        <div class="props-title">Linhas (afirmações)</div>
                                        <textarea class="textarea is-small" rows="5" :value="(current.settings.matrix_rows || []).join('\n')"
                                                  @change="current.settings.matrix_rows = $event.target.value.split('\n').map(s => s.trim()).filter(Boolean)" placeholder="Uma linha por afirmação"></textarea>
                                        <p class="help">Uma por linha. As colunas são as opções abaixo (ex.: Discordo, Neutro, Concordo).</p>
                                    </div>
                                </template>
                                <div class="props-title is-flex is-justify-content-space-between"><span x-text="current.type === 'matrix' ? 'Colunas (escala)' : (current.type === 'ranking' ? 'Itens para ordenar' : 'Opções')"></span><a class="is-size-8" @click="bulk = !bulk" x-text="bulk ? 'Editar uma a uma' : 'Colar em massa'"></a></div>
                                <template x-if="bulk">
                                    <div class="field">
                                        <textarea class="textarea is-small" rows="6" :value="current.options.join('\n')" @change="current.options = $event.target.value.split('\n').map(s => s.trim()).filter(Boolean)" placeholder="Uma opção por linha"></textarea>
                                        <p class="help">Uma opção por linha.</p>
                                    </div>
                                </template>
                                <template x-if="!bulk">
                                    <div>
                                        <template x-for="(o, oi) in current.options" :key="oi">
                                            <div class="option-row">
                                                <span class="tag is-light is-small mono" x-text="String.fromCharCode(65 + oi)"></span>
                                                <input class="input is-small" :value="o" @input="current.options[oi] = $event.target.value" @keydown.enter.prevent="current.options.splice(oi + 1, 0, ''); $nextTick(() => $el.parentElement.nextElementSibling?.querySelector('input')?.focus())">
                                                <button type="button" class="button is-small is-white" @click="if (oi > 0) { const x = current.options.splice(oi, 1)[0]; current.options.splice(oi - 1, 0, x) }" title="Subir"><i class="fa-solid fa-arrow-up is-size-8"></i></button>
                                                <button type="button" class="button is-small is-white" @click="current.options.splice(oi, 1)" title="Remover"><i class="fa-solid fa-xmark"></i></button>
                                            </div>
                                        </template>
                                        <button type="button" class="button is-small is-fullwidth is-light mt-1" @click="current.options.push('Opção ' + (current.options.length + 1))"><i class="fa-solid fa-plus mr-1"></i> Opção</button>
                                    </div>
                                </template>
                                <div class="field mt-3" x-show="['radio','checkbox'].includes(current.type)">
                                    <label class="checkbox is-size-7"><input type="checkbox" x-model="current.settings.allow_other"> Permitir resposta “Outro”</label>
                                </div>
                                <div class="field" x-show="current.type === 'checkbox'">
                                    <label class="label is-small">Máximo de seleções</label>
                                    <input type="number" min="0" class="input is-small" x-model.number="current.settings.max_choices" placeholder="Sem limite">
                                </div>
                            </div>
                        </template>

                        {{-- Configurações por tipo --}}
                        <template x-if="current.type === 'number'">
                            <div class="columns is-mobile is-variable is-1 mt-1">
                                <div class="column"><label class="label is-small">Mínimo</label><input type="number" class="input is-small" x-model="current.settings.min"></div>
                                <div class="column"><label class="label is-small">Máximo</label><input type="number" class="input is-small" x-model="current.settings.max"></div>
                            </div>
                        </template>
                        <template x-if="['short_text','long_text'].includes(current.type)">
                            <div class="field"><label class="label is-small">Limite de caracteres</label><input type="number" min="1" class="input is-small" x-model.number="current.settings.max_length" placeholder="Padrão"></div>
                        </template>
                        <template x-if="current.type === 'rating'">
                            <div class="field"><label class="label is-small">Número de estrelas</label>
                                <div class="select is-small is-fullwidth"><select x-model.number="current.settings.max"><option value="3">3</option><option value="5">5</option><option value="7">7</option><option value="10">10</option></select></div>
                            </div>
                        </template>
                        <template x-if="current.type === 'scale'">
                            <div>
                                <div class="columns is-mobile is-variable is-1 mt-1 mb-0">
                                    <div class="column"><label class="label is-small">De</label><div class="select is-small is-fullwidth"><select x-model.number="current.settings.min"><option value="0">0</option><option value="1">1</option></select></div></div>
                                    <div class="column"><label class="label is-small">Até</label><div class="select is-small is-fullwidth"><select x-model.number="current.settings.max"><template x-for="n in [3,4,5,6,7,8,9,10]"><option :value="n" x-text="n" :selected="n == current.settings.max"></option></template></select></div></div>
                                </div>
                                <div class="columns is-mobile is-variable is-1">
                                    <div class="column"><input class="input is-small" x-model="current.settings.label_min" placeholder="Rótulo início"></div>
                                    <div class="column"><input class="input is-small" x-model="current.settings.label_max" placeholder="Rótulo fim"></div>
                                </div>
                            </div>
                        </template>
                        <template x-if="current.type === 'nps'">
                            <div class="columns is-mobile is-variable is-1 mt-1">
                                <div class="column"><label class="label is-small">Rótulo 0</label><input class="input is-small" x-model="current.settings.label_min" placeholder="Nada provável"></div>
                                <div class="column"><label class="label is-small">Rótulo 10</label><input class="input is-small" x-model="current.settings.label_max" placeholder="Muito provável"></div>
                            </div>
                        </template>
                        <template x-if="current.type === 'consent'">
                            <div class="field"><label class="label is-small">Texto do consentimento</label><textarea class="textarea is-small" rows="3" x-model="current.settings.text" placeholder="Li e concordo com a política de privacidade."></textarea></div>
                        </template>
                        <template x-if="current.type === 'hidden'">
                            <div>
                                <div class="notification is-info is-light is-size-7 p-3">Campos ocultos capturam valores da URL — ex.: <span class="mono">?<span x-text="current.settings.param || current.key"></span>=valor</span>. Útil para ID do lead, vendedor, plano, afiliado…</div>
                                <div class="field"><label class="label is-small">Parâmetro da URL</label><input class="input is-small mono" x-model="current.settings.param" :placeholder="current.key"></div>
                                <div class="field"><label class="label is-small">Valor padrão</label><input class="input is-small" x-model="current.settings.default"></div>
                            </div>
                        </template>

                        <template x-if="current.type === 'cpf_cnpj'">
                            <div class="field"><label class="label is-small">Documento aceito</label>
                                <div class="select is-small is-fullwidth"><select x-model="current.settings.doc"><option value="both">CPF ou CNPJ</option><option value="cpf">Somente CPF</option><option value="cnpj">Somente CNPJ</option></select></div>
                                <p class="help">O número é validado (dígitos verificadores) e formatado automaticamente.</p>
                            </div>
                        </template>
                        <template x-if="current.type === 'address'">
                            <div class="field">
                                <p class="help mb-2">A pessoa digita o CEP e rua, bairro, cidade e UF são preenchidos automaticamente (ViaCEP).</p>
                                <label class="checkbox is-size-7"><input type="checkbox" x-model="current.settings.number_optional"> Número opcional</label>
                            </div>
                        </template>
                        <template x-if="current.type === 'currency'">
                            <div class="columns is-mobile is-variable is-1 mt-1">
                                <div class="column"><label class="label is-small">Mínimo (R$)</label><input type="number" min="0" class="input is-small" x-model="current.settings.min"></div>
                                <div class="column"><label class="label is-small">Máximo (R$)</label><input type="number" min="0" class="input is-small" x-model="current.settings.max"></div>
                            </div>
                        </template>
                        <template x-if="current.type === 'slider'">
                            <div>
                                <div class="columns is-mobile is-variable is-1 mt-1 mb-0">
                                    <div class="column"><label class="label is-small">De</label><input type="number" class="input is-small" x-model.number="current.settings.min"></div>
                                    <div class="column"><label class="label is-small">Até</label><input type="number" class="input is-small" x-model.number="current.settings.max"></div>
                                    <div class="column"><label class="label is-small">Passo</label><input type="number" min="0.01" class="input is-small" x-model.number="current.settings.step"></div>
                                </div>
                                <div class="field"><label class="label is-small">Unidade</label><input class="input is-small" x-model="current.settings.unit" placeholder="%, kg, anos…"></div>
                            </div>
                        </template>
                        <template x-if="current.type === 'file'">
                            <div>
                                <div class="field"><label class="label is-small">Tipos aceitos</label>
                                    <div class="select is-small is-fullwidth"><select x-model="current.settings.accept">
                                        @foreach (\App\Support\FieldTypes::ACCEPT as $k => $a)<option value="{{ $k }}">{{ $a['label'] }}</option>@endforeach
                                    </select></div>
                                </div>
                                <div class="columns is-mobile is-variable is-1">
                                    <div class="column"><label class="label is-small">Tamanho máx. (MB)</label><input type="number" min="1" max="{{ config('traceforms.upload_max_mb') }}" class="input is-small" x-model.number="current.settings.max_size"></div>
                                    <div class="column"><label class="label is-small">Nº de arquivos</label><input type="number" min="1" max="10" class="input is-small" x-model.number="current.settings.max_files"></div>
                                </div>
                                <p class="help">Arquivos ficam em armazenamento privado; só membros com acesso às respostas conseguem baixar.</p>
                            </div>
                        </template>
                        <template x-if="current.type === 'signature'">
                            <p class="help">A pessoa assina com o dedo ou o mouse. A assinatura é salva como imagem PNG junto da resposta.</p>
                        </template>

                        {{-- Lógica condicional --}}
                        <template x-if="!['hidden'].includes(current.type) && previousFields.length">
                            <div>
                                <div class="props-title"><i class="fa-solid fa-code-branch"></i> Lógica condicional</div>
                                <label class="checkbox is-size-7 mb-2"><input type="checkbox" :checked="current.settings.logic?.enabled" @change="toggleLogic($event.target.checked)"> Mostrar somente se…</label>
                                <template x-if="current.settings.logic?.enabled">
                                    <div>
                                        <div class="select is-small mb-2" x-show="current.settings.logic.rules.length > 1">
                                            <select x-model="current.settings.logic.match"><option value="all">todas as condições forem verdadeiras</option><option value="any">qualquer condição for verdadeira</option></select>
                                        </div>
                                        <template x-for="(r, ri) in current.settings.logic.rules" :key="ri">
                                            <div class="logic-rule">
                                                <div class="select is-small is-fullwidth mb-1"><select x-model="r.field">
                                                    <template x-for="pf in previousFields" :key="pf.key"><option :value="pf.key" x-text="pf.label" :selected="pf.key === r.field"></option></template>
                                                </select></div>
                                                <div class="is-flex" style="gap:.3rem">
                                                    <div class="select is-small"><select x-model="r.op">
                                                        @foreach ($operators as $op => $label)<option value="{{ $op }}">{{ $label }}</option>@endforeach
                                                    </select></div>
                                                    <template x-if="!['empty','not_empty'].includes(r.op)">
                                                        <div style="flex:1">
                                                            <template x-if="ruleOptions(r).length">
                                                                <div class="select is-small is-fullwidth"><select x-model="r.value"><option value="">—</option><template x-for="o in ruleOptions(r)"><option :value="o" x-text="o" :selected="o == r.value"></option></template></select></div>
                                                            </template>
                                                            <template x-if="!ruleOptions(r).length"><input class="input is-small" x-model="r.value" placeholder="valor"></template>
                                                        </div>
                                                    </template>
                                                    <button type="button" class="button is-small is-white" @click="current.settings.logic.rules.splice(ri, 1); if (!current.settings.logic.rules.length) current.settings.logic.enabled = false"><i class="fa-solid fa-xmark"></i></button>
                                                </div>
                                            </div>
                                        </template>
                                        <button type="button" class="button is-small is-light is-fullwidth" @click="current.settings.logic.rules.push({ field: previousFields[previousFields.length - 1].key, op: 'eq', value: '' })"><i class="fa-solid fa-plus mr-1"></i> Condição</button>
                                    </div>
                                </template>
                            </div>
                        </template>

                        <div class="props-title">Avançado</div>
                        <div class="field">
                            <label class="label is-small">Chave do campo <span class="has-text-muted has-text-weight-normal">(exportação / webhook)</span></label>
                            <input class="input is-small mono" :value="current.key" @change="rekey(current, $event.target.value)">
                        </div>
                    </div>
                </template>
            </div>

            {{-- Painel de design --}}
            <div x-show="panel === 'design'">
                <div class="props-title">Formato</div>
                <div class="layout-picker">
                    @foreach (\App\Models\Form::LAYOUT_LABELS as $key => [$label, $desc, $icon])
                        <button type="button" class="layout-opt" :class="{ 'is-selected': meta.layout === '{{ $key }}' }" @click="meta.layout = '{{ $key }}'" title="{{ $desc }}">
                            <span class="layout-mini is-{{ $key }}" aria-hidden="true"><i></i><b></b><b></b><b></b></span>
                            <strong>{{ $label }}</strong>
                            <small>{{ $desc }}</small>
                        </button>
                    @endforeach
                </div>

                <div class="props-title">Temas prontos</div>
                <div class="is-flex is-flex-wrap-wrap" style="gap:.4rem">
                    <template x-for="p in presets" :key="p.name">
                        <button type="button" class="swatch" :title="p.name" :style="`background: linear-gradient(135deg, ${p.background} 50%, ${p.primary} 50%)`" @click="Object.assign(meta.theme, p)"></button>
                    </template>
                </div>

                <div class="props-title">Cores</div>
                <template x-for="c in [['primary','Destaque / botões'],['background','Fundo'],['text','Texto']]" :key="c[0]">
                    <div class="field is-flex is-align-items-center" style="gap:.6rem">
                        <input type="color" class="swatch" x-model="meta.theme[c[0]]">
                        <div style="flex:1"><p class="is-size-7 has-text-weight-semibold" x-text="c[1]"></p><input class="input is-small mono" x-model="meta.theme[c[0]]" maxlength="7"></div>
                    </div>
                </template>

                <div class="props-title">Tipografia e forma</div>
                <div class="field">
                    <label class="label is-small">Fonte</label>
                    <div class="select is-small is-fullwidth"><select x-model="meta.theme.font">
                        @foreach (\App\Http\Controllers\FormController::FONTS as $font)<option>{{ $font }}</option>@endforeach
                    </select></div>
                </div>
                <div class="field">
                    <label class="label is-small">Arredondamento: <span x-text="meta.theme.radius + 'px'"></span></label>
                    <input type="range" min="0" max="28" x-model.number="meta.theme.radius" style="width:100%;accent-color:#0e7c66">
                </div>
                <div class="field">
                    <label class="label is-small">URL do logotipo</label>
                    <input class="input is-small" x-model="meta.theme.logo_url" placeholder="https://…/logo.png">
                </div>
                <div class="field" x-show="meta.layout === 'conversational'">
                    <label class="label is-small">Imagem de capa</label>
                    <input class="input is-small" x-model="meta.theme.cover_url" placeholder="https://…/capa.jpg">
                    <p class="help">Aparece ao lado do título na tela de abertura.</p>
                </div>
                <a :href="previewUrl" target="_blank" class="button is-small is-fullwidth mt-3" @click="save()"><i class="fa-solid fa-eye mr-2"></i> Pré-visualizar</a>
            </div>
        </aside>
    </div>
</div>
@endsection

@push('scripts')
<script src="{{ asset('vendor/Sortable.min.js') }}"></script>
<script>
document.addEventListener('alpine:init', () => Alpine.store('builder', { title: @json($form->title), status: 'saved', savedAt: '', error: '' }));
function builder() {
    const TYPES = @json(collect($fieldTypes)->collapse());
    const SAVE_URL = @json(route('forms.builder', $form));
    const rnd = () => Math.random().toString(36).slice(2, 8);
    const defaults = (type) => {
        const base = { key: 'q_' + rnd(), type, label: '', description: '', placeholder: '', required: false, options: [], settings: {} };
        const labels = { short_text: 'Qual é o seu nome?', long_text: 'Conte mais sobre isso', email: 'Qual é o seu e-mail?', phone: 'Qual é o seu telefone?',
            url: 'Qual é o seu site?', number: 'Quantos?', date: 'Qual a data?', radio: 'Escolha uma opção', checkbox: 'Selecione as opções',
            select: 'Selecione', yes_no: 'Você concorda?', rating: 'Como você avalia?', nps: 'De 0 a 10, quanto você nos recomendaria a um amigo?',
            scale: 'Qual o seu nível de satisfação?', consent: 'Consentimento', statement: 'Nova seção', hidden: 'Campo oculto',
            cpf_cnpj: 'Qual é o seu CPF ou CNPJ?', address: 'Qual é o seu endereço?', currency: 'Qual valor você pretende investir?',
            slider: 'De 0 a 100, quanto?', time: 'Qual o melhor horário?', ranking: 'Ordene por ordem de importância',
            matrix: 'Quanto você concorda com cada afirmação?', file: 'Envie o arquivo', signature: 'Assine aqui' };
        base.label = labels[type] || 'Nova pergunta';
        if (['radio', 'checkbox', 'select'].includes(type)) base.options = ['Opção 1', 'Opção 2', 'Opção 3'];
        if (type === 'rating') base.settings = { max: 5 };
        if (type === 'scale') base.settings = { min: 1, max: 5 };
        if (type === 'consent') { base.required = true; base.settings = { text: 'Li e concordo com a política de privacidade.' }; }
        if (type === 'hidden') { base.key = 'utm_ref_' + rnd().slice(0, 3); base.settings = { param: '' }; }
        if (type === 'ranking') base.options = ['Preço', 'Qualidade', 'Atendimento', 'Prazo'];
        if (type === 'matrix') { base.options = ['Discordo', 'Neutro', 'Concordo']; base.settings = { matrix_rows: ['Primeira afirmação', 'Segunda afirmação', 'Terceira afirmação'] }; }
        if (type === 'slider') base.settings = { min: 0, max: 100, step: 1, unit: '' };
        if (type === 'file') base.settings = { accept: 'any', max_size: 10, max_files: 1 };
        if (type === 'cpf_cnpj') base.settings = { doc: 'both' };
        return base;
    };

    return {
        types: TYPES,
        fields: @json($fieldsJson).map(f => ({ ...f, options: f.options || [], settings: Array.isArray(f.settings) ? {} : (f.settings || {}) })),
        meta: {
            title: @json($form->title), description: @json($form->description ?? ''), layout: @json($form->layout),
            theme: Object.assign(@json(\App\Models\Form::DEFAULT_THEME), @json($form->theme ?? [])),
        },
        presets: [
            { name: 'Petróleo', primary: '#0e7c66', background: '#eef3f1', text: '#14231f' },
            { name: 'Oceano', primary: '#1d5fa8', background: '#eef3f9', text: '#0f1f33' },
            { name: 'Ameixa', primary: '#6d3fc0', background: '#f4f1fa', text: '#21163a' },
            { name: 'Terracota', primary: '#b4472b', background: '#f8f1ee', text: '#2b1610' },
            { name: 'Mostarda', primary: '#e0a526', background: '#fbf7ec', text: '#2a2210' },
            { name: 'Grafite', primary: '#1f2328', background: '#f2f2f0', text: '#1f2328' },
            { name: 'Noite', primary: '#7ee0c3', background: '#10201c', text: '#e6f2ee' },
        ],
        selected: null, panel: 'field', bulk: false, addMenu: false,
        status: 'saved', savedAt: '', error: '', timer: null, ready: false,
        previewUrl: @json($form->publicUrl(['preview' => 1])),

        init() {
            this.$watch('fields', () => this.dirty());
            this.$watch('meta', () => { Alpine.store('builder').title = this.meta.title; this.dirty(); });
            this.$nextTick(() => { this.ready = true; this.sortable(); });
            window.addEventListener('beforeunload', (e) => { if (this.status === 'dirty' || this.status === 'saving') { e.preventDefault(); e.returnValue = ''; } });
        },

        sortable() {
            const list = this.$refs.list;
            Sortable.create(list, {
                handle: '.handle', draggable: '.q-card', animation: 160, ghostClass: 'sortable-ghost',
                group: { name: 'q', pull: false, put: ['palette'] },
                onAdd: (evt) => {
                    const type = evt.item.dataset.type; evt.item.remove();
                    const f = defaults(type);
                    this.fields.splice(evt.newDraggableIndex ?? evt.newIndex, 0, f);
                    this.select(f.key);
                },
                onEnd: (evt) => {
                    if (evt.oldDraggableIndex === evt.newDraggableIndex) return;
                    // Devolve o nó e deixa o Alpine reordenar a partir dos dados.
                    const nodes = Array.from(list.querySelectorAll('.q-card'));
                    list.insertBefore(evt.item, nodes[evt.oldDraggableIndex + (evt.oldDraggableIndex > evt.newDraggableIndex ? 1 : 0)] || null);
                    const [m] = this.fields.splice(evt.oldDraggableIndex, 1);
                    this.fields.splice(evt.newDraggableIndex, 0, m);
                },
            });
            document.querySelectorAll('.palette-list').forEach(el => Sortable.create(el, {
                group: { name: 'palette', pull: 'clone', put: false }, sort: false, draggable: '.palette-item', animation: 120,
            }));
        },

        get current() { return this.fields.find(f => f.key === this.selected) || null; },
        get previousFields() {
            const idx = this.fields.findIndex(f => f.key === this.selected);
            return this.fields.slice(0, Math.max(0, idx)).filter(f => !['statement'].includes(f.type));
        },
        numberOf(f) { return this.fields.filter(x => !['statement', 'hidden'].includes(x.type)).indexOf(f) + 1; },
        select(key) { this.selected = key; this.panel = 'field'; this.bulk = false; },

        add(type) {
            const f = defaults(type);
            const idx = this.fields.findIndex(x => x.key === this.selected);
            this.fields.splice(idx >= 0 ? idx + 1 : this.fields.length, 0, f);
            this.select(f.key);
            this.$nextTick(() => document.querySelector(`[data-key="${f.key}"]`)?.scrollIntoView({ behavior: 'smooth', block: 'center' }));
        },
        duplicate(i) {
            const copy = JSON.parse(JSON.stringify(this.fields[i]));
            copy.key = 'q_' + rnd(); copy.label += ' (cópia)';
            this.fields.splice(i + 1, 0, copy); this.select(copy.key);
        },
        remove(i) {
            const f = this.fields[i];
            const deps = this.fields.filter(x => (x.settings.logic?.rules || []).some(r => r.field === f.key));
            if (!confirm(deps.length ? `Esta pergunta é usada na lógica de ${deps.length} outra(s). Excluir mesmo assim?` : 'Excluir esta pergunta?')) return;
            this.fields.splice(i, 1);
            deps.forEach(d => { d.settings.logic.rules = d.settings.logic.rules.filter(r => r.field !== f.key); if (!d.settings.logic.rules.length) d.settings.logic.enabled = false; });
            if (this.selected === f.key) this.selected = null;
        },
        move(i, dir) {
            const j = i + dir; if (j < 0 || j >= this.fields.length) return;
            const [m] = this.fields.splice(i, 1); this.fields.splice(j, 0, m);
        },
        retype(f) {
            if (['radio', 'checkbox', 'select', 'ranking'].includes(f.type) && !f.options.length) f.options = ['Opção 1', 'Opção 2'];
            if (f.type === 'matrix') { if (!f.options.length) f.options = ['Discordo', 'Neutro', 'Concordo']; f.settings.matrix_rows ??= ['Primeira afirmação', 'Segunda afirmação']; }
            if (f.type === 'slider') { f.settings.min ??= 0; f.settings.max ??= 100; f.settings.step ??= 1; }
            if (f.type === 'file') { f.settings.accept ??= 'any'; f.settings.max_size ??= 10; f.settings.max_files ??= 1; }
            if (f.type === 'cpf_cnpj') f.settings.doc ??= 'both';
            if (f.type === 'rating' && !f.settings.max) f.settings.max = 5;
            if (f.type === 'scale') { f.settings.min ??= 1; f.settings.max ??= 5; }
            if (f.type === 'statement' || f.type === 'hidden') f.required = false;
        },
        rekey(f, val) {
            val = String(val).toLowerCase().replace(/[^a-z0-9_]/g, '_').slice(0, 40);
            if (!val || this.fields.some(x => x.key === val && x !== f)) { FP.toast('Chave inválida ou já usada.', 'error'); return; }
            this.fields.forEach(x => (x.settings.logic?.rules || []).forEach(r => { if (r.field === f.key) r.field = val; }));
            f.key = val; this.selected = val;
        },
        toggleLogic(on) {
            const f = this.current;
            if (!f.settings.logic) f.settings.logic = { enabled: false, match: 'all', rules: [] };
            f.settings.logic.enabled = on;
            if (on && !f.settings.logic.rules.length) f.settings.logic.rules.push({ field: this.previousFields[this.previousFields.length - 1].key, op: 'eq', value: '' });
        },
        ruleOptions(r) {
            const f = this.fields.find(x => x.key === r.field);
            if (!f || ['contains', 'not_contains', 'gt', 'lt'].includes(r.op)) return [];
            if (['radio', 'checkbox', 'select'].includes(f.type)) return f.options;
            if (f.type === 'cpf_cnpj' || f.type === 'address' || f.type === 'file' || f.type === 'signature' || f.type === 'matrix' || f.type === 'ranking') return [];
            if (f.type === 'yes_no') return ['Sim', 'Não'];
            if (f.type === 'nps') return ['0','1','2','3','4','5','6','7','8','9','10'];
            if (f.type === 'rating') return Array.from({ length: Number(f.settings.max || 5) }, (_, i) => String(i + 1));
            if (f.type === 'consent') return ['Aceito'];
            return [];
        },

        dirty() {
            if (!this.ready) return;
            this.setStatus('dirty');
            clearTimeout(this.timer);
            this.timer = setTimeout(() => this.save(), 1200);
        },
        setStatus(s, extra) { this.status = s; Object.assign(Alpine.store('builder'), { status: s }, extra || {}); },
        async save() {
            clearTimeout(this.timer);
            this.setStatus('saving');
            try {
                const res = await FP.api(SAVE_URL, 'PUT', { ...this.meta, fields: this.fields });
                this.setStatus('saved', { savedAt: 'às ' + res.saved_at });
            } catch (e) {
                const first = e.data && e.data.errors ? Object.values(e.data.errors)[0][0] : e.message;
                this.setStatus('error', { error: first });
                const m = e.data && e.data.errors && Object.keys(e.data.errors)[0].match(/^fields\.(\d+)/);
                if (m && this.fields[m[1]]) this.select(this.fields[m[1]].key);
            }
        },
    };
}
</script>
@endpush
