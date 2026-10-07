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
    ];
}
