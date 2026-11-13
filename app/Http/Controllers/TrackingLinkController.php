<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Form;
use App\Models\TrackingLink;
use Illuminate\Http\Request;

class TrackingLinkController extends Controller
{
    public function store(Request $request, Form $form)
    {
        $this->authorizeForm($form, 'links.manage');
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'utm_source' => ['required', 'string', 'max:120'],
            'utm_medium' => ['required', 'string', 'max:120'],
            'utm_campaign' => ['nullable', 'string', 'max:160'],
            'utm_term' => ['nullable', 'string', 'max:160'],
            'utm_content' => ['nullable', 'string', 'max:160'],
        ], ['utm_source.required' => 'Informe a origem (ex.: instagram).', 'utm_medium.required' => 'Informe a mídia (ex.: social, cpc, email).']);

        $norm = fn ($v) => $v === null ? null : trim(preg_replace('/\s+/', '_', mb_strtolower($v)));
        $link = $form->trackingLinks()->create([
            'name' => $data['name'],
            'created_by' => $request->user()->id,
        ] + array_map($norm, array_intersect_key($data, array_flip(['utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content']))));

        AuditLog::record('link.created', 'Criou o link rastreado "'.$link->name.'"', $link);

        return back()->with('success', 'Link rastreado criado: '.$link->shortUrl());
    }

    public function destroy(Form $form, TrackingLink $link)
    {
        $this->authorizeForm($form, 'links.manage');
        abort_unless($link->form_id === $form->id, 404);
        $link->delete();

        return back()->with('success', 'Link removido. Os dados já coletados foram mantidos.');
    }
}
