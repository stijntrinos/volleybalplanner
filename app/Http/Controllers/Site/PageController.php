<?php

namespace App\Http\Controllers\Site;

use App\Enums\ContactTopic;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreContactRequest;
use App\Models\ContactRequest;
use App\Models\Plan;
use App\Notifications\ContactRequestReceived;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Notification;

/**
 * The public marketing site of Volleybalplanner itself; the pages of a club
 * live under /{club} (routes/planner.php).
 */
class PageController extends Controller
{
    public function home(): View
    {
        return view('site.pages.home');
    }

    public function features(): View
    {
        return view('site.pages.features');
    }

    public function pricing(): View
    {
        return view('site.pages.pricing', [
            'plans' => Plan::query()->public()->ordered()->get(),
        ]);
    }

    public function contact(): View
    {
        $topic = collect(ContactTopic::publicCases())->first(fn (ContactTopic $case): bool => $case->value === request('onderwerp')) ?? ContactTopic::Demo;

        return view('site.pages.contact', [
            'topic' => old('topic', $topic->value),
            'teamOptions' => StoreContactRequest::TEAMS,
            'venueOptions' => StoreContactRequest::VENUES,
        ]);
    }

    public function storeContact(StoreContactRequest $request): RedirectResponse
    {
        $contactRequest = ContactRequest::create($request->safe()->except(['privacy', 'website']));

        if (filled($recipient = config('site.contact_recipient'))) {
            Notification::route('mail', $recipient)->notify(new ContactRequestReceived($contactRequest));
        }

        return redirect()
            ->route('site.contact')
            ->with('contact-sent', $contactRequest->topic->value);
    }
}
