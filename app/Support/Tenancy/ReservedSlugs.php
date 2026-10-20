<?php

namespace App\Support\Tenancy;

/**
 * Club slugs are the first URL segment of public pages, so they may not
 * collide with application paths.
 */
final class ReservedSlugs
{
    public const ALL = [
        'admin', 'app', 'login', 'logout', 'register', 'livewire', 'filament',
        'storage', 'up', 'build', 'vendor', 'css', 'js', 'fonts', 'images',
        // laravel/passkeys registers /passkeys/* and /user/passkeys.
        'passkeys', 'user',
        // Pages of the public marketing site.
        'functies', 'prijzen', 'contact', 'demo', 'privacy', 'voorwaarden', 'cookies',
        // Invitation links.
        'uitnodiging',
    ];
}
