<?php

namespace App\Planner\Support\Security;

use App\Support\Tenancy\CurrentClub;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Limits how often one visitor may submit a public form, per club and per
 * (hashed) IP address.
 */
class SubmissionRateLimiter
{
    public function __construct(
        private readonly IpHasher $ipHasher,
        private readonly CurrentClub $currentClub,
    ) {}

    /** Records an attempt; returns the seconds to wait when the limit is reached, otherwise null. */
    public function attempt(string $scope, int $maxAttempts = 5, int $decaySeconds = 600): ?int
    {
        $clubId = $this->currentClub->get()?->getKey() ?? 'none';
        $ipHash = $this->ipHasher->hash(request()->ip());
        $key = "planner-submission:{$clubId}:{$scope}:{$ipHash}";

        if (RateLimiter::tooManyAttempts($key, $maxAttempts)) {
            return RateLimiter::availableIn($key);
        }

        RateLimiter::hit($key, $decaySeconds);

        return null;
    }
}
