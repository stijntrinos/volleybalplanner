<?php

namespace Deployer;

require 'recipe/laravel.php';

// Config
set('repository', 'git@github.com:stijntrinos/volleybalplanner.git');
set('keep_releases', 2);

set('bin/php', '/usr/bin/php8.5');

// Host
host('157.90.167.45')
    ->set('remote_user', 'sanction')
    ->set('deploy_path', '/home/sanction/volleybalplanner.nl')
    ->set('branch', 'main');

// Tasks
task('deploy:styling', function () {
    run('cd {{release_path}} && npm ci && PHP_BINARY=/usr/bin/php8.5 npm run build');
});
after('deploy:vendors', 'deploy:styling');

// .npmrc sets ignore-scripts, so puppeteer's postinstall never fetches the
// headless shell that Browsershot needs; an upgrade then breaks the images.
task('deploy:chrome', function () {
    run('cd {{release_path}} && npx puppeteer browsers install chrome-headless-shell');
});
after('deploy:styling', 'deploy:chrome');

task('deploy:reload-php', function () {
    run('sudo -S service php8.5-fpm reload');
});
after('deploy:publish', 'deploy:reload-php');

after('deploy:publish', 'artisan:queue:restart');

after('deploy:failed', 'deploy:unlock');
