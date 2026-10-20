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

// A deploy does only what the new release needs before it goes live: migrations, seeders (rivers and spots, which upsert
// by slug) and a warm cache. Everything that can wait runs on the queue once the release is live (below).
after('artisan:migrate', 'artisan:db:seed');
task('artisan:regions:refresh', artisan('regions:refresh --all', ['skipIfNoEnv']))
    ->desc('Rebuild cached conditions, summaries and menus so the new release starts warm');
// Cache keys carry a hash of the cached classes, so the new release warms its own entries before it goes live while
// the old release keeps serving from its own. No cache:clear, which would leave the old release cold meanwhile.
after('artisan:db:seed', 'artisan:regions:refresh');

// The queue worker (as www-data) draws share cards inside share/, which the deploy user owns, so the card folders are
// made group writable and setgid.
task('share-images:folders', function () {
    run('mkdir -p {{deploy_path}}/shared/storage/app/public/share && cd {{deploy_path}}/shared/storage/app/public/share && mkdir -p overflows spots rivers beaches lakes && chmod 2775 . overflows spots rivers beaches lakes');
})->desc('Make the share card folders writable by the queue worker');
after('artisan:regions:refresh', 'share-images:folders');

// Once the release is live: restart the workers so they run its code, then queue centrelines and positions, share
// cards and the annual returns check (deploy:dispatch-jobs). Dispatching before the switch could hand a job to a worker
// still running the old release.
after('deploy:symlink', 'artisan:queue:restart');
task('artisan:deploy:dispatch-jobs', artisan('deploy:dispatch-jobs', ['skipIfNoEnv', 'showOutput']))
    ->desc('Queue the background work a deploy leaves behind');
after('artisan:queue:restart', 'artisan:deploy:dispatch-jobs');
