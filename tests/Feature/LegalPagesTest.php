<?php

use App\Models\Region;

it('serves the privacy and terms pages ahead of region slugs', function () {
    Region::factory()->create(['name' => 'Yorkshire', 'slug' => 'yorkshire']);

    $this->get('/privacy')->assertOk()->assertSee('<h1', false)->assertSee('Your rights');
    $this->get('/terms')->assertOk()->assertSee('Reusing the data');
});

it('credits each data source under its own licence in the footer and links the legal pages', function () {
    Region::factory()->create(['name' => 'Yorkshire', 'slug' => 'yorkshire']);

    $this->get(route('home'))
        ->assertOk()
        ->assertSee('real-time data API (Beta)')
        ->assertSee('via Stream, CC BY 4.0', false)
        ->assertSee('contain OS data © Crown copyright and database right', false)
        ->assertSee(route('privacy'))
        ->assertSee(route('terms'));
});

it('offers the analytics consent banner only in production', function () {
    Region::factory()->create(['name' => 'Yorkshire', 'slug' => 'yorkshire']);
    config(['services.google_analytics.measurement_id' => 'G-TEST123']);

    $this->get(route('home'))->assertOk()->assertDontSee('data-cookie-banner', false)->assertDontSee('G-TEST123');

    app()->detectEnvironment(fn () => 'production');

    $this->get(route('home'))->assertOk()
        ->assertSee('data-measurement-id="G-TEST123"', false)
        ->assertSee('Cookie settings')
        ->assertDontSee('googletagmanager.com');
});

it('sets no cookies and stores no session for visitors', function () {
    Region::factory()->create(['name' => 'Yorkshire', 'slug' => 'yorkshire']);

    $response = $this->get(route('home'))->assertOk();

    expect($response->headers->getCookies())->toBeEmpty();
    $this->assertDatabaseCount('sessions', 0);
});

it('credits Stream companies and Scottish Water separately in the footer', function () {
    Region::factory()->create(['name' => 'North East', 'slug' => 'north-east', 'overflow_feeds' => ['northumbrian_water', 'scottish_water']]);

    $this->get(route('home'))
        ->assertOk()
        ->assertSee('Northumbrian Water storm overflow data via Stream, CC BY 4.0.')
        ->assertSee('Overflow data from Scottish Water.');
});

it('lists the legal pages in the sitemap', function () {
    $this->get(route('sitemap'))->assertOk()->assertSee(route('privacy'))->assertSee(route('terms'));
});
