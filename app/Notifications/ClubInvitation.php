<?php

namespace App\Notifications;

use App\Models\Club;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Carbon;

class ClubInvitation extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Club $club,
        public string $url,
        public ?string $inviterName,
        public Carbon $expiresAt,
    ) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $who = $this->inviterName ? "{$this->inviterName} nodigt je uit" : 'Je bent uitgenodigd';

        return (new MailMessage)
            ->subject("Uitnodiging voor {$this->club->name} in ".config('app.name'))
            ->greeting('Hallo!')
            ->line("{$who} om mee te plannen voor {$this->club->name} in ".config('app.name').'.')
            ->action('Uitnodiging bekijken', $this->url)
            ->line('De link werkt tot '.$this->expiresAt->translatedFormat('j F Y').'. Verwacht je deze uitnodiging niet, dan kun je deze e-mail negeren.');
    }
}
