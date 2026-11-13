@extends('layouts.app')
@section('title', $submission->headline($form))
@section('crumb')<a href="{{ route('forms.index') }}">Formulários</a> / <a href="{{ route('submissions.index', $form) }}">{{ \Illuminate\Support\Str::limit($form->title, 40) }}</a> / Resposta @endsection
@section('actions')
    <div class="buttons has-addons mb-0">
        <a class="button is-small" @if ($prev) href="{{ route('submissions.show', [$form, $prev]) }}" @else disabled @endif title="Mais recente"><i class="fa-solid fa-chevron-left"></i></a>
        <a class="button is-small" @if ($next) href="{{ route('submissions.show', [$form, $next]) }}" @else disabled @endif title="Mais antiga"><i class="fa-solid fa-chevron-right"></i></a>
    </div>
    @can('responses.manage', $form)
        <form method="POST" action="{{ route('submissions.update', [$form, $submission]) }}" class="is-inline">@csrf @method('PATCH')
            <input type="hidden" name="starred" value="{{ $submission->starred ? 0 : 1 }}">
            <button class="button is-small"><i class="{{ $submission->starred ? 'fa-solid fa-star has-text-warning' : 'fa-regular fa-star' }} mr-2"></i>{{ $submission->starred ? 'Favorita' : 'Favoritar' }}</button>
        </form>
        <form method="POST" action="{{ route('submissions.update', [$form, $submission]) }}" class="is-inline">@csrf @method('PATCH')
            <input type="hidden" name="is_spam" value="{{ $submission->is_spam ? 0 : 1 }}">
            <button class="button is-small"><i class="fa-solid {{ $submission->is_spam ? 'fa-inbox' : 'fa-ban' }} mr-2"></i>{{ $submission->is_spam ? 'Não é spam' : 'Marcar spam' }}</button>
        </form>
        <form method="POST" action="{{ route('submissions.destroy', [$form, $submission]) }}" class="is-inline" onsubmit="return confirm('Excluir esta resposta definitivamente?')">@csrf @method('DELETE')
            <button class="button is-small is-danger is-light" title="Excluir"><i class="fa-regular fa-trash-can"></i></button>
        </form>
    @endcan
@endsection

@section('content')
@php
    use App\Services\Reports\ReportService;
    use App\Services\Tracking\ChannelClassifier;
    use App\Services\Tracking\TrafficParser;
    use App\Http\Controllers\ReportController;
    $tz = $account->timezone;
    $row = fn ($label, $value, $mono = false) => $value === null || $value === '' ? '' : '<dt>'.e($label).'</dt><dd class="'.($mono ? 'mono' : '').'">'.e($value).'</dd>';
@endphp

@if ($submission->is_spam)
    <div class="notification is-danger is-light"><i class="fa-solid fa-ban mr-2"></i> Marcada como spam: <strong>{{ $submission->spam_reason }}</strong>. Não entra nos relatórios.</div>
@endif

<div class="columns is-variable is-4">
    <div class="column is-7">
        <div class="box">
            <div class="is-flex is-justify-content-space-between mb-2">
                <span class="tag is-light"><i class="fa-regular fa-clock mr-1"></i> {{ $submission->submitted_at->setTimezone($tz)->translatedFormat('d \d\e F \d\e Y, H:i') }}</span>
                <span class="is-size-8 has-text-muted mono">{{ $submission->uuid }}</span>
            </div>
            @foreach ($form->fields as $f)
                @continue($f->type === 'statement')
                @continue(! array_key_exists($f->key, $submission->data))
                <div class="answer">
                    <div class="q"><i class="fa-solid {{ $f->icon() }}"></i> {{ $f->label }}
                        @if ($s && ($ms = $s->field_stats[$f->key]['ms'] ?? null))<span class="tag is-light is-size-8 ml-auto" title="Tempo nesta pergunta">{{ ReportService::duration($ms) }}</span>@endif
                    </div>
                    @php $v = $submission->data[$f->key]; @endphp
                    <div class="a">
                        @if ($f->type === 'rating' && $v)
                            @for ($i = 1; $i <= (int) $f->setting('max', 5); $i++)<i class="fa-solid fa-star {{ $i <= $v ? 'has-text-warning' : 'has-text-grey-lighter' }}"></i>@endfor
                            <span class="has-text-muted is-size-7 ml-1">{{ $v }}/{{ $f->setting('max', 5) }}</span>
                        @elseif ($f->type === 'nps' && $v !== null)
                            <span class="tag is-medium {{ $v >= 9 ? 'is-success' : ($v >= 7 ? 'is-warning' : 'is-danger') }}">{{ $v }}</span>
                            <span class="has-text-muted is-size-7 ml-1">{{ $v >= 9 ? 'Promotor' : ($v >= 7 ? 'Neutro' : 'Detrator') }}</span>
                        @elseif (in_array($f->type, ['file', 'signature'], true) && is_array($v))
                            <div class="file-grid">
                                @foreach ($v as $file)
                                    @php $url = route('submissions.file', [$form, $file['id'] ?? '']); $img = str_starts_with($file['mime'] ?? '', 'image/'); @endphp
                                    <a href="{{ $url }}{{ $img ? '' : '?download=1' }}" target="_blank" class="file-chip {{ $f->type === 'signature' ? 'is-signature' : '' }}">
                                        @if ($img)<img src="{{ $url }}" alt="{{ $file['name'] ?? '' }}" loading="lazy">@else<i class="fa-regular fa-file-lines"></i>@endif
                                        <span><strong class="truncate">{{ $file['name'] ?? 'arquivo' }}</strong><small>{{ \App\Models\Upload::humanSize((int) ($file['size'] ?? 0)) }} · baixar</small></span>
                                    </a>
                                @endforeach
                            </div>
                        @elseif ($f->type === 'address' && is_array($v))
                            {{ $f->display($v) }}
                            <a class="is-size-7 ml-2" target="_blank" rel="noopener" href="https://www.google.com/maps/search/?api=1&query={{ urlencode($f->display($v)) }}"><i class="fa-solid fa-map-location-dot"></i> Ver no mapa</a>
                        @elseif ($f->type === 'matrix' && is_array($v))
                            <table class="table is-narrow is-size-7 mb-0">
                                @foreach ($f->matrixRows() as $row)<tr><td>{{ $row }}</td><td class="has-text-weight-semibold">{{ $v[$row] ?? '—' }}</td></tr>@endforeach
                            </table>
                        @elseif ($f->type === 'ranking' && is_array($v))
                            <ol class="rank-list">@foreach ($v as $x)<li>{{ $x }}</li>@endforeach</ol>
                        @elseif (in_array($f->type, ['currency', 'cpf_cnpj', 'slider'], true))
                            <span class="is-tabular">{{ $f->display($v) }}</span>
                        @elseif (is_array($v))
                            @foreach ($v as $x)<span class="tag is-primary is-light mr-1 mb-1">{{ is_scalar($x) ? $x : '' }}</span>@endforeach
                        @elseif ($f->type === 'email' && $v)
                            <a href="mailto:{{ $v }}">{{ $v }}</a>
                        @elseif ($f->type === 'phone' && $v)
                            {{ $v }} <a href="https://wa.me/{{ preg_replace('/\D/', '', $v) }}" target="_blank" class="tag is-success is-light ml-2"><i class="fa-brands fa-whatsapp mr-1"></i> WhatsApp</a>
                        @elseif ($f->type === 'url' && $v)
                            <a href="{{ $v }}" target="_blank" rel="noopener nofollow">{{ $v }}</a>
                        @else
                            <span class="text">{{ $f->display($v) }}</span>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>

        @can('responses.manage', $form)
            <div class="box">
                <div class="box-title"><i class="fa-regular fa-note-sticky has-text-primary"></i> Notas internas</div>
                <form method="POST" action="{{ route('submissions.update', [$form, $submission]) }}">@csrf @method('PATCH')
                    <textarea class="textarea" name="notes" rows="3" placeholder="Visível apenas para a equipe. Ex.: contato feito em 12/11, interessado no plano anual.">{{ $submission->notes }}</textarea>
                    <button class="button is-small is-primary mt-2">Salvar nota</button>
                </form>
            </div>
        @elseif ($submission->notes)
            <div class="box"><div class="box-title">Notas internas</div><p class="is-size-7">{{ $submission->notes }}</p></div>
        @endcan
    </div>

    <div class="column is-5">
        @if (! $s)
            <div class="box has-text-muted is-size-7">Sem dados de rastreamento para esta resposta.</div>
        @else
            <div class="box">
                <div class="box-title"><i class="fa-solid fa-route has-text-primary"></i> Origem</div>
                <div class="mb-3">
                    <span class="tag is-medium" style="background: {{ ChannelClassifier::COLORS[$s->channel] ?? '#ccc' }}22; color: {{ ChannelClassifier::COLORS[$s->channel] ?? '#555' }}">{{ ChannelClassifier::label($s->channel) }}</span>
                    @if ($s->click_id_type)<span class="tag is-medium is-light">{{ TrafficParser::clickLabel($s->click_id_type) }}</span>@endif
                    @if ($s->trackingLink)<span class="tag is-medium is-primary is-light"><i class="fa-solid fa-link mr-1"></i> {{ $s->trackingLink->name }}</span>@endif
                </div>
                <dl class="kv">
                    {!! $row('utm_source', $s->utm_source, true) !!}
                    {!! $row('utm_medium', $s->utm_medium, true) !!}
                    {!! $row('utm_campaign', $s->utm_campaign, true) !!}
                    {!! $row('utm_content', $s->utm_content, true) !!}
                    {!! $row('utm_term', $s->utm_term, true) !!}
                    {!! $row('utm_id', $s->utm_id, true) !!}
                    {!! $row($s->click_id_type ?? 'Click ID', $s->click_id ? \Illuminate\Support\Str::limit($s->click_id, 40) : null, true) !!}
                    {!! $row('Referrer', $s->referrer) !!}
                    {!! $row('Página de entrada', $s->landing_url ? \Illuminate\Support\Str::limit($s->landing_url, 90) : null) !!}
                    {!! $row('Incorporado em', $s->parent_url ? \Illuminate\Support\Str::limit($s->parent_url, 90) : null) !!}
                </dl>
                @if ($s->first_touch && (($s->first_touch['utm_source'] ?? null) || ($s->first_touch['referrer'] ?? null)))
                    <div class="notification is-light is-size-7 p-3 mt-3">
                        <strong><i class="fa-solid fa-flag mr-1"></i> Primeiro toque</strong>
                        @if (! empty($s->first_touch['ts']))<span class="has-text-muted">· {{ \Carbon\Carbon::parse($s->first_touch['ts'])->setTimezone($tz)->format('d/m/Y H:i') }}</span>@endif<br>
                        {{ $s->first_touch['utm_source'] ?? 'direto' }} / {{ $s->first_touch['utm_medium'] ?? '—' }}
                        @if (! empty($s->first_touch['utm_campaign'])) · <strong>{{ $s->first_touch['utm_campaign'] }}</strong>@endif
                        @if (! empty($s->first_touch['referrer']))<br><span class="has-text-muted">via {{ \Illuminate\Support\Str::limit($s->first_touch['referrer'], 60) }}</span>@endif
                    </div>
                @endif
                @if ($s->hidden_fields)
                    <p class="is-size-8 has-text-muted has-text-weight-bold mt-3 mb-1">Campos ocultos</p>
                    <dl class="kv">@foreach ($s->hidden_fields as $k => $v){!! $row($k, $v, true) !!}@endforeach</dl>
                @endif
            </div>

            <div class="box">
                <div class="box-title"><i class="fa-solid {{ $s->deviceIcon() }} has-text-primary"></i> Dispositivo e local</div>
                <dl class="kv">
                    {!! $row('Dispositivo', trim(ReportController::deviceLabel($s->device_type).' '.($s->device_brand ?? '').' '.($s->device_model ?? ''))) !!}
                    {!! $row('Sistema', trim(($s->os_name ?? '').' '.($s->os_version ?? ''))) !!}
                    {!! $row('Navegador', trim(($s->browser_name ?? '').' '.($s->browser_version ?? ''))) !!}
                    {!! $row('Tela', $s->screenLabel() ? $s->screenLabel().' @'.rtrim(rtrim(number_format((float) $s->pixel_ratio, 2), '0'), '.').'x' : null) !!}
                    {!! $row('Janela', $s->viewport_width ? $s->viewport_width.'×'.$s->viewport_height : null) !!}
                    {!! $row('Toque', $s->touch === null ? null : ($s->touch ? 'Sim' : 'Não')) !!}
                    {!! $row('Idioma', $s->language) !!}
                    {!! $row('Fuso', $s->timezone) !!}
                    {!! $row('Local', implode(', ', array_filter([$s->city, $s->region, $s->country ? ReportController::countryLabel($s->country) : null]))) !!}
                    {!! $row('IP', $s->ip, true) !!}
                    {!! $row('Conexão', $s->connection_type ? $s->connection_type.($s->connection_downlink ? ' · ~'.$s->connection_downlink.' Mbps' : '') : null) !!}
                    {!! $row('Hardware', $s->hardware_concurrency ? $s->hardware_concurrency.' núcleos'.($s->device_memory ? ' · '.$s->device_memory.' GB' : '') : null) !!}
                    {!! $row('Tema', $s->dark_mode === null ? null : ($s->dark_mode ? 'Escuro' : 'Claro')) !!}
                </dl>
                <details class="mt-3"><summary class="is-size-8 has-text-muted is-clickable">User-Agent</summary><p class="is-size-8 mono mt-1" style="word-break:break-all">{{ $s->user_agent }}</p></details>
            </div>

            <div class="box">
                <div class="box-title"><i class="fa-solid fa-stopwatch has-text-primary"></i> Engajamento</div>
                <dl class="kv">
                    {!! $row('Tempo total', ReportService::duration($s->duration_ms)) !!}
                    {!! $row('Tempo ativo', ReportService::duration($s->active_ms)) !!}
                    {!! $row('Até começar', ReportService::duration($s->time_to_start_ms)) !!}
                    {!! $row('Trocas de aba', (string) $s->tab_switches) !!}
                    {!! $row('Colagens', (string) $s->paste_count) !!}
                    {!! $row('Visita nº', $s->visit_number.($s->is_returning ? ' (recorrente)' : '')) !!}
                </dl>
            </div>

            @if ($journey->count() > 1)
                <div class="box">
                    <div class="box-title"><i class="fa-solid fa-shoe-prints has-text-primary"></i> Jornada do visitante <span class="right has-text-muted">{{ $journey->count() }} visitas</span></div>
                    @foreach ($journey as $j)
                        <div class="timeline-item">
                            <span class="timeline-dot {{ $j->id === $s->id ? 'is-current' : ($j->status === 'submitted' ? 'is-success' : '') }}"><i class="fa-solid {{ $j->status === 'submitted' ? 'fa-check' : ($j->started_at ? 'fa-pen' : 'fa-eye') }}"></i></span>
                            <div class="is-size-7">
                                <strong>{{ $j->created_at->setTimezone($tz)->format('d/m/Y H:i') }}</strong>
                                · {{ ['viewed' => 'Visualizou', 'started' => 'Começou e saiu', 'submitted' => 'Enviou', 'spam' => 'Spam'][$j->status] ?? $j->status }}<br>
                                <span class="has-text-muted">{{ ChannelClassifier::label($j->channel) }}{{ $j->utm_campaign ? ' · '.$j->utm_campaign : '' }} · <i class="fa-solid {{ $j->deviceIcon() }}"></i> {{ $j->browser_name }}</span>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        @endif
    </div>
</div>
@endsection
