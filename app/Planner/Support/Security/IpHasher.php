<?php

namespace App\Planner\Support\Security;

/**
 * Turns an IP address into a pseudonymous hash.
 *
 * Deliberately a keyed HMAC and not a plain sha256: the whole IPv4 space is
 * only four billion hashes, so an unsalted hash is easily reversed to the
 * original address. With APP_KEY as the key that is not possible.
 *
 * Consequence: after rotating APP_KEY old and new hashes no longer compare.
 */
class IpHasher
{
    public function hash(?string $ip): ?string
    {
        if (blank($ip)) {
            return null;
        }

        return hash_hmac('sha256', $ip, (string) config('app.key'));
    }
}
