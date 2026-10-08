<?php

namespace App\Support\Tenancy;

use Symfony\Component\HttpKernel\Exception\HttpException;

/** A write to a club in read-only mode (see ReadOnlyClub). */
class ClubIsReadOnly extends HttpException
{
    public function __construct()
    {
        parent::__construct(403, __('Deze vereniging staat in de leesstand: het abonnement is afgelopen, dus wijzigen kan niet meer. Neem contact op om het te verlengen.'));
    }
}
