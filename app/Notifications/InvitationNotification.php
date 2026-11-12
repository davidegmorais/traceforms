<?php

namespace App\Notifications;

use App\Models\Invitation;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class InvitationNotification extends Notification
{
    public function __construct(public Invitation $invitation) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $i = $this->invitation;

        return (new MailMessage)
            ->subject('Convite para '.$i->account->name.' no TraceForms')
            ->greeting('Você foi convidado!')
            ->line(($i->inviter?->name ?? 'Alguém').' convidou você para a conta **'.$i->account->name.'** como **'.$i->role->label().'**.')
            ->line($i->role->description())
            ->action('Aceitar convite', route('invitations.show', $i->token))
            ->line('O convite expira em '.$i->expires_at->format('d/m/Y').'.');
    }
}
