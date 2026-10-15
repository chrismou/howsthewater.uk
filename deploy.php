<?php

declare(strict_types=1);

namespace Deployer;

require 'recipe/laravel.php';
require 'contrib/npm.php';

// Config - repo URL uses the deploy user's SSH alias for this repo's read-only deploy key
set('repository', 'git@github-howsthewater:chrismou/howsthewater.uk.git');

add('shared_files', []);
add('shared_dirs', []);
// Non-recursive chmod only reaches the directories listed, so name storage's subdirectories too
add('writable_dirs', [
    'storage/app',
    'storage/app/private',
    'storage/app/public',
    'storage/framework',
    'storage/framework/cache',
    'storage/framework/cache/data',
    'storage/framework/sessions',
    'storage/framework/views',
    'storage/logs',
]);

// Permissions model (ansible README section 9): shared www-data group, chmod rather than ACLs
set('http_user', 'www-data');
set('writable_mode', 'chmod');
set('writable_chmod_mode', '2770');
// The Laravel recipe turns this on, but deploy cannot chmod files www-data owns inside storage
set('writable_recursive', false);

set('remote_user', getenv('DEPLOY_USER'));

// Hosts
host(getenv('DEPLOY_IP'))
    ->set('port', getenv('DEPLOY_PORT'))
    ->set('remote_user', getenv('DEPLOY_USER'))
    ->set('deploy_path', getenv('DEPLOY_PATH'))
    ->set('ssh_options', [
        'IdentitiesOnly=yes',
    ]);

task('npm:build', function () {
    run('cd {{release_path}} && npm run build');
});

// Make the release code read-only to the web user, whatever umask the deploy user has.
// chmod -R does not follow symlinks, so shared/storage and .env are untouched.
task('deploy:harden_release', function () {
    run('chmod -R g-w,o-w {{release_path}}');
})->desc('Make release code read-only to the web user');

after('deploy:vendors', 'npm:install');
after('npm:install', 'npm:build');
after('npm:build', 'deploy:harden_release');
after('deploy:failed', 'deploy:unlock');

// Seed rivers and spots, import missing centrelines and position everything on each deploy, so adding a seeded
// river needs no manual step. All three are idempotent: the seeders upsert by slug and centrelines are only
// fetched for rivers that lack one. Gauge discovery stays on its weekly schedule as it backfills readings.
task('artisan:rivers:centrelines', artisan('rivers:centrelines', ['skipIfNoEnv', 'showOutput']))
    ->desc('Import centrelines for rivers that do not have one yet');
task('artisan:rivers:positions', artisan('rivers:positions', ['skipIfNoEnv']))
    ->desc('Position monitors, gauges and spots along each river');
task('artisan:regions:refresh', artisan('regions:refresh --all', ['skipIfNoEnv']))
    ->desc('Rebuild cached conditions, summaries and menus so the new release starts warm');

// Run migrate before cache clear to prevent errors on first run where DB cache clears before DB exists
after('artisan:migrate', 'artisan:db:seed');
after('artisan:db:seed', 'artisan:rivers:centrelines');
after('artisan:rivers:centrelines', 'artisan:rivers:positions');
after('artisan:rivers:positions', 'artisan:cache:clear');
// cache:clear empties every cached page fact, so rebuild them before the release goes live.
after('artisan:cache:clear', 'artisan:regions:refresh');
