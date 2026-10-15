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

it('lists the legal pages in the sitemap', function () {
    $this->get(route('sitemap'))->assertOk()->assertSee(route('privacy'))->assertSee(route('terms'));
});
