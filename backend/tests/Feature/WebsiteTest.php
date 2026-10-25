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
        ->assertSee('/app/welcome', false)
        ->assertSee('/app/login', false)
        ->assertSee('Google Play')
        ->assertSee('App Store')
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
    ['/support', 'How can we help?'],
    ['/contact', 'Send us a message'],
    ['/sitemap.xml', '/contact'],
    ['/sitemap.xml', '/support'],
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

it('only upgrades insecure requests over https, so local http sites keep their images', function () {
    expect($this->get('/')->headers->get('Content-Security-Policy'))->not->toContain('upgrade-insecure-requests');
    expect($this->get('https://localhost/')->headers->get('Content-Security-Policy'))->toContain('upgrade-insecure-requests');
});

it('inlines the site css with root-relative font urls', function () {
    $html = $this->get('/')->getContent();
    expect($html)->toContain("url('/fonts/site/poppins-400.woff2')")->not->toContain("url('http");
});

it('shows store links once they are set', function () {
    config(['handi.store_links.android' => 'https://play.google.com/store/apps/details?id=com.hegemonic.handi']);
    $this->get('/')->assertSee('https://play.google.com/store/apps/details?id=com.hegemonic.handi', false)->assertSee('Get it on');
});

it('serves the web app page for every /app address', function (string $url) {
    $shell = public_path('app/shell.html');
    if (! is_file($shell)) {
        $this->markTestSkipped('Web app not built (npm run build:web).');
    }

    $this->get($url)
        ->assertOk()
        ->assertSee('<div id="root">', false)
        ->assertHeader('Cache-Control')
        ->assertHeader('Permissions-Policy', 'camera=(self), microphone=(), geolocation=(self), interest-cohort=()')
        ->assertCookieMissing('laravel_session');
    expect($this->get($url)->headers->get('Content-Security-Policy'))->toContain("script-src 'self'")->toContain("connect-src 'self' blob: data:");
})->with(['/app', '/app/welcome', '/app/today', '/app/setup/family', '/app/contact']);

it('returns 404 for missing web app files instead of the app page', function () {
    $this->get('/app/_expo/static/js/web/old-build.js')->assertNotFound();
});

it('points email buttons at the web app by default', function () {
    expect(config('handi.app_links.today'))->toEndWith('/app/today')
        ->and(config('handi.app_links.reset_password'))->toEndWith('/app/forgot-password');
});
