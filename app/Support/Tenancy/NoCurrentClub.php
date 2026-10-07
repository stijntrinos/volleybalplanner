<?php

namespace App\Support\Tenancy;

use RuntimeException;

class NoCurrentClub extends RuntimeException
{
    public function __construct()
    {
        parent::__construct("Er is geen actieve club. Gebruik CurrentClub::run() of withoutGlobalScope('club').");
    }
}
