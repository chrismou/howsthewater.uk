<?php

use App\Data\SunTimes;
use App\Models\BathingWater;
use App\Models\Lake;
use App\Models\Region;
use App\Models\River;
use App\Models\Spot;
use App\Models\WeatherForecast;
use App\Services\Weather\WeatherCell;
use App\Services\Weather\WeatherCode;
use GuzzleHttp\Promise\PromiseInterface;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Sleep;

function fakeOpenMeteo(): void
{
    Http::fake(['api.open-meteo.com/*' => fn (Request $request) => openMeteoAnswer($request)]);
}

/**
 * Open-Meteo's answer for however many locations a request asks for: an object for one, a list for several.
 */
function openMeteoAnswer(Request $request): PromiseInterface
{
    parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);
    $hours = collect(range(0, 23))->map(fn (int $hour) => now()->startOfHour()->addHours($hour)->getTimestamp());
    $location = [
        'hourly' => [
            'time' => $hours->all(),
            'temperature_2m' => $hours->map(fn ($time, $hour) => 15.4 - $hour * 0.2)->all(),
            'apparent_temperature' => $hours->map(fn () => 12.1)->all(),
            'precipitation_probability' => $hours->map(fn ($time, $hour) => $hour * 4)->all(),
            'precipitation' => $hours->map(fn () => 0.2)->all(),
            'weather_code' => $hours->map(fn () => 3)->all(),
            'wind_speed_10m' => $hours->map(fn () => 9.6)->all(),
            'wind_gusts_10m' => $hours->map(fn () => 21.3)->all(),
            'wind_direction_10m' => $hours->map(fn () => 250)->all(),
        ],
        'daily' => ['time' => [now('Europe/London')->startOfDay()->getTimestamp()], 'uv_index_max' => [2.6]],
    ];
    $count = count(explode(',', $query['latitude']));

    return Http::response($count === 1 ? $location : array_fill(0, $count, $location));
}

function weatherSpot(array $attributes = []): Spot
{
    $region = Region::factory()->create(['name' => 'Yorkshire', 'slug' => 'yorkshire']);
    $river = River::factory()->create(['region_id' => $region->id, 'name' => 'River Wharfe', 'slug' => 'wharfe']);

    return Spot::factory()->create(['river_id' => $river->id, 'name' => 'Ilkley', 'slug' => 'ilkley', 'latitude' => 53.92, 'longitude' => -1.82, ...$attributes]);
}

it('reads one forecast per grid cell in paced batches and skips cells read recently', function () {
    Sleep::fake();
    fakeOpenMeteo();
    $spot = weatherSpot();
    // A second spot in the same cell shares its forecast; 29 more each sit in their own.
    Spot::factory()->create(['river_id' => $spot->river_id, 'latitude' => 53.921, 'longitude' => -1.821]);
    foreach (range(1, 29) as $step) {
        Spot::factory()->create(['river_id' => $spot->river_id, 'latitude' => 53.03 + $step * 0.1, 'longitude' => -1.82]);
    }

    $this->artisan('ingest:weather')->assertSuccessful()->expectsOutputToContain('Read forecasts for 30 of 30 weather cells.');

    expect(WeatherForecast::count())->toBe(30);
    Http::assertSentCount(2);
    Http::assertSent(fn (Request $request) => str_contains($request->url(), 'wind_speed_unit=mph')
        && str_starts_with($request->header('User-Agent')[0], 'howsthewater.uk'));
    Sleep::assertSleptTimes(1);

    $forecast = WeatherForecast::firstWhere('cell_key', WeatherCell::containing(53.92, -1.82)->key);
    expect($forecast->data['hours'])->toHaveCount(24)
        ->and($forecast->data['hours'][0]['wind_direction'])->toBe(250)
        ->and($forecast->data['uv_index_max'])->toBe([now('Europe/London')->toDateString() => 2.6]);

    $this->artisan('ingest:weather')->assertSuccessful()->expectsOutputToContain('Read forecasts for 0 of 30 weather cells.');
    Http::assertSentCount(2);
});

it('logs a failed read, keeps the last forecast and fails the run', function () {
    Sleep::fake();
    Log::spy();
    Http::fake(['api.open-meteo.com/*' => Http::response(['error' => true, 'reason' => 'Too many requests'], 429)]);
    weatherSpot();
    $old = WeatherForecast::factory()->at(53.92, -1.82)->create(['fetched_at' => now()->subHours(4)]);

    $this->artisan('ingest:weather')->assertFailed();

    expect($old->fresh()->fetched_at->equalTo($old->fetched_at))->toBeTrue();
    Http::assertSentCount(1);
    Log::shouldHaveReceived('error')->withArgs(fn (string $message) => str_contains($message, 'Open-Meteo weather forecast request failed'));
});

it('tries again after a server error, counting each try against the allowance', function () {
    Sleep::fake();
    $tries = 0;
    Http::fake(['api.open-meteo.com/*' => function (Request $request) use (&$tries) {
        return ++$tries === 1 ? Http::response(['error' => true], 503) : openMeteoAnswer($request);
    }]);
    weatherSpot();

    $this->artisan('ingest:weather')->assertSuccessful();

    expect(WeatherForecast::count())->toBe(1)
        ->and(RateLimiter::attempts('open-meteo:day'))->toBe(2);
    Http::assertSentCount(2);
});

it('sends nothing once the day\'s allowance is used', function () {
    Sleep::fake();
    Log::spy();
    Http::fake();
    weatherSpot();
    RateLimiter::increment('open-meteo:day', 86400, 8000);

    $this->artisan('ingest:weather')->assertFailed();

    Http::assertNothingSent();
});

it('shows the weather now, the next hours and the daylight on a spot page', function () {
    $this->travelTo(Carbon::parse('2026-07-01 09:20', 'Europe/London'));
    $spot = weatherSpot();
    WeatherForecast::factory()->at($spot->latitude, $spot->longitude)->create();

    $this->get('/yorkshire/rivers/wharfe/ilkley')
        ->assertOk()
        ->assertSeeInOrder(['Weather', '14°C', 'Partly cloudy', 'Feels like 12°C', '12 mph SW', '25 mph', 'UV today', '3', 'moderate'])
        ->assertSeeInOrder(['11:00', '13°', '10%', '13:00'])
        ->assertSeeInOrder(['First light', 'Sunrise', 'Sunset', 'Last light'])
        ->assertSee('Forecast for the area around this spot from')
        ->assertSee('https://open-meteo.com/');
});

it('warns when the forecast is old and says when there is none', function () {
    $spot = weatherSpot();
    WeatherForecast::factory()->at($spot->latitude, $spot->longitude)->create(['fetched_at' => now()->subHours(8)]);

    $this->get('/yorkshire/rivers/wharfe/ilkley')->assertOk()->assertSee('so it may have changed since');

    WeatherForecast::query()->delete();

    $this->get('/yorkshire/rivers/wharfe/ilkley')->assertOk()->assertSee('No forecast for this spot yet.')->assertSee('Sunrise');
});

it('works out first light, sunrise, sunset and last light from a place\'s position', function () {
    $sun = SunTimes::at(53.92, -1.82, Carbon::parse('2026-06-21 12:00', 'Europe/London'));

    expect($sun->sunrise->format('H:i'))->toBeGreaterThanOrEqual('04:30')->toBeLessThanOrEqual('04:50')
        ->and($sun->sunset->format('H:i'))->toBeGreaterThanOrEqual('21:35')->toBeLessThanOrEqual('21:55')
        ->and($sun->firstLight->lessThan($sun->sunrise))->toBeTrue()
        ->and($sun->lastLight->greaterThan($sun->sunset))->toBeTrue();
});

it('gives advice for each kind of place before you go in', function () {
    $spot = weatherSpot();
    $region = $spot->river->region;
    $beach = BathingWater::factory()->create(['region_id' => $region->id]);
    $lake = Lake::factory()->create(['region_id' => $region->id]);

    $this->get('/yorkshire/rivers/wharfe/ilkley')->assertOk()->assertSeeInOrder(['Before you go in', 'Cold water', 'Rivers can be faster than they look', 'Fishing and paddling', 'keep well clear of weirs', 'In an emergency call 999 and ask for the Fire Service.']);
    $this->get(route('beaches.show', [$region, $beach]))->assertOk()->assertSee('Rip currents')->assertSee('offshore wind')->assertSee('ask for the Coastguard');
    $this->get(route('lakes.show', [$region, $lake]))->assertOk()->assertSee('blue-green algae')->assertSee('within reach of the shore')->assertSee('ask for the Fire Service');
});

it('has an icon for every weather code it can name, and none for an unknown code', function () {
    $named = collect(range(0, 99))->filter(fn (int $code) => WeatherCode::label($code) !== null);

    expect($named)->toHaveCount(28)
        ->and($named->reject(fn (int $code) => WeatherCode::icon($code) !== null))->toBeEmpty()
        ->and(WeatherCode::icon(80))->toBe('showers')
        ->and(WeatherCode::icon(61))->toBe('rain')
        ->and(WeatherCode::icon(42))->toBeNull();
});

it('shows the moon for clear hours after dark', function () {
    $this->travelTo(Carbon::parse('2026-10-17 19:30', 'Europe/London'));
    $spot = weatherSpot();
    WeatherForecast::factory()->at($spot->latitude, $spot->longitude)->create();
    $sun = SunTimes::at($spot->latitude, $spot->longitude);

    expect($sun->isDark(now()))->toBeTrue()
        ->and($sun->isDark(Carbon::parse('2026-10-18 01:00', 'Europe/London')))->toBeTrue()
        ->and($sun->isDark(Carbon::parse('2026-10-17 13:00', 'Europe/London')))->toBeFalse();

    $this->get('/yorkshire/rivers/wharfe/ilkley')
        ->assertOk()
        ->assertSee('data-weather-icon="partly-cloudy-night"', false)
        ->assertSee('data-weather-icon="rain"', false);
});
