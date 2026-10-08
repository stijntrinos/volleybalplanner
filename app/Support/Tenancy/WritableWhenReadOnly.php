<?php

namespace App\Support\Tenancy;

/**
 * Marks a BelongsToClub model that stays writable in read-only mode, such
 * as a user's own view preferences: saving them changes nothing for the club.
 */
interface WritableWhenReadOnly {}
