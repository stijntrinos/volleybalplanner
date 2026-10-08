<?php

/*
 * The public marketing site (/, /functies, /prijzen, /contact). Everything
 * the design left as a placeholder lives here; a part whose value is empty
 * is left out of the page instead of showing a placeholder.
 */
return [
    'company' => env('SITE_COMPANY'),

    'email' => env('SITE_EMAIL'),

    'phone' => env('SITE_PHONE'),

    // Where demo and contact requests are mailed; defaults to the e-mail above.
    'contact_recipient' => env('SITE_CONTACT_RECIPIENT', env('SITE_EMAIL')),

    'reply_within_days' => env('SITE_REPLY_DAYS', 2),

    'trial_weeks' => env('SITE_TRIAL_WEEKS'),

    // E.g. "TransIP in Nederland"; shown in the FAQ about where data is stored.
    'hosting' => env('SITE_HOSTING'),

    'privacy_url' => env('SITE_PRIVACY_URL'),

    'terms_url' => env('SITE_TERMS_URL'),

    // Plans, prices and limits live in the database (Abonnementen in /admin).
];
