<?php

namespace App\Http\Controllers;

use App\Models\Invitation;
use App\Models\User;
use App\Support\Tenancy\ClubInvitations;
use Filament\Facades\Filament;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

/**
 * The page behind an invitation link: create an account, or log in with the
 * invited address and accept. Declining removes the invitation.
 */
class InvitationController extends Controller
{
    public function show(Request $request, string $token): View
    {
        $invitation = Invitation::findPendingByToken($token);

        if ($invitation === null) {
            return view('site.pages.invitation', ['state' => 'invalid', 'invitation' => null, 'token' => $token]);
        }

        $user = $request->user();
        $hasAccount = User::query()->where('email', $invitation->email)->exists();

        $state = match (true) {
            $user instanceof User && Str::lower($user->email) === $invitation->email => 'accept',
            $user instanceof User => 'other-account',
            $hasAccount => 'login',
            default => 'register',
        };

        if ($state === 'login') {
            // After logging in, Filament sends the user back here.
            $request->session()->put('url.intended', $request->fullUrl());
        }

        return view('site.pages.invitation', [
            'state' => $state,
            'invitation' => $invitation,
            'token' => $token,
            'loginUrl' => Filament::getPanel('club')->getLoginUrl(),
        ]);
    }

    public function accept(Request $request, string $token, ClubInvitations $invitations): RedirectResponse
    {
        $invitation = Invitation::findPendingByToken($token);
        abort_if($invitation === null, 404);

        $user = $request->user();
        $existing = User::query()->where('email', $invitation->email)->first();

        if ($existing instanceof User) {
            // An existing account only joins when its owner is logged in.
            abort_unless($user instanceof User && $user->is($existing), 403);
        } else {
            abort_if($user instanceof User, 403);
            $data = $request->validate([
                'name' => ['required', 'string', 'max:255'],
                'password' => ['required', 'confirmed', Password::default()],
            ]);
            $user = User::create([
                'name' => $data['name'],
                'email' => $invitation->email,
                'password' => $data['password'],
            ]);
            // The invitation link went to this address, so it is verified.
            $user->forceFill(['email_verified_at' => now()])->save();
            Auth::login($user);
            $request->session()->regenerate();
        }

        $invitations->accept($invitation, $user);

        return redirect()->to(Filament::getPanel('club')->getUrl($invitation->club));
    }

    public function decline(string $token): RedirectResponse
    {
        Invitation::findPendingByToken($token)?->delete();

        return redirect()->route('site.home')->with('invitation-declined', true);
    }
}
