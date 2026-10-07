<?php

namespace App\Planner\Exceptions;

use DomainException;

/**
 * A rejected publication is only useful when the user sees why. It used to
 * stop at "the schedule contains hard conflicts" in a notification while the
 * scan result disappeared into the log; so the result travels with the
 * exception, and the conflict overview can show it.
 */
class PublishBlocked extends DomainException
{
    /** @param list<array<string, mixed>> $conflicts */
    public function __construct(public readonly array $conflicts)
    {
        parent::__construct(trans_choice(
            'Publiceren is geblokkeerd omdat de planning :count harde conflict bevat.|Publiceren is geblokkeerd omdat de planning :count harde conflicten bevat.',
            count($conflicts),
            ['count' => count($conflicts)],
        ));
    }
}
