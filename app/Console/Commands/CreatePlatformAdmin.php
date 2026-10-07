<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class CreatePlatformAdmin extends Command
{
    protected $signature = 'platform:create-admin {email} {--name=}';

    protected $description = 'Maak een platformbeheerder aan of promoveer een bestaande gebruiker';

    public function handle(): int
    {
        $email = Str::lower(trim((string) $this->argument('email')));

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->error('Ongeldig e-mailadres.');

            return self::FAILURE;
        }

        $user = User::query()->where('email', $email)->first();

        if ($user === null) {
            $user = User::create([
                'name' => $this->option('name') ?: $this->ask('Naam'),
                'email' => $email,
                'password' => $this->secret('Wachtwoord'),
            ]);
        }

        $user->forceFill(['is_platform_admin' => true, 'email_verified_at' => $user->email_verified_at ?? now()])->save();

        $this->info("{$user->email} is platformbeheerder.");

        return self::SUCCESS;
    }
}
