@extends('layouts.app')
@section('title', $form->title)
@section('crumb')<a href="{{ route('forms.index') }}">Formulários</a> / Compartilhar @endsection
@section('actions')@include('partials.form-actions')@endsection

@section('content')
@include('partials.form-header')
@php
    $url = $form->publicUrl();
    $embed = '<div data-traceforms="'.$form->public_id.'"></div>'."\n".'<script src="'.url('embed.js').'" async></script>';
    $popup = '<div data-traceforms="'.$form->public_id.'" data-popup="true" data-button="Fale conosco" data-color="'.$form->themeValue('primary').'"></div>'."\n".'<script src="'.url('embed.js').'" async></script>';
@endphp

@unless ($form->isPublished())
    <div class="notification is-warning is-light"><i class="fa-solid fa-triangle-exclamation mr-2"></i> Este formulário está <strong>{{ mb_strtolower($form->statusLabel()) }}</strong>. Publique-o para que o link e a incorporação funcionem.</div>
@endunless

<div class="columns is-variable is-4" x-data="{ tab: 'link' }">
    <div class="column is-7">
        <div class="box">
            <div class="tabs is-small mb-4"><ul>
                <li :class="{ 'is-active': tab === 'link' }"><a @click="tab = 'link'"><i class="fa-solid fa-link mr-1"></i> Link</a></li>
                <li :class="{ 'is-active': tab === 'embed' }"><a @click="tab = 'embed'"><i class="fa-solid fa-code mr-1"></i> Incorporar no site</a></li>
                <li :class="{ 'is-active': tab === 'popup' }"><a @click="tab = 'popup'"><i class="fa-regular fa-window-restore mr-1"></i> Pop-up</a></li>
                <li :class="{ 'is-active': tab === 'qr' }"><a @click="tab = 'qr'"><i class="fa-solid fa-qrcode mr-1"></i> QR Code</a></li>
            </ul></div>

            <div x-show="tab === 'link'">
                <label class="label">Link público</label>
                <div class="field has-addons">
                    <div class="control is-expanded"><input class="input mono" readonly value="{{ $url }}" @focus="$el.select()"></div>
                    <div class="control"><button class="button is-primary" @click="FP.copy(@js($url), 'Link copiado')"><i class="fa-regular fa-copy mr-2"></i> Copiar</button></div>
                </div>
                <p class="help">Qualquer parâmetro na URL é registrado. Adicione UTMs manualmente ou crie <a href="#links">links rastreados</a> ao lado.</p>
            </div>

            <div x-show="tab === 'embed'" x-cloak>
                <p class="is-size-7 mb-3">Cole onde o formulário deve aparecer. O script repassa automaticamente <strong>URL da página, referrer, UTMs, click IDs (gclid, fbclid…) e o 1º toque</strong> do visitante no seu site — dados que um iframe comum perde.</p>
                <div class="code-box">{{ $embed }}<button class="button is-small copy" @click="FP.copy(@js($embed), 'Código copiado')"><i class="fa-regular fa-copy"></i></button></div>
                <p class="help mt-3">Opções: <span class="mono">data-height="700"</span>, <span class="mono">data-transparent="true"</span>. A altura se ajusta sozinha. Escute <span class="mono">traceforms:submit</span> ou o evento <span class="mono">traceforms_submit</span> no dataLayer para suas próprias conversões.</p>
            </div>

            <div x-show="tab === 'popup'" x-cloak>
                <p class="is-size-7 mb-3">Mostra um botão que abre o formulário em uma janela modal.</p>
                <div class="code-box">{{ $popup }}<button class="button is-small copy" @click="FP.copy(@js($popup), 'Código copiado')"><i class="fa-regular fa-copy"></i></button></div>
            </div>

            <div x-show="tab === 'qr'" x-cloak class="has-text-centered">
                <p class="is-size-7 mb-3">Para materiais impressos e eventos. Já inclui <span class="mono">utm_medium=qr</span> para você medir o offline.</p>
                @php $qrUrl = $form->publicUrl(['utm_source' => 'qrcode', 'utm_medium' => 'qr']); @endphp
                <img src="https://api.qrserver.com/v1/create-qr-code/?size=240x240&margin=8&data={{ urlencode($qrUrl) }}" alt="QR Code" width="240" height="240" style="border-radius:12px;border:1px solid var(--fp-border)">
                <p class="mt-2"><a href="https://api.qrserver.com/v1/create-qr-code/?size=1000x1000&margin=20&format=png&data={{ urlencode($qrUrl) }}" target="_blank" class="button is-small"><i class="fa-solid fa-download mr-2"></i> Baixar em alta resolução</a></p>
            </div>
        </div>

        <div class="box" id="links">
            <div class="box-title"><i class="fa-solid fa-link has-text-primary"></i> Links rastreados
                <span class="right has-text-muted">Links curtos com UTMs — um por canal ou anúncio</span></div>
            @if ($form->trackingLinks->isEmpty())
                <p class="has-text-muted is-size-7 py-3">Nenhum link ainda. Crie um para cada lugar onde você divulga o formulário (bio do Instagram, newsletter, anúncio…) e compare os resultados.</p>
            @else
                <div class="table-wrap">
                <table class="table is-fullwidth is-hoverable">
                    <thead><tr><th>Nome</th><th>UTMs</th><th class="has-text-right">Cliques</th><th class="has-text-right">Visitas</th><th class="has-text-right">Envios</th><th></th></tr></thead>
                    <tbody>
                    @foreach ($form->trackingLinks as $l)
                        <tr>
                            <td><strong>{{ $l->name }}</strong><br><a class="is-size-7 mono" @click.prevent="FP.copy(@js($l->shortUrl()), 'Link copiado')" href="{{ $l->shortUrl() }}" title="Copiar">{{ str_replace(['https://', 'http://'], '', $l->shortUrl()) }} <i class="fa-regular fa-copy"></i></a></td>
                            <td class="is-size-7">
                                <span class="tag is-light">{{ $l->utm_source }}</span> <span class="tag is-light">{{ $l->utm_medium }}</span>
                                @if ($l->utm_campaign)<span class="tag is-primary is-light">{{ $l->utm_campaign }}</span>@endif
                                @if ($l->utm_content)<span class="tag is-light">{{ $l->utm_content }}</span>@endif
                            </td>
                            <td class="has-text-right">{{ number_format($l->clicks, 0, ',', '.') }}</td>
                            <td class="has-text-right">{{ number_format($l->views_count, 0, ',', '.') }}</td>
                            <td class="has-text-right has-text-weight-semibold">{{ number_format($l->submits_count, 0, ',', '.') }}</td>
                            <td class="has-text-right nowrap">
                                <a href="{{ route('reports.show', [$form, 'acquisition']) }}?tracking_link_id={{ $l->id }}" class="button is-small is-white" title="Ver relatório"><i class="fa-solid fa-chart-line"></i></a>
                                @can('links.manage', $form)
                                    <form method="POST" action="{{ route('links.destroy', [$form, $l]) }}" class="is-inline" onsubmit="return confirm('Remover este link? Ele deixará de redirecionar.')">@csrf @method('DELETE')
                                        <button class="button is-small is-white" title="Remover"><i class="fa-regular fa-trash-can"></i></button></form>
                                @endcan
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
                </div>
            @endif
        </div>
    </div>

    <div class="column is-5">
        @can('links.manage', $form)
        <div class="box" x-data="utmBuilder()">
            <div class="box-title"><i class="fa-solid fa-wand-magic-sparkles has-text-primary"></i> Gerador de link com UTM</div>
            <form method="POST" action="{{ route('links.store', $form) }}">
                @csrf
                <div class="field">
                    <label class="label is-small">Atalhos</label>
                    <div class="buttons are-small">
                        <template x-for="p in presets" :key="p.n">
                            <button type="button" class="button is-light" @click="apply(p)"><i class="mr-1" :class="p.i"></i> <span x-text="p.n"></span></button>
                        </template>
                    </div>
                </div>
                <div class="field"><label class="label is-small">Nome do link *</label><input class="input is-small" name="name" x-model="name" placeholder="Ex.: Bio do Instagram" required></div>
                <div class="columns is-variable is-2 mb-0">
                    <div class="column field mb-0"><label class="label is-small">Origem (utm_source) *</label><input class="input is-small mono" name="utm_source" x-model="source" placeholder="instagram" required></div>
                    <div class="column field"><label class="label is-small">Mídia (utm_medium) *</label><input class="input is-small mono" name="utm_medium" x-model="medium" placeholder="social" required list="mediums">
                        <datalist id="mediums"><option value="cpc"><option value="social"><option value="paid_social"><option value="email"><option value="referral"><option value="affiliate"><option value="display"><option value="sms"><option value="qr"></datalist></div>
                </div>
                <div class="field"><label class="label is-small">Campanha (utm_campaign)</label><input class="input is-small mono" name="utm_campaign" x-model="campaign" placeholder="black_friday_2026"></div>
                <div class="columns is-variable is-2">
                    <div class="column field mb-0"><label class="label is-small">Conteúdo</label><input class="input is-small mono" name="utm_content" x-model="content" placeholder="video_a"></div>
                    <div class="column field"><label class="label is-small">Termo</label><input class="input is-small mono" name="utm_term" x-model="term" placeholder="palavra-chave"></div>
                </div>
                <label class="label is-small">Prévia da URL completa</label>
                <div class="code-box is-size-8 mb-3" x-text="preview"></div>
                <div class="buttons">
                    <button type="button" class="button is-small" @click="FP.copy(preview, 'URL copiada')"><i class="fa-regular fa-copy mr-1"></i> Copiar URL</button>
                    <button class="button is-small is-primary"><i class="fa-solid fa-plus mr-1"></i> Salvar como link curto</button>
                </div>
            </form>
        </div>
        @endcan
        <div class="box">
            <div class="box-title"><i class="fa-solid fa-circle-info has-text-primary"></i> O que coletamos automaticamente</div>
            <ul class="is-size-7" style="line-height:1.9">
                <li><i class="fa-solid fa-check has-text-success mr-2"></i>UTMs (source, medium, campaign, term, content, id)</li>
                <li><i class="fa-solid fa-check has-text-success mr-2"></i>Click IDs: Google (gclid, gbraid, wbraid), Meta (fbclid), TikTok, Microsoft, LinkedIn, X…</li>
                <li><i class="fa-solid fa-check has-text-success mr-2"></i>Atribuição de primeiro e último toque (90 dias)</li>
                <li><i class="fa-solid fa-check has-text-success mr-2"></i>Canal (pago/orgânico/social/e-mail…), referrer e página de entrada</li>
                <li><i class="fa-solid fa-check has-text-success mr-2"></i>Dispositivo, marca, modelo, sistema, navegador, tela, idioma, fuso</li>
                <li><i class="fa-solid fa-check has-text-success mr-2"></i>País/região/cidade (via CDN) e IP (com anonimização opcional)</li>
                <li><i class="fa-solid fa-check has-text-success mr-2"></i>Tempo por pergunta, abandono, erros, trocas de aba, visitas recorrentes</li>
            </ul>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
function utmBuilder() {
    const BASE = @json($url);
    return {
        name: '', source: '', medium: '', campaign: '', content: '', term: '',
        presets: [
            { n: 'Instagram bio', i: 'fa-brands fa-instagram', s: 'instagram', m: 'social', c: 'bio' },
            { n: 'Meta Ads', i: 'fa-brands fa-meta', s: 'facebook', m: 'paid_social', c: '' },
            { n: 'Google Ads', i: 'fa-brands fa-google', s: 'google', m: 'cpc', c: '' },
            { n: 'Newsletter', i: 'fa-regular fa-envelope', s: 'newsletter', m: 'email', c: '' },
            { n: 'WhatsApp', i: 'fa-brands fa-whatsapp', s: 'whatsapp', m: 'sms', c: '' },
            { n: 'LinkedIn', i: 'fa-brands fa-linkedin', s: 'linkedin', m: 'social', c: '' },
        ],
        apply(p) { this.name = this.name || p.n; this.source = p.s; this.medium = p.m; if (p.c) this.campaign = p.c; },
        norm(v) { return String(v || '').trim().toLowerCase().replace(/\s+/g, '_'); },
        get preview() {
            const u = new URL(BASE);
            [['utm_source', this.source], ['utm_medium', this.medium], ['utm_campaign', this.campaign], ['utm_term', this.term], ['utm_content', this.content]]
                .forEach(([k, v]) => { if (v) u.searchParams.set(k, this.norm(v)); });
            return u.toString();
        },
    };
}
</script>
@endpush
