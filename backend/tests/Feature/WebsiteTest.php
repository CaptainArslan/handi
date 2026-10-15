<?php

use App\Models\User;
use App\Providers\TelescopeServiceProvider;

beforeEach(fn () => seedCatalog());

it('serves the website at the domain root', function () {
    $this->get('/')
        ->assertOk()
        ->assertSee('Aaj kya pakaen?')
        ->assertSee('Pick. Cook. Log.')
        ->assertSee('Tell us about your family')
        ->assertSee('Save your kitchen')
        ->assertDontSee('/admin')
        ->assertHeader('Content-Security-Policy')
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertCookieMissing('laravel_session');
});

it('serves the legal, account-deletion and sitemap pages', function (string $url, string $text) {
    $this->get($url)->assertOk()->assertSee($text);
})->with([
    ['/privacy', 'Privacy policy'],
    ['/terms', 'Terms of use'],
    ['/delete-account', 'Delete your Handi account'],
    ['/sitemap.xml', '/delete-account'],
]);

it('shows a branded 404', function () {
    $this->get('/no-such-page')->assertNotFound()->assertSee('This pot is empty');
});

it('keeps the admin reachable only by its URL', function () {
    $this->get('/admin/login')->assertOk()->assertSee('noindex', false);
});

it('lets only the allowed admin email open Telescope', function () {
    config(['telescope.allowed_emails' => ['admin@handi.test']]);
    $owner = User::factory()->create(['is_admin' => true, 'email' => 'admin@handi.test']);
    $otherAdmin = User::factory()->create(['is_admin' => true, 'email' => 'boss@handi.test']);
    $user = User::factory()->create(['is_admin' => false, 'email' => 'cook@handi.test']);

    expect(TelescopeServiceProvider::allowed($owner))->toBeTrue()
        ->and(TelescopeServiceProvider::allowed($otherAdmin))->toBeFalse()
        ->and(TelescopeServiceProvider::allowed($user))->toBeFalse()
        ->and(TelescopeServiceProvider::allowed(null))->toBeFalse();
});

it('sends baseline security headers on the admin and the API too', function () {
    $this->get('/admin/login')->assertHeader('X-Content-Type-Options', 'nosniff')->assertHeader('X-Frame-Options', 'SAMEORIGIN');
    $this->getJson('/api/v1/health')->assertHeader('X-Content-Type-Options', 'nosniff');
    $this->get('/')->assertHeader('X-Frame-Options', 'DENY');
});
