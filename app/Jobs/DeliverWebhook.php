<?php

namespace App\Jobs;

use App\Models\Submission;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Http;

/** Envia a resposta + todos os dados de rastreamento para um webhook (Zapier, Make, n8n, CRM…). */
class DeliverWebhook implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 3;

    public array $backoff = [10, 60, 300];

    public function __construct(public int $submissionId, public string $url) {}

    public function handle(): void
    {
        $s = Submission::with(['form.fields', 'session'])->find($this->submissionId);
        if (! $s || ! preg_match('#^https://#i', $this->url)) {
            return;
        }
        $answers = [];
        foreach ($s->form->fields as $f) {
            if (array_key_exists($f->key, $s->data)) {
                $answers[] = ['key' => $f->key, 'label' => $f->label, 'type' => $f->type, 'value' => $s->data[$f->key]];
            }
        }
        $session = $s->session?->only([
            'visitor_id', 'is_returning', 'visit_number', 'country', 'region', 'city', 'device_type', 'device_brand',
            'device_model', 'os_name', 'os_version', 'browser_name', 'browser_version', 'screen_width', 'screen_height',
            'language', 'timezone', 'referrer', 'referrer_domain', 'channel', 'landing_url', 'parent_url',
            'utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content', 'utm_id', 'click_id_type',
            'click_id', 'first_touch', 'hidden_fields', 'duration_ms', 'time_to_start_ms',
        ]);

        Http::timeout(8)->acceptJson()->withHeaders(['User-Agent' => 'TraceForms-Webhook/1.0'])->post($this->url, [
            'event' => 'submission.created',
            'form' => ['id' => $s->form->public_id, 'title' => $s->form->title],
            'submission' => ['id' => $s->uuid, 'submitted_at' => $s->submitted_at->toIso8601String()],
            'answers' => $answers,
            'data' => $s->data,
            'tracking' => $session,
        ])->throw();
    }
}
