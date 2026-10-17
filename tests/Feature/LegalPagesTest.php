<?php

use Illuminate\Support\Carbon;
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
    $this->get(route('sitemap'))->assertOk()->assertSee(route('privacy'))->assertSee(route('terms'))
        // Dated from the same entry each page shows.
        ->assertSeeInOrder(['<loc>'.route('terms').'</loc>', '<lastmod>'.Carbon::parse(config('app.pages_updated.terms'), 'Europe/London')->utc()->toAtomString().'</lastmod>'], false);

    $this->get(route('terms'))->assertSeeText('Last updated '.Carbon::parse(config('app.pages_updated.terms'))->format('j F Y'));
});

it('explains what the monitors record and offers an email contact on the about page', function () {
    Region::factory()->create(['name' => 'North East', 'slug' => 'north-east']);
    config(['app.contact_email' => 'contact@example.test']);

    $this->get(route('about'))
        ->assertOk()
        ->assertSee('in the North East')
        ->assertSee('not how much it released')
        ->assertSee('12/24 method')
        ->assertSee('mailto:contact@example.test', false)
        ->assertSee('<time datetime="', false)
        ->assertSee('"dateModified"', false);
});

it('describes the share image and gives phones a search box on the search page', function () {
    Region::factory()->create(['name' => 'Yorkshire', 'slug' => 'yorkshire']);

    $this->get(route('home'))
        ->assertOk()
        ->assertSee('<meta property="og:image:alt" content="How&#039;s the Water? What&#039;s the river doing right now?', false)
        ->assertSee('<meta name="twitter:image:alt"', false)
        ->assertSee('data-search-toggle', false);

    $this->get(route('search'))->assertOk()->assertSee('id="page-search"', false);
});
