<?php

namespace App\Notifications;

use App\Models\Form;
use App\Models\Submission;
use App\Services\Tracking\ChannelClassifier;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class NewSubmission extends Notification
{
    public function __construct(public Form $form, public Submission $submission) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject('Nova resposta: '.$this->form->title)
            ->greeting('Nova resposta recebida')
            ->line('O formulário **'.$this->form->title.'** recebeu uma nova resposta.');

        foreach ($this->form->fields as $f) {
            if (array_key_exists($f->key, $this->submission->data)) {
                $mail->line('**'.$f->label.':** '.$f->display($this->submission->data[$f->key]));
            }
        }
        if ($s = $this->submission->session) {
            $mail->line('---')
                ->line('Origem: '.ChannelClassifier::label($s->channel).($s->utm_campaign ? ' · Campanha: '.$s->utm_campaign : ''))
                ->line('Dispositivo: '.trim(($s->device_type ?? '').' · '.($s->os_name ?? '').' · '.($s->browser_name ?? ''), ' ·'));
        }

        return $mail->action('Ver resposta', route('submissions.show', [$this->form, $this->submission]));
    }
}
