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
// Positions every river's monitors, gauges and spots; a few minutes now that every water company is recorded, so it
// has the share cards' longer timeout rather than the recipe's five minutes.
task('artisan:rivers:positions', function () {
    run('{{bin/php}} {{release_or_current_path}}/artisan rivers:positions', timeout: 1800);
})->desc('Position monitors, gauges and spots along each river');
task('artisan:regions:refresh', artisan('regions:refresh --all', ['skipIfNoEnv']))
    ->desc('Rebuild cached conditions, summaries and menus so the new release starts warm');

after('artisan:migrate', 'artisan:db:seed');
after('artisan:db:seed', 'artisan:rivers:centrelines');
after('artisan:rivers:centrelines', 'artisan:rivers:positions');
// Cache keys carry a hash of the cached classes, so the new release warms its own entries before it goes live while
// the old release keeps serving from its own. No cache:clear, which would leave the old release cold meanwhile.
after('artisan:rivers:positions', 'artisan:regions:refresh');

// Share cards are drawn only for places whose card has changed, so most deploys draw none. A change to how cards are
// drawn changes all of them (about 1,400, over half an hour on the server), so the deploy draws for ten minutes and
// the hourly scheduled run draws the rest; until then those pages keep their old card.
task('artisan:share-images:generate', function () {
    run('{{bin/php}} {{release_or_current_path}}/artisan share-images:generate --within=600', timeout: 1800);
})->desc('Draw share cards for new or changed spots, rivers, beaches and lakes, for up to ten minutes');
after('artisan:regions:refresh', 'artisan:share-images:generate');

// The scheduler, which runs as www-data, draws overflow cards and finishes place cards inside share/, which the deploy
// user owns. The card folders are made here, group writable and setgid, so the scheduler can write to them.
task('share-images:overflow-folder', function () {
    run('cd {{deploy_path}}/shared/storage/app/public/share && mkdir -p overflows spots rivers beaches lakes && chmod 2775 . overflows spots rivers beaches lakes');
})->desc('Make the share card folders writable by the scheduler');
after('artisan:share-images:generate', 'share-images:overflow-folder');

// Imports the overflow annual returns on a deploy that has none, or whose matching rules changed
// (ImportOverflowAnnualReturns::MATCHING_VERSION): about 80 requests to the Rivers Trust, a few minutes. A year that
// fails to read keeps its old rows, and a failure never fails the deploy: the next deploy or the monthly run retries.
task('artisan:ingest:overflow-annual-returns', function () {
    try {
        run('{{bin/php}} {{release_or_current_path}}/artisan ingest:overflow-annual-returns --if-outdated', timeout: 1800);
    } catch (\Throwable $exception) {
        warning('Overflow annual returns not imported: '.$exception->getMessage());
    }
})->desc('Import the overflow annual returns if none are stored yet');
after('artisan:share-images:generate', 'artisan:ingest:overflow-annual-returns');
