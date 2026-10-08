<?php

namespace App\Notifications;

use App\Models\ContactRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ContactRequestReceived extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public ContactRequest $request) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $request = $this->request;

        return (new MailMessage)
            ->subject("{$request->topic->getLabel()}: {$request->club}")
            ->replyTo($request->email, $request->name)
            ->greeting($request->topic->getLabel())
            ->line('**'.self::plain($request->name).'** van **'.self::plain($request->club).'** ('.self::plain($request->email).($request->phone ? ', '.self::plain($request->phone) : '').')')
            ->line('Teams: '.self::plain($request->teams ?: '—').' · Sporthallen: '.self::plain($request->venues ?: '—'))
            ->line($request->message ? self::plain($request->message) : 'Geen bericht.')
            ->action('Bekijken in het platformbeheer', url('/admin/contact-requests'));
    }

    /**
     * Visitor input as plain text: escaped Markdown, so a message can't turn
     * into a convincing link or button in the operator's inbox.
     */
    private static function plain(string $text): string
    {
        return preg_replace('/([\\\\`*_{}\[\]()#+\-.!|<>~])/', '\\\\$1', $text) ?? '';
    }
}
