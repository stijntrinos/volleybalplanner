<?php

namespace App\Planner\Exceptions;

use RuntimeException;

/**
 * The public Nevobo API could not be reached or answered with an error. The
 * message is Dutch and safe to show: it never contains the response body.
 */
class NevoboUnavailable extends RuntimeException
{
    public static function make(?int $status = null): self
    {
        return new self(match (true) {
            $status === 429 => __('De Nevobo krijgt nu te veel verzoeken. Probeer het over een minuut opnieuw.'),
            $status === 404 => __('Deze vereniging is niet gevonden bij de Nevobo.'),
            $status !== null => __('De Nevobo is tijdelijk niet beschikbaar (HTTP :status). Probeer het later opnieuw.', ['status' => $status]),
            default => __('De Nevobo is niet bereikbaar. Controleer de verbinding en probeer het later opnieuw.'),
        });
    }
}
