<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed();
});

test('the application returns a successful response', function () {
    $this->get('/')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Public/Home'));
});

test('browser document visits receive the html app shell even if an inertia header is present', function () {
    $response = $this->withHeaders([
        'Accept' => 'text/html,application/xhtml+xml',
        'Sec-Fetch-Dest' => 'document',
        'Sec-Fetch-Mode' => 'navigate',
        'X-Inertia' => 'true',
    ])->get('/');

    $response
        ->assertOk()
        ->assertHeaderMissing('X-Inertia')
        ->assertSee('id="app"', false)
        ->assertSee('data-page="app"', false);

    expect($response->headers->get('content-type'))->toContain('text/html');
});

test('inertia xhr visits still return json', function () {
    $version = app(\App\Http\Middleware\HandleInertiaRequests::class)->version(request()) ?? '';

    $this->withHeaders([
        'X-Inertia' => 'true',
        'X-Inertia-Version' => $version,
        'X-Requested-With' => 'XMLHttpRequest',
        'Sec-Fetch-Dest' => 'empty',
        'Sec-Fetch-Mode' => 'cors',
    ])->get('/')
        ->assertOk()
        ->assertHeader('X-Inertia', 'true')
        ->assertJsonPath('component', 'Public/Home');
});
