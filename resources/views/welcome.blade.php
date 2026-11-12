@php
    $signupOpen = (bool) config('traceforms.registration_open');
    $signupUrl = $signupOpen ? route('register') : route('login');
    $templates = \App\Support\FormTemplates::all();
    unset($templates['blank']);
    $types = \App\Support\FieldTypes::grouped();
    $layouts = \App\Models\Form::LAYOUT_LABELS;
@endphp
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>TraceForms — formulários que mostram de onde veio cada resposta</title>
    <meta name="description" content="Crie formulários e pesquisas e veja, junto de cada resposta, a campanha, o anúncio, o dispositivo e em qual pergunta as pessoas desistem.">
    <meta property="og:title" content="TraceForms — formulários que mostram de onde veio cada resposta">
    <meta property="og:description" content="Formulários, pesquisas e inscrições com origem completa de cada resposta: UTMs, anúncios, dispositivo e funil por pergunta.">
    <meta property="og:image" content="{{ asset('img/landing/dashboard.jpg') }}">
    <meta name="theme-color" content="#0e7c66">
    <link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 32 32'><rect width='32' height='32' rx='9' fill='%230e7c66'/><path d='M7 22.5 13 13.5 19 19 25.5 9.5' stroke='white' stroke-width='2.6' fill='none' stroke-linecap='round' stroke-linejoin='round'/><circle cx='25.5' cy='9.5' r='3.1' fill='%23f2b33d'/></svg>">
    <link rel="stylesheet" href="{{ asset('vendor/fonts/fonts.css') }}">
    <link rel="stylesheet" href="{{ asset('vendor/bulma.min.css') }}">
    <link rel="stylesheet" href="{{ asset('vendor/fontawesome/css/all.min.css') }}">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}?v=4">
    <link rel="stylesheet" href="{{ asset('css/site.css') }}?v=1">
</head>
<body class="site">

<header class="s-nav" x-data="{ open: false, scrolled: false }" @scroll.window="scrolled = window.scrollY > 8" :class="{ 'is-open': open, 'is-scrolled': scrolled }">
    <div class="wrap">
        <a href="{{ url('/') }}" class="fp-brand">@include('partials.logo') TraceForms</a>
        <nav class="s-links" aria-label="Seções" @click="open = false">
            <a href="#como-funciona">Como funciona</a>
            <a href="#recursos">Recursos</a>
            <a href="#para-quem">Para quem</a>
            <a href="#modelos">Modelos</a>
            <a href="#duvidas">Dúvidas</a>
        </nav>
        <div class="buttons">
            @auth
                <a href="{{ route('dashboard') }}" class="button is-primary">Ir para o painel</a>
            @else
                @if ($signupOpen)
                    <a href="{{ route('login') }}" class="button is-white">Entrar</a>
                    <a href="{{ $signupUrl }}" class="button is-primary">Criar conta</a>
                @else
                    <a href="{{ route('login') }}" class="button is-primary">Entrar</a>
                @endif
            @endauth
        </div>
        <button class="s-burger" @click="open = !open" :aria-expanded="open" aria-label="Abrir menu"><i class="fa-solid" :class="open ? 'fa-xmark' : 'fa-bars'"></i></button>
    </div>
</header>

<main>
    {{-- Hero com demonstração ao vivo --}}
    <section class="hero">
        <div class="wrap">
            <div>
                <h1>Formulários que mostram de onde veio cada resposta.</h1>
                <p class="lead">Crie formulários, pesquisas e inscrições em minutos. Cada resposta chega com a campanha, o anúncio, o dispositivo e o caminho que a pessoa fez até enviar — e você vê em qual pergunta os outros desistiram.</p>
                <div class="buttons">
                    <a href="{{ $signupUrl }}" class="button is-primary is-medium">{{ $signupOpen ? 'Criar minha conta' : 'Entrar na minha conta' }}</a>
                    <a href="#como-funciona" class="button is-medium is-white">Ver como funciona</a>
                </div>
                <ul class="hero-facts">
                    <li>26 tipos de pergunta</li>
                    <li>4 formatos de formulário</li>
                    <li>Arquivos, assinatura e CPF/CNPJ</li>
                </ul>
            </div>

            <div class="demo" x-data="siteDemo()">
                <div class="demo-form">
                    <template x-if="step < questions.length">
                        <div>
                            <div class="q-num"><span x-text="step + 1"></span> <i class="fa-solid fa-arrow-right is-size-7"></i></div>
                            <h3 x-text="questions[step].q"></h3>
                            <template x-for="(o, i) in questions[step].opts" :key="o">
                                <button type="button" class="demo-opt" :class="{ 'is-on': picked === o }" @click="answer(o)">
                                    <kbd x-text="String.fromCharCode(65 + i)"></kbd><span x-text="o"></span>
                                </button>
                            </template>
                        </div>
                    </template>
                    <template x-if="step >= questions.length">
                        <div class="demo-done">
                            <div class="q-num"><i class="fa-solid fa-check"></i> Enviado</div>
                            <h3>Esse é o rastro que fica guardado em cada resposta.</h3>
                            <p>Ao lado está o que o TraceForms registraria sobre esta sua visita — sem perguntar nada a mais para quem responde.</p>
                            <div class="buttons mt-4">
                                <a href="{{ $signupUrl }}" class="button is-primary">Criar meu primeiro formulário</a>
                                <button type="button" class="button is-white" @click="reset()">Responder de novo</button>
                            </div>
                        </div>
                    </template>
                    <div class="demo-foot">
                        <span>Demonstração · nada é enviado</span>
                        <span class="demo-bar"><span :style="`width:${Math.min(100, step / questions.length * 100)}%`"></span></span>
                    </div>
                </div>
                <aside class="demo-trace" aria-live="polite">
                    <h4>Rastro desta resposta</h4>
                    <p class="sub">Lido agora do seu navegador</p>
                    <ol class="trace-list">
                        <template x-for="(t, i) in trace" :key="i">
                            <li :class="{ 'is-on': t.on }"><span class="k" x-text="t.k"></span><span class="v" x-text="t.v"></span></li>
                        </template>
                    </ol>
                </aside>
            </div>
        </div>
    </section>

    {{-- Como funciona --}}
    <section id="como-funciona">
        <div class="wrap">
            <h2 class="sec">Do formulário à resposta com origem, em três passos.</h2>
            <div class="steps">
                <div class="step"><h3>Monte o formulário</h3><p>Arraste perguntas, escolha um dos quatro formatos e as cores da sua marca. Lógica condicional mostra cada pergunta só para quem precisa responder.</p></div>
                <div class="step"><h3>Divulgue onde quiser</h3><p>Use o link, incorpore no seu site, abra em pop-up ou imprima o QR Code. Gere links com UTM para cada anúncio, post ou e-mail.</p></div>
                <div class="step"><h3>Veja o que funcionou</h3><p>Respostas chegam com canal, campanha, anúncio, dispositivo e cidade. Os relatórios mostram o que converte e onde as pessoas desistem.</p></div>
            </div>
        </div>
    </section>

    {{-- Formatos --}}
    <section>
        <div class="wrap">
            <h2 class="sec">Quatro formatos para o mesmo formulário.</h2>
            <p class="sec-lead">Troque o formato a qualquer momento, sem refazer perguntas. Todos funcionam no celular e podem ser incorporados no seu site.</p>
            <div class="formats">
                @foreach ($layouts as $key => [$label, $desc])
                    <div class="format">
                        <span class="layout-mini is-{{ $key }}" aria-hidden="true"><i></i><b></b><b></b><b></b></span>
                        <h3>{{ $label }}</h3>
                        <p>{{ $desc }}</p>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    {{-- Recursos com telas reais --}}
    <section id="recursos">
        <div class="wrap">
            <div class="feature">
                <div>
                    <h3>Saiba qual campanha traz respostas — não só visitas.</h3>
                    <p>Cada visita é classificada por canal e guarda as UTMs e os IDs de clique dos anúncios. Mesmo com o formulário incorporado no seu site, a origem não se perde.</p>
                    <ul>
                        <li>UTMs e IDs de clique do Google, Meta, TikTok, Microsoft, LinkedIn e outros</li>
                        <li>Primeiro toque e último toque lado a lado</li>
                        <li>Links curtos com UTM para cada post, anúncio ou e-mail</li>
                    </ul>
                </div>
                <div class="shot"><img src="{{ asset('img/landing/acquisition.jpg') }}" alt="Relatório de aquisição com canais e desempenho de cada campanha" loading="lazy" width="1192" height="900"></div>
            </div>
            <div class="feature is-flipped">
                <div>
                    <h3>Descubra em qual pergunta as pessoas desistem.</h3>
                    <p>O funil por pergunta mostra quantas pessoas chegaram a cada etapa, quanto tempo levaram e onde erraram o preenchimento.</p>
                    <ul>
                        <li>Abandono, tempo médio e taxa de erro por pergunta</li>
                        <li>Mapa de calor com os dias e horários em que mais respondem</li>
                        <li>Respostas parciais de quem começou e não enviou</li>
                    </ul>
                </div>
                <div class="shot"><img src="{{ asset('img/landing/funnel.jpg') }}" alt="Funil por pergunta com alcance, abandono e tempo médio" loading="lazy" width="1192" height="900"></div>
            </div>
            <div class="feature">
                <div>
                    <h3>Cada resposta vem com a história completa.</h3>
                    <p>Abra uma resposta e veja de onde a pessoa veio, o aparelho, a cidade, quanto tempo levou em cada pergunta e as visitas anteriores.</p>
                    <ul>
                        <li>Dispositivo, sistema, navegador, tela, idioma e localização</li>
                        <li>Jornada com todas as visitas da mesma pessoa</li>
                        <li>Notas internas, favoritos e botão direto para o WhatsApp</li>
                    </ul>
                </div>
                <div class="shot"><img src="{{ asset('img/landing/response.jpg') }}" alt="Detalhe de uma resposta com origem, dispositivo e engajamento" loading="lazy" width="1192" height="900"></div>
            </div>
            <div class="feature is-flipped">
                <div>
                    <h3>Construtor que salva sozinho.</h3>
                    <p>Arraste perguntas, edite ao lado e veja como fica. As alterações são salvas automaticamente enquanto você trabalha.</p>
                    <ul>
                        <li>Lógica condicional com várias regras</li>
                        <li>Temas, fontes, logotipo e imagem de capa</li>
                        <li>{{ count($templates) }} modelos prontos para começar, além do formulário em branco</li>
                    </ul>
                </div>
                <div class="shot"><img src="{{ asset('img/landing/builder.jpg') }}" alt="Construtor de formulários com lista de perguntas e painel de propriedades" loading="lazy" width="1440" height="900"></div>
            </div>
        </div>
    </section>

    {{-- Tipos de pergunta --}}
    <section>
        <div class="wrap">
            <h2 class="sec">26 tipos de pergunta, incluindo os que o Brasil usa.</h2>
            <p class="sec-lead">CPF e CNPJ validados, endereço preenchido pelo CEP, valores em reais, envio de arquivos e assinatura na tela.</p>
            <div class="types">
                @foreach ($types as $group => $items)
                    <div>
                        <h4>{{ $group }}</h4>
                        <ul>@foreach ($items as $meta)<li><i class="fa-solid {{ $meta['icon'] }}"></i> {{ $meta['label'] }}</li>@endforeach</ul>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    {{-- Casos de uso --}}
    <section id="para-quem" x-data="{ tab: 0 }">
        <div class="wrap">
            <h2 class="sec">Feito para quem precisa saber o que gera resultado.</h2>
            @php
                $cases = [
                    ['Marketing e tráfego pago', 'Pare de adivinhar qual anúncio traz leads.', ['Qual campanha, criativo e termo geram respostas', 'O que trouxe a pessoa (1º toque) e o que a fez responder', 'Conversão por canal, dispositivo e página do site'], ['Captura de leads com UTMs e IDs de clique', 'Links rastreados por anúncio', 'Pixels do GA4, Meta e GTM no envio']],
                    ['Vendas e pré-vendas', 'Receba leads já qualificados e com contexto.', ['De onde veio cada lead e o que respondeu', 'Quem começou o formulário e não terminou', 'Que perguntas fazem as pessoas desistir'], ['Webhook para o seu CRM', 'Aviso por e-mail a cada resposta', 'Respostas parciais para recuperar contatos']],
                    ['RH e recrutamento', 'Candidaturas e pesquisas internas sem planilha.', ['Quais canais trazem mais candidaturas', 'Clima do time com grade de concordância', 'Prioridades do time por ranking'], ['Envio de currículo e assinatura', 'CPF validado e endereço pelo CEP', 'Acesso restrito por formulário para gestores']],
                    ['Eventos', 'Inscrições que fecham sozinhas.', ['Quais divulgações lotaram o evento', 'Horários em que as pessoas mais se inscrevem', 'Cidade e dispositivo dos inscritos'], ['Encerramento por data ou limite de vagas', 'QR Code já com UTM para materiais impressos', 'Uma inscrição por pessoa']],
                    ['Pesquisa e experiência do cliente', 'NPS, satisfação e pesquisas que as pessoas terminam.', ['NPS com promotores, neutros e detratores', 'Médias, medianas e distribuição por pergunta', 'Onde a pesquisa cansa quem responde'], ['Formatos “Uma por vez” e Chat', 'Matriz, ranking, escala e estrelas', 'Exportação em CSV pronta para o Excel']],
                    ['Agências', 'Uma conta por cliente, com o acesso certo para cada pessoa.', ['Resultados de cada cliente separados', 'Campanhas comparadas entre formulários', 'Quem alterou o quê, e quando'], ['Várias contas no mesmo login', 'Cinco níveis de acesso e acesso por formulário', 'Histórico de alterações']],
                ];
            @endphp
            <div class="cases">
                <div class="case-tabs" role="tablist">
                    @foreach ($cases as $i => $c)
                        <button type="button" role="tab" :aria-selected="tab === {{ $i }}" :class="{ 'is-on': tab === {{ $i }} }" @click="tab = {{ $i }}">{{ $c[0] }}</button>
                    @endforeach
                </div>
                @foreach ($cases as $i => $c)
                    <div class="case-panel" role="tabpanel" x-show="tab === {{ $i }}" @if($i) x-cloak @endif>
                        <h3>{{ $c[1] }}</h3>
                        <p class="lead">{{ $c[0] }}</p>
                        <div class="case-grid">
                            <div><h4>O que você descobre</h4><ul>@foreach ($c[2] as $x)<li>{{ $x }}</li>@endforeach</ul></div>
                            <div><h4>O que ajuda</h4><ul>@foreach ($c[3] as $x)<li>{{ $x }}</li>@endforeach</ul></div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    {{-- Modelos --}}
    <section id="modelos">
        <div class="wrap">
            <h2 class="sec">Comece por um modelo pronto.</h2>
            <p class="sec-lead">Todos podem ser editados: troque perguntas, cores e formato.</p>
            <div class="tpls">
                @foreach ($templates as $key => $t)
                    <a href="{{ $signupUrl }}" class="tpl-card">
                        <span class="tpl-icon" style="background: {{ $t['color'] }}"><i class="fa-solid {{ $t['icon'] }}"></i></span>
                        <h3>{{ $t['name'] }}</h3>
                        <p>{{ $t['description'] }}</p>
                        <small>{{ count($t['fields']) }} perguntas · {{ $layouts[$t['layout']][0] }}</small>
                    </a>
                @endforeach
            </div>
        </div>
    </section>

    {{-- Integrações e privacidade --}}
    <section>
        <div class="wrap">
            <h2 class="sec">Leve as respostas para onde você trabalha.</h2>
            <div class="duo">
                <div>
                    <h3>Integrações</h3>
                    <dl>
                        <dt>Webhook</dt><dd>Cada resposta e todo o rastro vão para o seu CRM, Zapier, Make ou n8n.</dd>
                        <dt>Pixels</dt><dd>Conversão no Google Analytics 4, Pixel da Meta e Google Tag Manager.</dd>
                        <dt>E-mail</dt><dd>Aviso a cada resposta, com a origem e o dispositivo.</dd>
                        <dt>CSV</dt><dd>Exportação com respostas e colunas de rastreamento, pronta para o Excel.</dd>
                        <dt>No seu site</dt><dd>Incorporado, em pop-up ou por link, com altura automática.</dd>
                    </dl>
                </div>
                <div>
                    <h3>Privacidade</h3>
                    <dl>
                        <dt>IP</dt><dd>Coleta opcional por formulário, com anonimização.</dd>
                        <dt>Consentimento</dt><dd>Pergunta própria de consentimento, registrada com a resposta.</dd>
                        <dt>Retenção</dt><dd>Visitas sem resposta apagadas automaticamente após o prazo que você definir.</dd>
                        <dt>Arquivos</dt><dd>Guardados de forma privada; só quem tem acesso às respostas baixa.</dd>
                        <dt>Sem terceiros</dt><dd>O painel e os formulários carregam fontes e scripts do próprio servidor.</dd>
                    </dl>
                </div>
            </div>
        </div>
    </section>

    {{-- Dúvidas --}}
    <section id="duvidas">
        <div class="wrap">
            <h2 class="sec">Dúvidas frequentes</h2>
            <div class="faq">
                <details><summary>O que exatamente o TraceForms registra de quem responde?</summary>
                    <p>A origem da visita (UTMs, ID de clique do anúncio, site de referência e página de entrada), o tipo de aparelho, sistema, navegador, tela, idioma, fuso e cidade, além do tempo gasto em cada pergunta. Nada disso é perguntado à pessoa: vem da própria visita. A demonstração no topo desta página mostra os dados da sua visita, lidos só no seu navegador.</p></details>
                <details><summary>A origem funciona se eu colocar o formulário no meu site?</summary>
                    <p>Sim. O script de incorporação repassa ao formulário as UTMs, os IDs de clique, a página onde ele está e o primeiro toque do visitante no seu site — informações que um iframe comum perderia.</p></details>
                <details><summary>Como fica a LGPD?</summary>
                    <p>Você decide por formulário se coleta o IP, se ele é anonimizado e se respostas parciais são salvas, e define por quanto tempo visitas sem resposta ficam guardadas. Há um tipo de pergunta de consentimento. Informe na sua política de privacidade o que você coleta e para quê.</p></details>
                <details><summary>Posso receber arquivos e assinaturas?</summary>
                    <p>Sim. Você define os tipos aceitos, o tamanho máximo e quantos arquivos por pergunta. Os arquivos ficam privados e só membros com acesso às respostas conseguem baixá-los.</p></details>
                <details><summary>Consigo mandar as respostas para o meu CRM?</summary>
                    <p>Sim, pelo webhook: cada resposta é enviada com todos os dados de rastreamento para o endereço que você configurar, inclusive Zapier, Make e n8n. Também é possível exportar tudo em CSV.</p></details>
                <details><summary>Várias pessoas da equipe podem usar a mesma conta?</summary>
                    <p>Sim. Há cinco níveis de acesso — de quem só vê os painéis até o proprietário — e é possível limitar uma pessoa a formulários específicos. Todas as alterações importantes ficam registradas no histórico.</p></details>
            </div>
        </div>
    </section>

    <section style="border-top:0;padding-top:0">
        <div class="wrap">
            <div class="cta-band">
                <div>
                    <h2>Seu próximo formulário já pode mostrar de onde vêm as respostas.</h2>
                    <p>{{ $signupOpen ? 'Crie a conta e publique o primeiro em poucos minutos.' : 'Entre na sua conta e publique o primeiro em poucos minutos.' }}</p>
                </div>
                <a href="{{ $signupUrl }}" class="button is-medium">{{ $signupOpen ? 'Criar minha conta' : 'Entrar na minha conta' }}</a>
            </div>
        </div>
    </section>
</main>

<footer class="s-footer">
    <div class="wrap">
        <div class="cols">
            <div>
                <a href="{{ url('/') }}" class="fp-brand" style="padding:0">@include('partials.logo') TraceForms</a>
                <p class="is-size-7 has-text-muted mt-3" style="max-width:26em">Formulários, pesquisas e inscrições com a origem de cada resposta.</p>
            </div>
            <div><h4>Produto</h4><ul><li><a href="#como-funciona">Como funciona</a></li><li><a href="#recursos">Recursos</a></li><li><a href="#modelos">Modelos</a></li></ul></div>
            <div><h4>Para quem</h4><ul><li><a href="#para-quem">Marketing</a></li><li><a href="#para-quem">RH e recrutamento</a></li><li><a href="#para-quem">Agências</a></li></ul></div>
            <div><h4>Conta</h4><ul><li><a href="{{ route('login') }}">Entrar</a></li>@if ($signupOpen)<li><a href="{{ $signupUrl }}">Criar conta</a></li>@endif<li><a href="#duvidas">Dúvidas</a></li></ul></div>
        </div>
        <div class="legal"><span>© {{ date('Y') }} TraceForms</span><span>Feito no Brasil</span></div>
    </div>
</footer>

<script>
function siteDemo() {
    const ua = navigator.userAgent;
    const os = /iPhone|iPad/.test(ua) ? 'iOS' : /Android/.test(ua) ? 'Android' : /Windows/.test(ua) ? 'Windows' : /Mac OS X/.test(ua) ? 'macOS' : /Linux/.test(ua) ? 'Linux' : 'Sistema não identificado';
    const browser = /Edg\//.test(ua) ? 'Edge' : /OPR\//.test(ua) ? 'Opera' : /SamsungBrowser/.test(ua) ? 'Samsung Internet' : /Firefox\//.test(ua) ? 'Firefox' : /Chrome\//.test(ua) ? 'Chrome' : /Safari\//.test(ua) ? 'Safari' : 'Navegador';
    const mobile = /Mobi|Android|iPhone/.test(ua), tablet = /iPad|Tablet/.test(ua);
    const q = new URLSearchParams(location.search);
    const click = ['gclid', 'fbclid', 'ttclid', 'msclkid'].find(k => q.get(k));
    const ref = document.referrer ? new URL(document.referrer).hostname.replace(/^www\./, '') : '';
    let tz = ''; try { tz = Intl.DateTimeFormat().resolvedOptions().timeZone; } catch (e) {}
    const origin = q.get('utm_source') ? `${q.get('utm_source')} / ${q.get('utm_medium') || '—'}${q.get('utm_campaign') ? ' · ' + q.get('utm_campaign') : ''}`
        : (click ? 'Anúncio (' + click + ')' : (ref && ref !== location.hostname ? 'Veio de ' + ref : 'Acesso direto, sem UTM'));
    let returning = false; try { returning = !!localStorage.getItem('tf_demo_seen'); localStorage.setItem('tf_demo_seen', '1'); } catch (e) {}
    const fmt = (ms) => (ms / 1000).toFixed(1).replace('.', ',') + 's';

    return {
        questions: [
            { q: 'O que você mais quer descobrir com seus formulários?', opts: ['De onde vêm meus leads', 'Onde as pessoas desistem', 'Qual campanha converte mais'] },
            { q: 'Quantas respostas você recebe por mês?', opts: ['Até 100', 'De 100 a 1.000', 'Mais de 1.000'] },
            { q: 'Onde seus formulários costumam ficar?', opts: ['No meu site', 'Em links nas redes sociais', 'Em anúncios'] },
        ],
        step: 0, picked: null, t0: 0, start: 0, trace: [],
        base() {
            return [
                { k: 'Visita', v: returning ? 'Recorrente — você já esteve aqui' : 'Primeira visita', on: false },
                { k: 'Origem', v: origin, on: false },
                { k: 'Dispositivo', v: (tablet ? 'Tablet' : mobile ? 'Celular' : 'Computador') + ' · ' + os + ' · ' + browser, on: false },
                { k: 'Tela, idioma e fuso', v: `${screen.width}×${screen.height} · ${navigator.language} · ${tz}`, on: false },
            ];
        },
        init() { this.reset(); },
        reset() {
            this.step = 0; this.picked = null; this.trace = this.base();
            this.questions.forEach((x, i) => this.trace.push({ k: 'Pergunta ' + (i + 1), v: 'aguardando resposta', on: false }));
            this.trace.push({ k: 'Envio', v: 'aguardando', on: false });
            const reduce = matchMedia('(prefers-reduced-motion: reduce)').matches;
            this.base().forEach((_, i) => setTimeout(() => this.trace[i].on = true, reduce ? 0 : 350 + i * 380));
            this.start = this.t0 = performance.now();
        },
        answer(o) {
            if (this.picked) return;
            this.picked = o;
            const now = performance.now(), idx = 4 + this.step;
            this.trace[idx] = { k: 'Pergunta ' + (this.step + 1) + ' · ' + fmt(now - this.t0), v: o, on: true };
            setTimeout(() => {
                this.step++; this.picked = null; this.t0 = performance.now();
                if (this.step >= this.questions.length) {
                    this.trace[this.trace.length - 1] = { k: 'Envio', v: 'Respondido em ' + fmt(performance.now() - this.start), on: true };
                }
            }, 420);
        },
    };
}
</script>
<script defer src="{{ asset('vendor/alpine.min.js') }}"></script>
</body>
</html>
