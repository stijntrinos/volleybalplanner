<?php

namespace App\Notifications;

use App\Models\Club;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ClubInvitation extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Club $club,
        public string $url,
        public bool $isNewUser,
    ) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        if ($this->isNewUser) {
            return (new MailMessage)
                ->subject("Uitnodiging voor {$this->club->name} in ".config('app.name'))
                ->greeting('Hallo!')
                ->line("Je bent uitgenodigd voor {$this->club->name}. Stel je wachtwoord in om te beginnen.")
                ->action('Wachtwoord instellen', $this->url);
        }

        return (new MailMessage)
            ->subject("Je hebt toegang tot {$this->club->name}")
            ->greeting('Hallo!')
            ->line("Je hebt nu toegang tot {$this->club->name} in ".config('app.name').'.')
            ->action("Naar {$this->club->name}", $this->url);
    }
}
