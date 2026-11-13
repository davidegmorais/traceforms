@extends('layouts.app')
@section('title', $form->title)
@section('crumb')<a href="{{ route('forms.index') }}">Formulários</a> / Configurações @endsection
@section('actions')@include('partials.form-actions')@endsection

@section('content')
@include('partials.form-header')
@php $s = fn ($k) => old($k, $form->setting($k)); @endphp
<form method="POST" action="{{ route('forms.settings.update', $form) }}" style="max-width:920px">
    @csrf @method('PUT')

    <div class="box">
        <div class="box-title"><i class="fa-solid fa-flag-checkered has-text-primary"></i> Envio e confirmação</div>
        <div class="columns">
            <div class="column is-4 field">
                <label class="label">Texto do botão</label>
                <input class="input" name="submit_label" value="{{ $s('submit_label') }}" required maxlength="60">
            </div>
            <div class="column field">
                <label class="label">Título da mensagem de sucesso</label>
                <input class="input" name="success_title" value="{{ $s('success_title') }}" required maxlength="150">
            </div>
        </div>
        <div class="field">
            <label class="label">Mensagem de sucesso</label>
            <textarea class="textarea" name="success_message" rows="2">{{ $s('success_message') }}</textarea>
        </div>
        <div class="field">
            <label class="label">Redirecionar após envio <span class="has-text-muted has-text-weight-normal">(opcional)</span></label>
            <input class="input @error('redirect_url') is-danger @enderror" name="redirect_url" value="{{ $s('redirect_url') }}" placeholder="https://seusite.com/obrigado">
            @error('redirect_url')<p class="help is-danger">{{ $message }}</p>@enderror
            <label class="checkbox is-size-7 mt-2"><input type="checkbox" name="redirect_pass_params" value="1" @checked($s('redirect_pass_params'))> Repassar UTMs e o ID da resposta para a página de obrigado</label>
        </div>
    </div>

    <div class="box">
        <div class="box-title"><i class="fa-solid fa-door-closed has-text-primary"></i> Disponibilidade</div>
        <div class="columns">
            <div class="column field">
                <label class="label">Encerrar automaticamente em</label>
                <input type="datetime-local" class="input" name="closes_at" value="{{ $s('closes_at') ? \Carbon\Carbon::parse($s('closes_at'))->format('Y-m-d\TH:i') : '' }}">
            </div>
            <div class="column field">
                <label class="label">Limite de respostas</label>
                <input type="number" min="1" class="input" name="max_submissions" value="{{ $s('max_submissions') }}" placeholder="Sem limite">
            </div>
        </div>
        <div class="field">
            <label class="label">Mensagem quando encerrado</label>
            <input class="input" name="closed_message" value="{{ $s('closed_message') }}">
        </div>
        <label class="checkbox"><input type="checkbox" name="one_per_visitor" value="1" @checked($s('one_per_visitor'))> Permitir apenas uma resposta por visitante (por navegador)</label>
        <br><label class="checkbox mt-2"><input type="checkbox" name="show_progress" value="1" @checked($s('show_progress'))> Mostrar barra de progresso</label>
        <br><label class="checkbox mt-2"><input type="checkbox" name="show_branding" value="1" @checked($s('show_branding'))> Exibir “Feito com TraceForms”</label>
    </div>

    <div class="box">
        <div class="box-title"><i class="fa-solid fa-bell has-text-primary"></i> Notificações e integrações</div>
        <div class="field">
            <label class="label">Avisar por e-mail a cada resposta</label>
            <input class="input" name="notify_emails" value="{{ implode(', ', (array) $form->setting('notify_emails', [])) }}" placeholder="vendas@empresa.com, joao@empresa.com">
            <p class="help">Separe vários e-mails por vírgula. O e-mail inclui a origem (canal, campanha) e o dispositivo.</p>
        </div>
        <div class="field">
            <label class="label">Webhook <span class="tag is-light is-small ml-1">Zapier · Make · n8n · CRM</span></label>
            <input class="input mono @error('webhook_url') is-danger @enderror" name="webhook_url" value="{{ $s('webhook_url') }}" placeholder="https://hooks.zapier.com/…">
            @error('webhook_url')<p class="help is-danger">{{ $message }}</p>@enderror
            <p class="help">Recebe um POST JSON com respostas + todos os dados de rastreamento (UTMs, click ID, dispositivo, 1º toque…). Até 3 tentativas.</p>
        </div>
    </div>

    <div class="box">
        <div class="box-title"><i class="fa-solid fa-bullseye has-text-primary"></i> Pixels de conversão</div>
        <p class="is-size-7 has-text-muted mb-4">Dispara eventos de conversão na página do formulário: <span class="mono">generate_lead</span> (GA4), <span class="mono">Lead</span> (Meta) e <span class="mono">traceforms_submit</span> (dataLayer/GTM). Quando incorporado, o evento também é enviado à página-mãe.</p>
        <div class="columns">
            <div class="column field">
                <label class="label">Google Analytics 4</label>
                <input class="input mono @error('ga4_id') is-danger @enderror" name="ga4_id" value="{{ $s('ga4_id') }}" placeholder="G-XXXXXXXXXX">
                @error('ga4_id')<p class="help is-danger">{{ $message }}</p>@enderror
            </div>
            <div class="column field">
                <label class="label">Pixel da Meta</label>
                <input class="input mono @error('meta_pixel_id') is-danger @enderror" name="meta_pixel_id" value="{{ $s('meta_pixel_id') }}" placeholder="123456789012345">
                @error('meta_pixel_id')<p class="help is-danger">{{ $message }}</p>@enderror
            </div>
            <div class="column field">
                <label class="label">Google Tag Manager</label>
                <input class="input mono @error('gtm_id') is-danger @enderror" name="gtm_id" value="{{ $s('gtm_id') }}" placeholder="GTM-XXXXXXX">
                @error('gtm_id')<p class="help is-danger">{{ $message }}</p>@enderror
            </div>
        </div>
    </div>

    <div class="box">
        <div class="box-title"><i class="fa-solid fa-user-shield has-text-primary"></i> Privacidade e antispam</div>
        <div class="columns">
            <div class="column">
                <label class="checkbox"><input type="checkbox" name="collect_ip" value="1" @checked($s('collect_ip'))> Coletar endereço IP</label><br>
                <label class="checkbox mt-2"><input type="checkbox" name="anonymize_ip" value="1" @checked($s('anonymize_ip'))> Anonimizar IP (zera o último bloco — recomendado para LGPD)</label><br>
                <label class="checkbox mt-2"><input type="checkbox" name="save_partials" value="1" @checked($s('save_partials'))> Salvar respostas parciais de quem desiste</label>
                <p class="help">Permite recuperar leads que começaram e não enviaram. Informe isso na sua política de privacidade.</p>
            </div>
            <div class="column is-4 field">
                <label class="label">Tempo mínimo para envio (s)</label>
                <input type="number" min="0" max="120" class="input" name="min_submit_seconds" value="{{ $s('min_submit_seconds') }}">
                <p class="help">Envios mais rápidos que isso são marcados como spam (robôs). Use 0 para desativar.</p>
            </div>
        </div>
    </div>

    <div class="is-flex is-justify-content-space-between">
        @can('forms.delete', $form)
            <button type="submit" form="delete-form" class="button is-danger is-light"><i class="fa-regular fa-trash-can mr-2"></i> Excluir formulário</button>
        @else <span></span> @endcan
        <button class="button is-primary"><i class="fa-solid fa-check mr-2"></i> Salvar configurações</button>
    </div>
</form>
@can('forms.delete', $form)
    <form id="delete-form" method="POST" action="{{ route('forms.destroy', $form) }}" onsubmit="return confirm('Excluir este formulário e todas as respostas?')">@csrf @method('DELETE')</form>
@endcan
@endsection
