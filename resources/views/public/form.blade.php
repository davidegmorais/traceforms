@php
    $t = array_merge(\App\Models\Form::DEFAULT_THEME, $form->theme ?? []);
    $layout = $form->layout === 'immersive' ? 'conversational' : (in_array($form->layout, \App\Models\Form::LAYOUTS, true) ? $form->layout : 'classic');
    $conv = in_array($layout, ['conversational', 'chat'], true);       // uma pergunta por vez
    $immersive = $layout === 'conversational';                          // tela cheia, uma pergunta por tela
    $chat = $layout === 'chat';                                         // balões de conversa
    $split = $layout === 'classic' && ! $embedded;                      // painel lateral fixo
    $s = fn ($k) => $form->setting($k);
    // Cor de texto legível sobre a cor principal (painel lateral e botões).
    $hex = ltrim($t['primary'], '#');
    [$r, $g, $b] = array_map(fn ($c) => hexdec($c) / 255, str_split(strlen($hex) === 6 ? $hex : '0e7c66', 2));
    $lum = 0.2126 * $r + 0.7152 * $g + 0.0722 * $b;
    $onPrimary = $lum > 0.6 ? '#14231f' : '#ffffff';
    $needs = fn ($type) => $form->fields->contains('type', $type);
    $localFonts = ['Instrument Sans', 'Bricolage Grotesque'];
@endphp
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title>{{ $form->title }}</title>
    <meta name="description" content="{{ \Illuminate\Support\Str::limit(strip_tags($form->description), 150) }}">
    <meta property="og:title" content="{{ $form->title }}">
    <meta property="og:description" content="{{ \Illuminate\Support\Str::limit(strip_tags($form->description), 150) }}">
    <meta name="robots" content="{{ $form->isPublished() ? 'index,follow' : 'noindex' }}">
    <meta name="theme-color" content="{{ $t['primary'] }}">
    <link rel="stylesheet" href="{{ asset('vendor/fonts/fonts.css') }}">
    @unless (in_array($t['font'], $localFonts, true))
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family={{ urlencode($t['font']) }}:wght@400;500;600;700&display=swap" rel="stylesheet">
    @endunless
    <link rel="stylesheet" href="{{ asset('vendor/fontawesome/css/all.min.css') }}">
    <link rel="stylesheet" href="{{ asset('css/public-form.css') }}?v=2">
    <style>
        :root {
            --tf-primary: {{ $t['primary'] }};
            --tf-on-primary: {{ $onPrimary }};
            --tf-bg: {{ $transparent ? 'transparent' : $t['background'] }};
            --tf-text: {{ $t['text'] }};
            --tf-radius: {{ (int) $t['radius'] }}px;
            --tf-font: '{{ $t['font'] }}', 'Instrument Sans', system-ui, -apple-system, 'Segoe UI', sans-serif;
            --tf-display: {{ $t['font'] === 'Instrument Sans' ? "'Bricolage Grotesque'," : '' }} var(--tf-font);
        }
    </style>
    @if (! $preview && ! $closed)
        @if ($gtm = $s('gtm_id'))
            <script>(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src='https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);})(window,document,'script','dataLayer',@json($gtm));</script>
        @endif
        @if ($ga = $s('ga4_id'))
            <script async src="https://www.googletagmanager.com/gtag/js?id={{ urlencode($ga) }}"></script>
            <script>window.dataLayer=window.dataLayer||[];function gtag(){dataLayer.push(arguments);}gtag('js',new Date());gtag('config',@json($ga));</script>
        @endif
        @if ($px = $s('meta_pixel_id'))
            <script>!function(f,b,e,v,n,t,s){if(f.fbq)return;n=f.fbq=function(){n.callMethod?n.callMethod.apply(n,arguments):n.queue.push(arguments)};if(!f._fbq)f._fbq=n;n.push=n;n.loaded=!0;n.version='2.0';n.queue=[];t=b.createElement(e);t.async=!0;t.src=v;s=b.getElementsByTagName(e)[0];s.parentNode.insertBefore(t,s)}(window,document,'script','https://connect.facebook.net/en_US/fbevents.js');fbq('init',@json($px));fbq('track','PageView');</script>
        @endif
    @endif
</head>
<body class="tf is-{{ $layout }} {{ $immersive ? 'is-immersive' : '' }} {{ $conv ? 'is-conv' : 'is-classic' }} {{ $split ? 'is-split' : 'is-stacked' }} {{ $embedded ? 'is-embedded' : '' }}">

@if ($preview)
    <div class="tf-preview"><span><i class="fa-solid fa-eye"></i> Pré-visualização: nada do que você enviar aqui é registrado.</span><a href="{{ route('forms.edit', $form) }}">Voltar ao construtor</a></div>
@endif

@if ($closed)
    <main class="tf-closed">
        <div class="tf-mark"><i class="fa-solid {{ $closed === 'already' ? 'fa-check' : 'fa-lock' }}"></i></div>
        <h1>{{ $closed === 'already' ? 'Você já respondeu' : ($closed === 'draft' ? 'Formulário indisponível' : 'Formulário encerrado') }}</h1>
        <p>{{ $closed === 'already' ? 'Recebemos sua resposta anteriormente. Obrigado!' : ($closed === 'draft' ? 'Este formulário ainda não foi publicado.' : $s('closed_message')) }}</p>
    </main>
@else
<div class="tf-app" x-data="tfForm()" x-init="init()" x-cloak :class="{ 'go-back': dir < 0 }">

    @if ($chat)
        <header class="tf-chat-head">
            <div class="tf-chat-head-inner">
                @if ($t['logo_url'])<img src="{{ $t['logo_url'] }}" alt="" class="tf-bot-avatar">@else<span class="tf-bot-avatar">{{ mb_strtoupper(mb_substr($form->account->name ?? $form->title, 0, 1)) }}</span>@endif
                <div class="tf-chat-who">
                    <strong>{{ $form->account->name ?? $form->title }}</strong>
                    <span x-text="done ? 'Conversa concluída' : (typing ? 'digitando…' : 'Responde em cerca de ' + estimate)"></span>
                </div>
                @if ($s('show_progress'))<span class="tf-chat-progress" x-show="!done"><span :style="`width:${progress}%`"></span></span>@endif
            </div>
        </header>
    @elseif ($conv)
        <div class="tf-topbar" x-show="!done">
            <div class="tf-topbar-fill" :style="`width:${progress}%`"></div>
        </div>
    @endif

    {{-- Painel de abertura: lateral fixa (clássico desktop) ou faixa no topo --}}
    @unless ($conv)
        <aside class="tf-intro">
            <div class="tf-intro-inner">
                @if ($t['logo_url'])<img src="{{ $t['logo_url'] }}" alt="" class="tf-logo">@endif
                <h1 class="tf-title">{{ $form->title }}</h1>
                @if ($form->description)<p class="tf-desc">{!! nl2br(e($form->description)) !!}</p>@endif
                @if ($s('show_progress'))
                    <div class="tf-trail" x-show="!done" aria-hidden="true">
                        <div class="tf-trail-line"><div class="tf-trail-fill" :style="`width:${progress}%`"></div></div>
                        <p><strong x-text="answeredCount"></strong> de <span x-text="requiredCount"></span> respondidas <span class="tf-dim">· cerca de <span x-text="estimate"></span></span></p>
                    </div>
                @endif
            </div>
        </aside>
    @endunless

    <main class="tf-main">
        <form class="tf-form" @submit.prevent="submit()" novalidate autocomplete="on">
            <div aria-hidden="true" class="tf-hp"><label>Site <input type="text" name="website_url" tabindex="-1" autocomplete="off" x-model="hp"></label></div>

            {{-- Sucesso --}}
            <template x-if="done && !CHAT_MODE">
                <section class="tf-done">
                    <div class="tf-mark is-ok"><i class="fa-solid fa-check"></i></div>
                    <h2>{{ $s('success_title') }}</h2>
                    <p>{!! nl2br(e($s('success_message'))) !!}</p>
                    <p class="tf-dim" x-show="redirecting"><i class="fa-solid fa-spinner fa-spin"></i> Redirecionando…</p>
                </section>
            </template>

            @if ($chat)
                <div class="tf-chat-intro">
                    <div class="tf-msg is-bot"><p class="tf-bubble"><strong>{{ $form->title }}</strong></p></div>
                    @if ($form->description)<div class="tf-msg is-bot"><p class="tf-bubble">{!! nl2br(e($form->description)) !!}</p></div>@endif
                </div>
            @endif

            {{-- Boas-vindas (conversacional) --}}
            @if ($conv && ! $chat)
                <section class="tf-welcome {{ $immersive && $t['cover_url'] ? 'has-cover' : '' }}" x-show="!done && step === -1">
                    @if ($immersive && $t['cover_url'])<div class="tf-cover" style="background-image:url('{{ e($t['cover_url']) }}')" role="img" aria-label=""></div>@endif
                    <div class="tf-welcome-text">
                    @if ($t['logo_url'])<img src="{{ $t['logo_url'] }}" alt="" class="tf-logo">@endif
                    <h1 class="tf-title">{{ $form->title }}</h1>
                    @if ($form->description)<p class="tf-desc">{!! nl2br(e($form->description)) !!}</p>@endif
                    <div class="tf-actions">
                        <button type="button" class="tf-btn" @click="next()">Começar</button>
                        <span class="tf-hint">ou pressione <kbd>Enter</kbd> · cerca de <span x-text="estimate"></span></span>
                    </div>
                    </div>
                </section>
            @endif

            <ol class="tf-steps" x-show="!done || CHAT_MODE">
                <template x-for="(f, i) in fields" :key="f.key">
                    <li class="tf-step"
                        :id="'tf-' + f.key"
                        x-show="isShown(f)"
                        x-transition:enter="tf-in" x-transition:enter-start="tf-in-start" x-transition:enter-end="tf-in-end"
                        :class="{ 'is-section': f.type === 'statement', 'is-filled': filled(f), 'has-error': errors[f.key], 'is-current': isCurrent(f), 'is-past': isPast(f) }"
                        @focusin="track.focus(f.key)">

                        <span class="tf-stop" aria-hidden="true" x-show="f.type !== 'statement'"><span x-text="numberOf(f)"></span>@if ($immersive)<i class="fa-solid fa-arrow-right"></i>@endif</span>

                        <div class="tf-q">
                            <label class="tf-label" :for="'in-' + f.key" :id="'lb-' + f.key">
                                <span x-text="f.label"></span><span class="tf-req" x-show="f.required && f.type !== 'statement'" aria-label="obrigatório">*</span>
                            </label>
                            <p class="tf-help" x-show="f.description" x-text="f.description"></p>

                            @if ($chat)
                                <button type="button" class="tf-msg is-user" x-show="isPast(f) && f.type !== 'statement'" @click="goTo(f)" :title="done ? '' : 'Toque para alterar'" :disabled="done || sending">
                                    <span class="tf-bubble" x-text="display(f)"></span>
                                    <span class="tf-edit" x-show="!done"><i class="fa-solid fa-pen"></i> alterar</span>
                                </button>
                            @endif

                            <div class="tf-reply" x-show="!CHAT_MODE || (isCurrent(f) && !done)">

                            {{-- Texto e contato --}}
                            <template x-if="['short_text','email','phone','url','number','date','time'].includes(f.type)">
                                <input class="tf-input" :id="'in-' + f.key" :name="f.key"
                                       :type="({short_text:'text',email:'email',phone:'tel',url:'url',number:'number',date:'date',time:'time'})[f.type]"
                                       :inputmode="({phone:'tel',number:'decimal',email:'email',url:'url'})[f.type] || 'text'"
                                       :autocomplete="autocomplete(f)"
                                       :placeholder="f.placeholder || ({email:'nome@exemplo.com',phone:'(00) 00000-0000',url:'https://'})[f.type] || ''"
                                       :min="f.settings.min" :max="f.settings.max"
                                       x-model="answers[f.key]" @input="changed(f)" @keydown.enter.prevent="enter(f)">
                            </template>

                            <template x-if="f.type === 'long_text'">
                                <textarea class="tf-input tf-textarea" :id="'in-' + f.key" :name="f.key" :rows="f.settings.rows || 4" :placeholder="f.placeholder || ''"
                                          x-model="answers[f.key]" @input="changed(f)" @keydown.enter.ctrl.prevent="enter(f)" @keydown.enter.meta.prevent="enter(f)"></textarea>
                            </template>

                            {{-- CPF / CNPJ com máscara --}}
                            <template x-if="f.type === 'cpf_cnpj'">
                                <input class="tf-input tf-short" :id="'in-' + f.key" inputmode="numeric" autocomplete="off"
                                       :placeholder="({cpf:'000.000.000-00',cnpj:'00.000.000/0000-00'})[f.settings.doc] || 'CPF ou CNPJ'"
                                       :value="answers[f.key]" @input="answers[f.key] = maskDoc($event.target.value, f.settings.doc); $event.target.value = answers[f.key]; changed(f)"
                                       @keydown.enter.prevent="enter(f)">
                            </template>

                            {{-- Moeda --}}
                            <template x-if="f.type === 'currency'">
                                <div class="tf-affix tf-short">
                                    <span>R$</span>
                                    <input class="tf-input" :id="'in-' + f.key" inputmode="numeric" placeholder="0,00"
                                           :value="moneyText[f.key] ?? ''" @input="setMoney(f, $event.target)" @keydown.enter.prevent="enter(f)">
                                </div>
                            </template>

                            {{-- Controle deslizante --}}
                            <template x-if="f.type === 'slider'">
                                <div class="tf-slider" :class="{ 'is-untouched': answers[f.key] === null }">
                                    <output class="tf-slider-value" :for="'in-' + f.key"
                                            x-text="answers[f.key] === null ? 'Arraste para responder' : fmtNum(answers[f.key]) + (f.settings.unit ? ' ' + f.settings.unit : '')"></output>
                                    <input type="range" :id="'in-' + f.key" :min="f.settings.min ?? 0" :max="f.settings.max ?? 100" :step="f.settings.step || 1"
                                           :value="answers[f.key] ?? Math.round(((Number(f.settings.min ?? 0)) + Number(f.settings.max ?? 100)) / 2)"
                                           :style="`--pct:${sliderPct(f)}%`"
                                           @input="answers[f.key] = Number($event.target.value); changed(f)">
                                    <div class="tf-scale-ends"><span x-text="(f.settings.min ?? 0) + (f.settings.unit ? ' ' + f.settings.unit : '')"></span><span x-text="(f.settings.max ?? 100) + (f.settings.unit ? ' ' + f.settings.unit : '')"></span></div>
                                </div>
                            </template>

                            {{-- Endereço com busca por CEP --}}
                            <template x-if="f.type === 'address'">
                                <div class="tf-address">
                                    <div class="tf-cep">
                                        <input class="tf-input" :id="'in-' + f.key" inputmode="numeric" placeholder="00000-000" autocomplete="postal-code" maxlength="9"
                                               :value="answers[f.key].cep" @input="answers[f.key].cep = maskCep($event.target.value); $event.target.value = answers[f.key].cep; changed(f); lookupCep(f)">
                                        <span class="tf-dim" x-show="cepState[f.key] === 'loading'"><i class="fa-solid fa-spinner fa-spin"></i> Buscando endereço…</span>
                                        <span class="tf-dim" x-show="cepState[f.key] === 'notfound'">CEP não encontrado — preencha abaixo.</span>
                                        <a class="tf-dim" target="_blank" rel="noopener" href="https://buscacepinter.correios.com.br/" x-show="!answers[f.key].cep">Não sei meu CEP</a>
                                    </div>
                                    <div class="tf-address-grid" x-show="answers[f.key].cep.length >= 9 || answers[f.key].street">
                                        <input class="tf-input g-street" placeholder="Rua / avenida" autocomplete="address-line1" x-model="answers[f.key].street" @input="changed(f)">
                                        <input class="tf-input g-number" placeholder="Número" :id="'num-' + f.key" x-model="answers[f.key].number" @input="changed(f)">
                                        <input class="tf-input g-compl" placeholder="Complemento (opcional)" autocomplete="address-line2" x-model="answers[f.key].complement" @input="changed(f)">
                                        <input class="tf-input g-district" placeholder="Bairro" x-model="answers[f.key].district" @input="changed(f)">
                                        <input class="tf-input g-city" placeholder="Cidade" autocomplete="address-level2" x-model="answers[f.key].city" @input="changed(f)">
                                        <input class="tf-input g-state" placeholder="UF" maxlength="2" autocomplete="address-level1" x-model="answers[f.key].state" @input="answers[f.key].state = answers[f.key].state.toUpperCase(); changed(f)">
                                    </div>
                                </div>
                            </template>

                            {{-- Escolhas --}}
                            <template x-if="f.type === 'radio' || f.type === 'checkbox'">
                                <div class="tf-choices" :class="{ 'is-wide': (f.options || []).length > 4 }" role="group" :aria-labelledby="'lb-' + f.key">
                                    <template x-for="(opt, oi) in f.options" :key="oi">
                                        <button type="button" class="tf-choice" :class="{ 'is-on': isSelected(f, opt) }" :aria-pressed="isSelected(f, opt)" @click="choose(f, opt)">
                                            <span class="tf-key" x-text="String.fromCharCode(65 + oi)"></span>
                                            <span class="tf-choice-text" x-text="opt"></span>
                                            <i class="fa-solid fa-check tf-tick"></i>
                                        </button>
                                    </template>
                                    <template x-if="f.settings.allow_other">
                                        <input class="tf-input tf-other" placeholder="Outro (escreva aqui)" :value="otherValue(f)" @input="setOther(f, $event.target.value)">
                                    </template>
                                    <p class="tf-dim tf-small" x-show="f.type === 'checkbox'" x-text="f.settings.max_choices ? 'Escolha até ' + f.settings.max_choices : 'Escolha quantas quiser'"></p>
                                </div>
                            </template>

                            <template x-if="f.type === 'select'">
                                <div class="tf-select">
                                    <select class="tf-input" :id="'in-' + f.key" x-model="answers[f.key]" @change="changed(f); autoAdvance(f)">
                                        <option value="">Selecione…</option>
                                        <template x-for="opt in f.options" :key="opt"><option :value="opt" x-text="opt"></option></template>
                                    </select>
                                </div>
                            </template>

                            <template x-if="f.type === 'yes_no'">
                                <div class="tf-choices is-pair">
                                    <template x-for="opt in ['Sim','Não']" :key="opt">
                                        <button type="button" class="tf-choice" :class="{ 'is-on': answers[f.key] === opt }" @click="answers[f.key] = opt; changed(f); autoAdvance(f)">
                                            <span class="tf-key" x-text="opt[0]"></span><span class="tf-choice-text" x-text="opt"></span>
                                        </button>
                                    </template>
                                </div>
                            </template>

                            {{-- Ranking --}}
                            <template x-if="f.type === 'ranking'">
                                <div>
                                    <ol class="tf-rank" :data-rank="f.key" x-init="initRank($el, f)">
                                        <template x-for="(opt, oi) in rankOrder[f.key]" :key="opt">
                                            <li class="tf-rank-item" :data-opt="opt">
                                                <span class="tf-rank-pos" x-text="oi + 1"></span>
                                                <span class="tf-rank-grip" aria-hidden="true"><i class="fa-solid fa-grip-vertical"></i></span>
                                                <span class="tf-choice-text" x-text="opt"></span>
                                                <span class="tf-rank-moves">
                                                    <button type="button" :disabled="oi === 0" @click="moveRank(f, oi, -1)" :aria-label="'Subir ' + opt"><i class="fa-solid fa-arrow-up"></i></button>
                                                    <button type="button" :disabled="oi === rankOrder[f.key].length - 1" @click="moveRank(f, oi, 1)" :aria-label="'Descer ' + opt"><i class="fa-solid fa-arrow-down"></i></button>
                                                </span>
                                            </li>
                                        </template>
                                    </ol>
                                    <button type="button" class="tf-link" x-show="!filled(f)" @click="answers[f.key] = [...rankOrder[f.key]]; changed(f)">Manter esta ordem</button>
                                    <p class="tf-dim tf-small" x-show="filled(f)"><i class="fa-solid fa-check"></i> Ordem registrada</p>
                                </div>
                            </template>

                            {{-- Matriz --}}
                            <template x-if="f.type === 'matrix'">
                                <div class="tf-matrix" role="table" :style="`--cols:${f.options.length}`">
                                    <div class="tf-matrix-head" role="row">
                                        <span></span>
                                        <template x-for="c in f.options"><span role="columnheader" x-text="c"></span></template>
                                    </div>
                                    <template x-for="row in (f.settings.matrix_rows || [])" :key="row">
                                        <div class="tf-matrix-row" role="row" :class="{ 'is-done': answers[f.key][row] }">
                                            <span class="tf-matrix-q" role="rowheader" x-text="row"></span>
                                            <template x-for="c in f.options">
                                                <button type="button" class="tf-cell" role="cell" :class="{ 'is-on': answers[f.key][row] === c }" :aria-pressed="answers[f.key][row] === c"
                                                        :aria-label="row + ': ' + c" @click="answers[f.key] = { ...answers[f.key], [row]: c }; changed(f)">
                                                    <span class="tf-dot"></span><span class="tf-cell-label" x-text="c"></span>
                                                </button>
                                            </template>
                                        </div>
                                    </template>
                                </div>
                            </template>

                            {{-- Opinião --}}
                            <template x-if="f.type === 'rating'">
                                <div class="tf-stars" @mouseleave="hover = {}">
                                    <template x-for="n in Number(f.settings.max || 5)" :key="n">
                                        <button type="button" class="tf-star" :aria-label="n + ' de ' + (f.settings.max || 5)"
                                                :class="{ 'is-on': (hover[f.key] || answers[f.key] || 0) >= n }"
                                                @mouseenter="hover[f.key] = n" @click="answers[f.key] = n; changed(f); autoAdvance(f)"><i class="fa-solid fa-star"></i></button>
                                    </template>
                                </div>
                            </template>

                            <template x-if="f.type === 'nps' || f.type === 'scale'">
                                <div>
                                    <div class="tf-scale" :class="{ 'is-nps': f.type === 'nps' }">
                                        <template x-for="n in range(f)" :key="n">
                                            <button type="button" class="tf-scale-btn" :class="{ 'is-on': answers[f.key] === n, ['band-' + npsBand(n)]: f.type === 'nps' }"
                                                    @click="answers[f.key] = n; changed(f); autoAdvance(f)" x-text="n"></button>
                                        </template>
                                    </div>
                                    <div class="tf-scale-ends">
                                        <span x-text="f.settings.label_min || (f.type === 'nps' ? 'Nada provável' : '')"></span>
                                        <span x-text="f.settings.label_max || (f.type === 'nps' ? 'Muito provável' : '')"></span>
                                    </div>
                                </div>
                            </template>

                            {{-- Arquivos --}}
                            <template x-if="f.type === 'file'">
                                <div class="tf-files">
                                    <label class="tf-drop" :class="{ 'is-over': dragOver === f.key, 'is-full': (answers[f.key] || []).length >= (f.settings.max_files || 1) }"
                                           @dragover.prevent="dragOver = f.key" @dragleave.prevent="dragOver = null" @drop.prevent="dragOver = null; addFiles(f, $event.dataTransfer.files)">
                                        <input type="file" class="tf-file-input" :id="'in-' + f.key" :accept="acceptAttr(f)" :multiple="(f.settings.max_files || 1) > 1"
                                               @change="addFiles(f, $event.target.files); $event.target.value = ''">
                                        <i class="fa-solid fa-arrow-up-from-bracket"></i>
                                        <span><strong>Escolher arquivo</strong> ou arraste até aqui</span>
                                        <span class="tf-dim tf-small" x-text="fileHint(f)"></span>
                                    </label>
                                    <ul class="tf-file-list">
                                        <template x-for="(it, ii) in (uploads[f.key] || [])" :key="it.tmp">
                                            <li class="tf-file" :class="{ 'is-error': it.error }">
                                                <i class="fa-regular" :class="it.error ? 'fa-circle-xmark' : (it.done ? 'fa-file-lines' : 'fa-hourglass-half')"></i>
                                                <span class="tf-file-name" x-text="it.name"></span>
                                                <span class="tf-dim tf-small" x-text="it.error || (it.done ? fmtSize(it.size) : it.pct + '%')"></span>
                                                <span class="tf-file-bar" x-show="!it.done && !it.error"><span :style="`width:${it.pct}%`"></span></span>
                                                <button type="button" @click="removeFile(f, ii)" aria-label="Remover arquivo"><i class="fa-solid fa-xmark"></i></button>
                                            </li>
                                        </template>
                                    </ul>
                                </div>
                            </template>

                            {{-- Assinatura --}}
                            <template x-if="f.type === 'signature'">
                                <div class="tf-sign">
                                    <canvas :data-sign="f.key" x-init="initSignature($el, f)" aria-label="Área para assinatura"></canvas>
                                    <div class="tf-sign-foot">
                                        <span class="tf-dim tf-small" x-text="answers[f.key] ? 'Assinado' : 'Assine com o dedo ou o mouse'"></span>
                                        <button type="button" class="tf-link" @click="clearSignature(f)" x-show="answers[f.key]">Limpar</button>
                                    </div>
                                </div>
                            </template>

                            <template x-if="f.type === 'consent'">
                                <label class="tf-consent">
                                    <input type="checkbox" x-model="answers[f.key]" @change="changed(f)">
                                    <span x-text="f.settings.text || 'Li e concordo com a política de privacidade.'"></span>
                                </label>
                            </template>

                            <p class="tf-error" role="alert" x-show="errors[f.key]"><i class="fa-solid fa-circle-exclamation"></i> <span x-text="errors[f.key]"></span></p>

                            @if ($conv)
                                <div class="tf-actions">
                                    <button type="button" class="tf-btn" @click="next()" x-show="!isLastVisible(f)">
                                        <span x-text="f.type === 'statement' ? 'Continuar' : 'OK'"></span> <i class="fa-solid fa-check"></i>
                                    </button>
                                    <button type="submit" class="tf-btn" x-show="isLastVisible(f)" :disabled="sending || uploading">
                                        <span x-show="!sending">{{ $s('submit_label') }}</span>
                                        <span x-show="sending"><i class="fa-solid fa-spinner fa-spin"></i> Enviando…</span>
                                    </button>
                                    <span class="tf-hint" x-show="!['long_text','address','matrix','file','signature','ranking'].includes(f.type)">ou pressione <kbd>Enter</kbd></span>
                                    <span class="tf-hint" x-show="f.type === 'long_text'"><kbd>Ctrl</kbd> + <kbd>Enter</kbd> para continuar</span>
                                </div>
                            @endif
                            </div>
                        </div>
                    </li>
                </template>
            </ol>

            @if ($chat)
                <div class="tf-msg is-bot tf-typing" x-show="typing" aria-label="digitando"><p class="tf-bubble"><i></i><i></i><i></i></p></div>
                <template x-if="done">
                    <div class="tf-chat-done">
                        <div class="tf-msg is-bot"><p class="tf-bubble"><strong>{{ $s('success_title') }}</strong></p></div>
                        @if ($s('success_message'))<div class="tf-msg is-bot"><p class="tf-bubble">{!! nl2br(e($s('success_message'))) !!}</p></div>@endif
                        <p class="tf-dim tf-small" x-show="redirecting"><i class="fa-solid fa-spinner fa-spin"></i> Redirecionando…</p>
                    </div>
                </template>
            @endif

            <p class="tf-error tf-global" role="alert" x-show="globalError"><i class="fa-solid fa-circle-exclamation"></i> <span x-text="globalError"></span></p>

            @unless ($conv)
                <div class="tf-submit" x-show="!done">
                    <button type="submit" class="tf-btn is-big" :disabled="sending || uploading">
                        <span x-show="!sending && !uploading">{{ $s('submit_label') }}</span>
                        <span x-show="uploading && !sending"><i class="fa-solid fa-spinner fa-spin"></i> Aguardando arquivos…</span>
                        <span x-show="sending"><i class="fa-solid fa-spinner fa-spin"></i> Enviando…</span>
                    </button>
                    <span class="tf-dim tf-small" x-show="requiredCount > answeredCount" x-text="(requiredCount - answeredCount) + ' pergunta(s) obrigatória(s) faltando'"></span>
                </div>
            @endunless
        </form>

        @if ($conv && ! $chat)
            <nav class="tf-nav" x-show="!done && step >= 0">
                @if ($immersive)
                    <span class="tf-nav-progress"><span class="tf-small" x-text="progress + '% concluído'"></span><span class="tf-nav-bar"><span :style="`width:${progress}%`"></span></span></span>
                @else
                    <span class="tf-dim tf-small" x-text="Math.min(step + 1, visible.length) + ' / ' + visible.length"></span>
                @endif
                <button type="button" @click="prev()" aria-label="Pergunta anterior"><i class="fa-solid fa-chevron-up"></i></button>
                <button type="button" @click="next()" aria-label="Próxima pergunta"><i class="fa-solid fa-chevron-down"></i></button>
            </nav>
        @endif

        @if ($s('show_branding'))
            <a class="tf-brand" href="{{ url('/') }}" target="_blank" rel="noopener">
                <svg viewBox="0 0 24 24" width="14" height="14" aria-hidden="true"><path d="M3 18 9 12l4 4 8-9" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"/><circle cx="21" cy="7" r="2.4" fill="currentColor"/></svg>
                Criado com TraceForms
            </a>
        @endif
    </main>
</div>

<script src="{{ asset('js/tf-tracker.js') }}?v=2"></script>
@if ($needs('ranking'))<script src="{{ asset('vendor/Sortable.min.js') }}"></script>@endif
<script>
function tfForm() {
    const FIELDS = @json($fieldsJson);
    const CONV = @json($conv);
    const CHAT = @json($chat);
    const PREVIEW = @json($preview);
    const FORM_ID = @json($form->public_id);
    const SUBMIT_URL = @json(route('public.submit', $form->public_id));
    const UPLOAD_URL = @json(route('public.upload', $form->public_id));
    const ACCEPT = @json(collect(\App\Support\FieldTypes::ACCEPT)->map(fn ($a) => $a['mimes']));
    const PIXELS = { ga4: @json((bool) $s('ga4_id')), meta: @json((bool) $s('meta_pixel_id')) };
    const empty = (f) => {
        if (['checkbox', 'ranking', 'file'].includes(f.type)) return [];
        if (f.type === 'matrix') return {};
        if (f.type === 'address') return { cep: '', street: '', number: '', complement: '', district: '', city: '', state: '' };
        if (f.type === 'consent') return false;
        if (f.type === 'slider' || f.type === 'signature') return null;
        return '';
    };

    return {
        fields: FIELDS.map(f => ({ ...f, settings: Array.isArray(f.settings) ? {} : (f.settings || {}), options: f.options || [] })),
        answers: {}, errors: {}, hover: {}, hp: '', moneyText: {}, cepState: {}, rankOrder: {}, uploads: {}, dragOver: null,
        step: CONV && !CHAT ? -1 : 0, dir: 1, typing: false, CHAT_MODE: CHAT, done: false, sending: false, redirecting: false, globalError: '', track: null,

        init() {
            this.fields.forEach(f => {
                this.answers[f.key] = empty(f);
                if (f.type === 'ranking') this.rankOrder[f.key] = [...f.options];
            });
            this.track = new TFTracker({ collectUrl: @json(route('public.collect', $form->public_id)), preview: PREVIEW }).init();
            if (CONV) window.addEventListener('keydown', e => this.shortcut(e));
            if (CHAT) this.botTurn(900);
            this.postHeight();
            if (window.ResizeObserver) new ResizeObserver(() => this.postHeight()).observe(document.body);
        },

        // ---------- Visibilidade e navegação ----------
        get visible() { return this.fields.filter(f => f.type !== 'hidden' && this.logicOk(f)); },
        get inputs() { return this.visible.filter(f => f.type !== 'statement'); },
        get requiredCount() { return this.inputs.filter(f => f.required).length || this.inputs.length; },
        get answeredCount() { const req = this.inputs.filter(f => f.required); return (req.length ? req : this.inputs).filter(f => this.filled(f)).length; },
        get estimate() { const m = Math.max(1, Math.round(this.inputs.reduce((t, f) => t + (['long_text','address','file','signature','matrix'].includes(f.type) ? 30 : 10), 0) / 60)); return m + (m === 1 ? ' minuto' : ' minutos'); },
        get progress() {
            if (CONV) return this.step < 0 ? 0 : Math.round(Math.min(this.step, this.visible.length) / this.visible.length * 100);
            return this.requiredCount ? Math.round(this.answeredCount / this.requiredCount * 100) : 0;
        },
        get uploading() { return Object.values(this.uploads).some(list => list.some(u => !u.done && !u.error)); },
        isShown(f) {
            if (f.type === 'hidden' || !this.logicOk(f)) return false;
            if (CHAT) { const i = this.visible.indexOf(f); return i >= 0 && (this.done || i < this.step || (i === this.step && !this.typing)); }
            return CONV ? !!this.visible[this.step] && this.visible[this.step].key === f.key : true;
        },
        isCurrent(f) { return CONV && this.visible[this.step] === f; },
        isPast(f) { return CHAT && (this.done || this.visible.indexOf(f) < this.step); },

        // ---------- Chat ----------
        botTurn(delay) {
            this.typing = true; this.scrollEnd();
            setTimeout(() => {
                this.typing = false;
                const f = this.visible[this.step];
                this.$nextTick(() => {
                    this.scrollEnd();
                    if (f && f.type === 'statement') { setTimeout(() => this.next(), 1100); return; }
                    const el = f && document.getElementById('in-' + f.key); el && el.focus({ preventScroll: true });
                });
            }, delay);
        },
        scrollEnd() { this.$nextTick(() => window.scrollTo({ top: document.documentElement.scrollHeight, behavior: 'smooth' })); },
        goTo(f) {
            if (this.done || this.sending) return;
            this.dir = -1; this.step = this.visible.indexOf(f);
            this.$nextTick(() => { document.getElementById('tf-' + f.key)?.scrollIntoView({ behavior: 'smooth', block: 'center' }); const el = document.getElementById('in-' + f.key); el && el.focus({ preventScroll: true }); });
        },
        display(f) {
            const v = this.answers[f.key];
            if (!this.filled(f)) return 'Pulei esta pergunta';
            switch (f.type) {
                case 'checkbox': return v.join(', ');
                case 'ranking': return v.map((x, i) => (i + 1) + '. ' + x).join('  ');
                case 'matrix': return Object.entries(v).map(([r, c]) => r + ': ' + c).join(' · ');
                case 'address': return [v.street && (v.street + (v.number ? ', ' + v.number : '')), v.district, v.city && (v.city + '/' + v.state), v.cep].filter(Boolean).join(' · ');
                case 'file': return (this.uploads[f.key] || []).filter(u => u.done).map(u => '📎 ' + u.name).join(', ');
                case 'signature': return '✍️ Assinatura enviada';
                case 'consent': return 'Aceito';
                case 'currency': return 'R$ ' + (this.moneyText[f.key] || v);
                case 'rating': return '★'.repeat(v) + ' (' + v + ')';
                case 'slider': return this.fmtNum(v) + (f.settings.unit ? ' ' + f.settings.unit : '');
                case 'date': { const [y, m, d] = String(v).split('-'); return d ? d + '/' + m + '/' + y : v; }
                default: return String(v);
            }
        },
        isLastVisible(f) { const v = this.visible; return v.length && v[v.length - 1].key === f.key; },
        numberOf(f) { return this.inputs.indexOf(f) + 1; },

        next() {
            if (this.step >= 0) { const f = this.visible[this.step]; if (f && !this.validate(f)) return; }
            if (CHAT && this.step < this.visible.length - 1) {
                this.dir = 1;
                const f = this.visible[this.step];
                if (f) this.track.reach(this.fields.indexOf(f) + 1, f.key);
                this.track.markStart();
                this.step++;
                this.botTurn(f && f.type === 'statement' ? 450 : 750);
                return;
            }
            if (this.step < this.visible.length - 1) {
                this.dir = 1;
                this.step++;
                const f = this.visible[this.step];
                this.track.reach(this.fields.indexOf(f) + 1, f.key);
                this.track.markStart();
                this.$nextTick(() => { const el = document.getElementById('in-' + f.key); el && el.focus({ preventScroll: true }); });
            } else { this.submit(); }
        },
        prev() { if (this.step > 0) { this.dir = -1; this.step--; } },
        enter() { if (CONV) this.next(); },
        autoAdvance(f) { if (CONV && f.type !== 'checkbox') setTimeout(() => this.next(), 380); },
        shortcut(e) {
            if (this.done || ['TEXTAREA', 'INPUT', 'SELECT'].includes(e.target.tagName) || e.metaKey || e.ctrlKey) return;
            if (e.key === 'Enter') { e.preventDefault(); this.next(); return; }
            const f = this.visible[this.step]; if (!f) return;
            const k = e.key.toUpperCase();
            if ((f.type === 'radio' || f.type === 'checkbox') && /^[A-Z]$/.test(k)) { const o = f.options[k.charCodeAt(0) - 65]; if (o !== undefined) this.choose(f, o); }
            if (f.type === 'yes_no' && (k === 'S' || k === 'N')) { this.answers[f.key] = k === 'S' ? 'Sim' : 'Não'; this.changed(f); this.autoAdvance(f); }
            if (['nps', 'scale', 'rating'].includes(f.type) && /^[0-9]$/.test(e.key)) { const n = Number(e.key); if (this.range(f).includes(n) || f.type === 'rating') { this.answers[f.key] = n; this.changed(f); this.autoAdvance(f); } }
        },

        // ---------- Lógica condicional (espelha App\Services\FormLogic) ----------
        logicOk(f) {
            const l = f.settings.logic;
            if (!l || !l.enabled || !(l.rules || []).length) return true;
            const res = l.rules.map(r => this.test(r));
            return l.match === 'any' ? res.includes(true) : !res.includes(false);
        },
        test(r) {
            const v = this.answers[r.field];
            let vals;
            if (Array.isArray(v)) vals = v.map(x => typeof x === 'object' ? '[arquivo]' : String(x));
            else if (v && typeof v === 'object') vals = Object.values(v).filter(x => x !== '' && x !== null).map(String);
            else vals = (v === null || v === undefined || v === '' || v === false) ? [] : [String(v === true ? 'Aceito' : v)];
            const t = String(r.value ?? '').toLowerCase(), low = vals.map(x => x.toLowerCase());
            switch (r.op) {
                case 'eq': return low.includes(t);
                case 'neq': return !low.includes(t);
                case 'contains': return low.some(x => x.includes(t));
                case 'not_contains': return !low.some(x => x.includes(t));
                case 'gt': return vals.length > 0 && !isNaN(vals[0]) && Number(vals[0]) > Number(r.value);
                case 'lt': return vals.length > 0 && !isNaN(vals[0]) && Number(vals[0]) < Number(r.value);
                case 'empty': return vals.length === 0;
                case 'not_empty': return vals.length > 0;
            }
            return true;
        },

        // ---------- Respostas ----------
        isSelected(f, opt) { const v = this.answers[f.key]; return Array.isArray(v) ? v.includes(opt) : v === opt; },
        choose(f, opt) {
            if (f.type === 'checkbox') {
                const arr = [...this.answers[f.key]]; const i = arr.indexOf(opt);
                if (i >= 0) arr.splice(i, 1); else { if (f.settings.max_choices && arr.length >= f.settings.max_choices) return; arr.push(opt); }
                this.answers[f.key] = arr;
            } else { this.answers[f.key] = opt; this.autoAdvance(f); }
            this.changed(f);
        },
        otherValue(f) { const v = this.answers[f.key], o = f.options; return Array.isArray(v) ? (v.find(x => !o.includes(x)) || '') : (v && !o.includes(v) ? v : ''); },
        setOther(f, text) {
            if (f.type === 'checkbox') { const arr = this.answers[f.key].filter(x => f.options.includes(x)); if (text) arr.push(text); this.answers[f.key] = arr; }
            else this.answers[f.key] = text;
            this.changed(f);
        },
        range(f) {
            if (f.type === 'nps') return [0,1,2,3,4,5,6,7,8,9,10];
            const a = Number(f.settings.min ?? 1), b = Number(f.settings.max ?? 5), out = [];
            for (let n = a; n <= b; n++) out.push(n); return out;
        },
        npsBand(n) { return n <= 6 ? 'low' : (n <= 8 ? 'mid' : 'high'); },
        autocomplete(f) {
            const l = (f.label || '').toLowerCase();
            if (f.type === 'email') return 'email'; if (f.type === 'phone') return 'tel'; if (f.type === 'url') return 'url';
            if (/nome completo|seu nome|^nome/.test(l)) return 'name';
            if (/empresa|companhia/.test(l)) return 'organization';
            if (/cargo/.test(l)) return 'organization-title';
            return 'on';
        },
        filled(f) {
            const v = this.answers[f.key];
            if (f.type === 'address') return !!(v && v.cep && v.city);
            if (f.type === 'matrix') return v && Object.keys(v).length > 0;
            if (Array.isArray(v)) return v.length > 0;
            return v !== '' && v !== null && v !== undefined && v !== false;
        },
        changed(f) {
            delete this.errors[f.key];
            this.track.change(f.key, this.fields.indexOf(f) + 1, this.partial());
        },
        partial() {
            const out = {};
            this.fields.forEach(f => { if (!['statement', 'hidden', 'file', 'signature'].includes(f.type) && this.filled(f)) out[f.key] = this.answers[f.key]; });
            return out;
        },
        fmtNum(n) { return Number(n).toLocaleString('pt-BR'); },
        sliderPct(f) {
            const min = Number(f.settings.min ?? 0), max = Number(f.settings.max ?? 100);
            const v = this.answers[f.key] ?? (min + max) / 2;
            return max > min ? (v - min) / (max - min) * 100 : 0;
        },

        // ---------- Máscaras e Brasil ----------
        maskDoc(v, doc) {
            const d = v.replace(/\D/g, '').slice(0, doc === 'cpf' ? 11 : 14);
            if (d.length <= 11 && doc !== 'cnpj') return d.replace(/(\d{3})(\d)/, '$1.$2').replace(/(\d{3})(\d)/, '$1.$2').replace(/(\d{3})(\d{1,2})$/, '$1-$2');
            return d.replace(/^(\d{2})(\d)/, '$1.$2').replace(/^(\d{2})\.(\d{3})(\d)/, '$1.$2.$3').replace(/\.(\d{3})(\d)/, '.$1/$2').replace(/(\d{4})(\d)/, '$1-$2');
        },
        validDoc(v, doc) {
            const d = String(v || '').replace(/\D/g, '');
            const cpf = (c) => { if (c.length !== 11 || /^(\d)\1+$/.test(c)) return false; for (let t = 9; t < 11; t++) { let s = 0; for (let i = 0; i < t; i++) s += c[i] * (t + 1 - i); if (Number(c[t]) !== ((10 * s) % 11) % 10) return false; } return true; };
            const cnpj = (c) => { if (c.length !== 14 || /^(\d)\1+$/.test(c)) return false; const w = [[5,4,3,2,9,8,7,6,5,4,3,2], [6,5,4,3,2,9,8,7,6,5,4,3,2]]; for (let k = 0; k < 2; k++) { let s = 0; for (let i = 0; i < w[k].length; i++) s += c[i] * w[k][i]; const r = s % 11; if (Number(c[12 + k]) !== (r < 2 ? 0 : 11 - r)) return false; } return true; };
            return doc === 'cpf' ? cpf(d) : (doc === 'cnpj' ? cnpj(d) : (cpf(d) || cnpj(d)));
        },
        maskCep(v) { const d = v.replace(/\D/g, '').slice(0, 8); return d.length > 5 ? d.slice(0, 5) + '-' + d.slice(5) : d; },
        async lookupCep(f) {
            const cep = this.answers[f.key].cep.replace(/\D/g, '');
            if (cep.length !== 8 || this.cepState[f.key + ':last'] === cep) return;
            this.cepState[f.key + ':last'] = cep; this.cepState[f.key] = 'loading';
            try {
                const r = await fetch('https://viacep.com.br/ws/' + cep + '/json/'); const d = await r.json();
                if (d.erro) { this.cepState[f.key] = 'notfound'; return; }
                Object.assign(this.answers[f.key], { street: d.logradouro || '', district: d.bairro || '', city: d.localidade || '', state: d.uf || '' });
                this.cepState[f.key] = 'ok'; this.changed(f);
                this.$nextTick(() => document.getElementById('num-' + f.key)?.focus());
            } catch (e) { this.cepState[f.key] = 'notfound'; }
        },
        setMoney(f, el) {
            const digits = el.value.replace(/\D/g, '').slice(0, 13);
            if (!digits) { this.moneyText[f.key] = ''; this.answers[f.key] = ''; el.value = ''; this.changed(f); return; }
            const n = Number(digits) / 100;
            this.answers[f.key] = n.toFixed(2);
            this.moneyText[f.key] = n.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            el.value = this.moneyText[f.key];
            this.changed(f);
        },

        // ---------- Ranking ----------
        initRank(el, f) {
            if (!window.Sortable) return;
            Sortable.create(el, { animation: 150, handle: '.tf-rank-item', draggable: '.tf-rank-item', ghostClass: 'is-ghost',
                onEnd: (e) => {
                    if (e.oldDraggableIndex === e.newDraggableIndex) return;
                    const nodes = Array.from(el.querySelectorAll('.tf-rank-item'));
                    el.insertBefore(e.item, nodes[e.oldDraggableIndex + (e.oldDraggableIndex > e.newDraggableIndex ? 1 : 0)] || null);
                    this.moveRank(f, e.oldDraggableIndex, e.newDraggableIndex - e.oldDraggableIndex);
                } });
        },
        moveRank(f, i, delta) {
            const arr = [...this.rankOrder[f.key]]; const j = i + delta;
            if (j < 0 || j >= arr.length) return;
            const [m] = arr.splice(i, 1); arr.splice(j, 0, m);
            this.rankOrder[f.key] = arr; this.answers[f.key] = [...arr]; this.changed(f);
        },

        // ---------- Arquivos ----------
        acceptAttr(f) { return (ACCEPT[f.settings.accept || 'any'] || ACCEPT.any).split(',').map(x => '.' + x).join(','); },
        fileHint(f) {
            const max = f.settings.max_files || 1;
            const kinds = { any: 'Imagens, PDF, documentos, planilhas', images: 'Imagens', documents: 'PDF, Word, Excel', pdf: 'Somente PDF', media: 'Áudio e vídeo' }[f.settings.accept || 'any'];
            return kinds + ' · até ' + (f.settings.max_size || 10) + ' MB' + (max > 1 ? ' · até ' + max + ' arquivos' : '');
        },
        fmtSize(b) { return b >= 1048576 ? (b / 1048576).toFixed(1).replace('.', ',') + ' MB' : Math.max(1, Math.round(b / 1024)) + ' KB'; },
        addFiles(f, list) {
            const max = f.settings.max_files || 1, limit = (f.settings.max_size || 10) * 1048576;
            this.uploads[f.key] = this.uploads[f.key] || [];
            Array.from(list).forEach(file => {
                if (this.uploads[f.key].filter(u => !u.error).length >= max) { this.errors[f.key] = 'Você pode enviar no máximo ' + max + ' arquivo(s).'; return; }
                const item = { tmp: Math.random().toString(36).slice(2), name: file.name, size: file.size, pct: 0, done: false, error: '', id: null };
                this.uploads[f.key].push(item);
                const it = this.uploads[f.key][this.uploads[f.key].length - 1];
                if (file.size > limit) { it.error = 'Maior que ' + (f.settings.max_size || 10) + ' MB'; return; }
                this.upload(f, file, it);
            });
            this.track.markStart();
        },
        upload(f, file, it) {
            const fd = new FormData(); fd.append('file', file); fd.append('field', f.key);
            if (this.track.sid) fd.append('sid', this.track.sid);
            if (PREVIEW) fd.append('preview', '1');
            const xhr = new XMLHttpRequest();
            xhr.open('POST', UPLOAD_URL); xhr.setRequestHeader('Accept', 'application/json');
            xhr.upload.onprogress = (e) => { if (e.lengthComputable) it.pct = Math.round(e.loaded / e.total * 100); };
            xhr.onload = () => {
                let d = {}; try { d = JSON.parse(xhr.responseText); } catch (e) {}
                if (xhr.status >= 200 && xhr.status < 300 && d.id) { it.done = true; it.id = d.id; it.size = d.size; this.syncFiles(f); }
                else it.error = d.message || 'Falha no envio';
            };
            xhr.onerror = () => { it.error = 'Falha de conexão'; };
            xhr.send(fd);
        },
        removeFile(f, i) { this.uploads[f.key].splice(i, 1); this.syncFiles(f); },
        syncFiles(f) { this.answers[f.key] = (this.uploads[f.key] || []).filter(u => u.done).map(u => u.id); this.changed(f); },

        // ---------- Assinatura ----------
        initSignature(canvas, f) {
            const ctx = canvas.getContext('2d');
            const fit = () => {
                const r = canvas.getBoundingClientRect(), dpr = window.devicePixelRatio || 1;
                if (!r.width) return;
                canvas.width = r.width * dpr; canvas.height = r.height * dpr; ctx.scale(dpr, dpr);
                ctx.lineWidth = 2.4; ctx.lineCap = 'round'; ctx.lineJoin = 'round';
                ctx.strokeStyle = getComputedStyle(document.documentElement).getPropertyValue('--tf-text').trim() || '#14231f';
            };
            fit(); new ResizeObserver(() => { if (!this.answers[f.key]) fit(); }).observe(canvas);
            let drawing = false, last = null;
            const pos = (e) => { const r = canvas.getBoundingClientRect(); return [e.clientX - r.left, e.clientY - r.top]; };
            canvas.addEventListener('pointerdown', (e) => { drawing = true; last = pos(e); canvas.setPointerCapture(e.pointerId); this.track.focus(f.key); });
            canvas.addEventListener('pointermove', (e) => { if (!drawing) return; const p = pos(e); ctx.beginPath(); ctx.moveTo(...last); ctx.lineTo(...p); ctx.stroke(); last = p; });
            const end = () => { if (!drawing) return; drawing = false; this.answers[f.key] = canvas.toDataURL('image/png'); this.changed(f); };
            canvas.addEventListener('pointerup', end); canvas.addEventListener('pointerleave', end);
        },
        clearSignature(f) {
            const c = document.querySelector(`canvas[data-sign="${f.key}"]`);
            c.getContext('2d').clearRect(0, 0, c.width, c.height);
            this.answers[f.key] = null; this.changed(f);
        },

        // ---------- Validação ----------
        validate(f) {
            if (f.type === 'statement' || f.type === 'hidden') return true;
            const v = this.answers[f.key]; let err = '';
            if (f.type === 'file' && (this.uploads[f.key] || []).some(u => !u.done && !u.error)) err = 'Aguarde o envio do arquivo terminar.';
            else if (f.required && !this.filled(f)) {
                err = ({ consent: 'É necessário aceitar para continuar.', file: 'Envie o arquivo.', signature: 'Assine para continuar.', ranking: 'Ordene as opções ou clique em “Manter esta ordem”.', address: 'Informe o CEP e o endereço.', slider: 'Arraste o controle para responder.' })[f.type] || 'Este campo é obrigatório.';
            } else if (this.filled(f)) {
                if (f.type === 'email' && !/^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(v)) err = 'Informe um e-mail válido.';
                if (f.type === 'url' && !/^https?:\/\/.+\..+/.test(v)) err = 'Informe uma URL válida (com https://).';
                if (f.type === 'phone' && !/^[0-9+()\-\s.]{6,40}$/.test(v)) err = 'Informe um telefone válido.';
                if (f.type === 'cpf_cnpj' && !this.validDoc(v, f.settings.doc)) err = ({ cpf: 'Informe um CPF válido.', cnpj: 'Informe um CNPJ válido.' })[f.settings.doc] || 'Informe um CPF ou CNPJ válido.';
                if (f.type === 'number' || f.type === 'currency') {
                    if (isNaN(v)) err = 'Informe um número.';
                    else if (f.settings.min !== undefined && f.settings.min !== '' && Number(v) < Number(f.settings.min)) err = 'O valor mínimo é ' + f.settings.min + '.';
                    else if (f.settings.max !== undefined && f.settings.max !== '' && Number(v) > Number(f.settings.max)) err = 'O valor máximo é ' + f.settings.max + '.';
                }
                if (f.type === 'address' && (!/^\d{5}-?\d{3}$/.test(v.cep) || !v.street || !v.city || v.state.length !== 2 || (f.required && !f.settings.number_optional && !v.number))) err = 'Complete o endereço (rua, número, cidade e UF).';
                if (f.type === 'matrix' && f.required && Object.keys(v).length < (f.settings.matrix_rows || []).length) err = 'Responda todas as linhas.';
                if (f.type === 'ranking' && f.required && v.length < f.options.length) err = 'Ordene todas as opções.';
            }
            if (err) { this.errors[f.key] = err; this.track.error(f.key); return false; }
            return true;
        },

        async submit() {
            if (this.sending) return;
            this.globalError = '';
            const vis = this.visible;
            const invalid = vis.filter(f => !this.validate(f));
            if (invalid.length) {
                if (CONV) this.step = vis.indexOf(invalid[0]);
                this.$nextTick(() => document.getElementById('tf-' + invalid[0].key)?.scrollIntoView({ behavior: 'smooth', block: 'center' }));
                return;
            }
            if (PREVIEW) { this.done = true; return; }

            this.sending = true;
            const answers = {};
            vis.forEach(f => {
                if (f.type === 'statement') return;
                let v = this.answers[f.key];
                if (f.type === 'consent') v = v ? 1 : null;
                if (f.type === 'address' && !this.filled(f)) v = null;
                if (f.type === 'matrix' && !Object.keys(v).length) v = null;
                answers[f.key] = v;
            });
            try {
                await (this.track.ready || Promise.resolve());
                const res = await fetch(SUBMIT_URL, {
                    method: 'POST', headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                    body: JSON.stringify({ answers, sid: this.track.sid, website_url: this.hp, meta: this.track.meta() })
                });
                const data = await res.json().catch(() => ({}));
                if (res.status === 422) {
                    this.errors = Object.fromEntries(Object.entries(data.errors || {}).map(([k, v]) => [k, v[0]]));
                    const first = vis.find(f => this.errors[f.key]); if (CONV && first) this.step = vis.indexOf(first);
                    this.globalError = data.message || 'Revise as respostas destacadas.';
                    if (first) this.$nextTick(() => document.getElementById('tf-' + first.key)?.scrollIntoView({ behavior: 'smooth', block: 'center' }));
                } else if (!res.ok) {
                    this.globalError = data.message || 'Não foi possível enviar agora. Tente de novo em instantes.';
                } else {
                    this.track.stop();
                    this.done = true;
                    if (CHAT) { this.step = this.visible.length; this.scrollEnd(); } else window.scrollTo({ top: 0, behavior: 'smooth' });
                    this.fireConversions(data.id);
                    if (data.redirect) {
                        this.redirecting = true;
                        setTimeout(() => { window.self !== window.top ? parent.postMessage({ type: 'tf:redirect', url: data.redirect }, '*') : (location.href = data.redirect); }, 1200);
                    }
                }
            } catch (e) {
                this.globalError = 'Sem conexão. Verifique sua internet e tente de novo.';
            } finally { this.sending = false; }
        },

        fireConversions(id) {
            try {
                if (PIXELS.ga4 && window.gtag) gtag('event', 'generate_lead', { form_id: FORM_ID });
                if (PIXELS.meta && window.fbq) fbq('track', 'Lead', { content_name: document.title });
                window.dataLayer = window.dataLayer || []; window.dataLayer.push({ event: 'traceforms_submit', traceforms_form: FORM_ID, traceforms_response: id });
                if (window.self !== window.top) parent.postMessage({ type: 'tf:submitted', form: FORM_ID, response: id }, '*');
            } catch (e) {}
        },

        postHeight() {
            if (window.self === window.top) return;
            parent.postMessage({ type: 'tf:height', height: document.documentElement.scrollHeight }, '*');
        },
    };
}
</script>
<script defer src="{{ asset('vendor/alpine.min.js') }}"></script>
@endif
</body>
</html>
