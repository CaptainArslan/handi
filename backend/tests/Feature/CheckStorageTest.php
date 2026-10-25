<?php

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

it('passes when the photo bucket works, is public and allows the admin origin', function () {
    Storage::fake('s3');
    Http::fake(['*' => Http::response('handi storage check', 200, ['Access-Control-Allow-Origin' => '*'])]);
    config([
        'handi.media_disk' => 's3',
        'filesystems.disks.s3' => array_merge(config('filesystems.disks.s3'), [
            'key' => 'k', 'secret' => 's', 'bucket' => 'handi-media', 'region' => 'auto',
            'endpoint' => 'https://abc.r2.cloudflarestorage.com', 'url' => 'https://media.example.com',
        ]),
    ]);

    $this->artisan('handi:check-storage')
        ->expectsOutputToContain('Upload works')
        ->expectsOutputToContain('Public URL opens')
        ->expectsOutputToContain('CORS allows')
        ->expectsOutputToContain('Storage looks good.')
        ->assertSuccessful();
});

it('warns when only FILESYSTEM_DISK was switched to s3', function () {
    Storage::fake('public');
    Http::fake(['*' => Http::response('ok', 200)]);
    config(['filesystems.default' => 's3', 'handi.media_disk' => 'public']);

    $this->artisan('handi:check-storage')
        ->expectsOutputToContain('Set MEDIA_DISK=s3')
        ->assertFailed();
});

it('explains a broken public URL and wrong R2 settings', function () {
    Storage::fake('s3');
    Http::fake(['*' => Http::response('denied', 403)]);
    config([
        'handi.media_disk' => 's3',
        'filesystems.disks.s3' => array_merge(config('filesystems.disks.s3'), [
            'key' => 'k', 'secret' => 's', 'bucket' => 'handi-media', 'region' => 'us-east-1',
            'endpoint' => 'https://abc.r2.cloudflarestorage.com/handi-media', 'url' => '',
        ]),
    ]);

    $this->artisan('handi:check-storage')
        ->expectsOutputToContain('R2 needs AWS_DEFAULT_REGION=auto')
        ->expectsOutputToContain('must not end with the bucket name')
        ->expectsOutputToContain('AWS_URL is empty')
        ->expectsOutputToContain('returns HTTP 403')
        ->assertFailed();
});
