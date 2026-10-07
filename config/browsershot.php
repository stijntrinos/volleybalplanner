<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Node en npm
    |--------------------------------------------------------------------------
    |
    | Browsershot start Chrome via een node-script. PHP-FPM erft niet de PATH
    | van een inlogshell, dus daar is node vaak onvindbaar terwijl hetzelfde
    | commando in de terminal werkt ("sh: node: command not found").
    |
    | Deze mogen leeg blijven: Nodepaden zoekt dan zelf, eerst in de nvm-map van
    | Herd en daarna in /usr/local/bin, /opt/homebrew/bin en /usr/bin. Vul ze
    | alleen als node ergens anders staat.
    |
    */

    'node_binary' => env('BROWSERSHOT_NODE_BINARY'),

    'npm_binary' => env('BROWSERSHOT_NPM_BINARY'),

    /*
    |--------------------------------------------------------------------------
    | Chrome
    |--------------------------------------------------------------------------
    |
    | Leeg laten laat puppeteer zijn eigen gedownloade Chrome kiezen. Vul dit
    | alleen als er een andere browser gebruikt moet worden.
    |
    */

    'chrome_path' => env('BROWSERSHOT_CHROME_PATH'),

];
