<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * Explains why dish photos don't reach S3 / Cloudflare R2 or don't show:
 * wrong .env key, cached config, bad credentials, bucket not public, missing CORS.
 */
class CheckStorage extends Command
{
    protected $signature = 'handi:check-storage';

    protected $description = 'Check where dish photos are stored and whether that storage works (upload, public URL, CORS for admin previews).';

    private int $problems = 0;

    public function handle(): int
    {
        $disk = (string) config('handi.media_disk');
        $appUrl = rtrim((string) config('app.url'), '/');

        $this->line('');
        $this->line('<options=bold>Settings in use</>');
        $this->table(['Setting', 'Value'], [
            ['MEDIA_DISK (dish photos go here)', $disk],
            ['FILESYSTEM_DISK (not used for photos)', (string) config('filesystems.default')],
            ['APP_URL', $appUrl],
            ['Config cached', app()->configurationIsCached() ? 'yes (run php artisan config:clear after .env changes)' : 'no'],
        ]);

        if (app()->environment('production') && Str::startsWith($appUrl, 'http://')) {
            $this->problem('APP_URL starts with http:// but the site runs on https, so the browser blocks photo previews in the admin (mixed content). Set APP_URL=https://… in .env.');
        }

        if (config('filesystems.default') === 's3' && $disk !== 's3') {
            $this->problem('FILESYSTEM_DISK is s3 but dish photos use MEDIA_DISK, which is still "'.$disk.'". Set MEDIA_DISK=s3 in .env, then php artisan config:clear && php artisan optimize.');
        }

        if (! array_key_exists($disk, (array) config('filesystems.disks'))) {
            $this->problem("MEDIA_DISK=\"{$disk}\" is not a disk in config/filesystems.php. Use public or s3.");

            return self::FAILURE;
        }

        if (config("filesystems.disks.{$disk}.driver") === 's3') {
            $this->checkS3Settings($disk);
        } elseif (! file_exists(public_path('storage'))) {
            $this->problem('public/storage link is missing, so photos 404. Run: php artisan storage:link');
        }

        $this->line('');
        $this->line('<options=bold>Live test on "'.$disk.'"</>');
        $path = 'handi-storage-check/'.Str::uuid().'.txt';
        $storage = Storage::disk($disk);

        try {
            if ($storage->put($path, 'handi storage check') === false) {
                throw new \RuntimeException('The disk refused the file (no details; set \'throw\' => true on this disk to see why).');
            }
            $this->ok('Upload works');
        } catch (Throwable $e) {
            $this->problem('Upload FAILED: '.$this->explain($e));
            $this->summary();

            return self::FAILURE;
        }

        try {
            $storage->get($path) === 'handi storage check' ? $this->ok('Read back works') : $this->problem('File uploaded but reads back different.');
        } catch (Throwable $e) {
            $this->problem('Read back FAILED: '.$this->explain($e));
        }

        $url = $storage->url($path);
        $url = Str::startsWith($url, '/') ? $appUrl.$url : $url;
        $this->line("  Public URL: {$url}");

        try {
            $response = Http::timeout(10)->withHeaders(['Origin' => $appUrl])->get($url);

            if ($response->successful()) {
                $this->ok('Public URL opens (the app and website can show photos)');
            } else {
                $this->problem("Public URL returns HTTP {$response->status()}, so photos won't show in the app or admin. "
                    .(config("filesystems.disks.{$disk}.driver") === 's3'
                        ? 'Make the bucket publicly readable: Cloudflare R2 → bucket → Settings → Public access (custom domain or r2.dev) and put that address in AWS_URL; AWS S3 → turn off "Block public access" and add a public-read bucket policy.'
                        : 'Check APP_URL and php artisan storage:link.'));
            }

            $cors = $response->header('Access-Control-Allow-Origin');
            if (config("filesystems.disks.{$disk}.driver") === 's3') {
                if ($cors === '*' || $cors === $appUrl) {
                    $this->ok("CORS allows {$appUrl} (admin photo previews work)");
                } else {
                    $this->problem("The bucket has no CORS rule for {$appUrl}, so the admin \"Edit dish\" page can't preview the photo. Add this CORS policy to the bucket (R2: bucket → Settings → CORS policy; S3: Permissions → CORS):");
                    $this->line(json_encode([[
                        'AllowedOrigins' => [$appUrl],
                        'AllowedMethods' => ['GET', 'HEAD'],
                        'AllowedHeaders' => ['*'],
                        'MaxAgeSeconds' => 3600,
                    ]], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
                }
            }
        } catch (Throwable $e) {
            $this->problem('Could not open the public URL: '.$e->getMessage());
        }

        try {
            $storage->delete($path);
        } catch (Throwable) {
            // a leftover test file is harmless
        }

        $this->line('');
        $this->line('<options=bold>Photos already stored</>');
        $rows = DB::table('media')->selectRaw('disk, count(*) as n')->groupBy('disk')->pluck('n', 'disk');
        if ($rows->isEmpty()) {
            $this->line('  No photos yet.');
        }
        foreach ($rows as $name => $count) {
            $this->line("  {$count} on \"{$name}\"".($name !== $disk ? " — older photos stay there and keep working; only new uploads go to \"{$disk}\"." : ''));
        }

        return $this->summary();
    }

    private function checkS3Settings(string $disk): void
    {
        $c = (array) config("filesystems.disks.{$disk}");
        $missing = collect(['key' => 'AWS_ACCESS_KEY_ID', 'secret' => 'AWS_SECRET_ACCESS_KEY', 'bucket' => 'AWS_BUCKET', 'region' => 'AWS_DEFAULT_REGION'])
            ->filter(fn ($env, $key) => blank($c[$key] ?? null))->values();

        if ($missing->isNotEmpty()) {
            $this->problem('Missing in .env: '.$missing->join(', '));
        }

        $endpoint = (string) ($c['endpoint'] ?? '');
        $isR2 = str_contains($endpoint, 'r2.cloudflarestorage.com');

        if ($isR2) {
            if (($c['region'] ?? '') !== 'auto') {
                $this->problem('Cloudflare R2 needs AWS_DEFAULT_REGION=auto.');
            }
            if (Str::endsWith(rtrim($endpoint, '/'), '/'.($c['bucket'] ?? ''))) {
                $this->problem('AWS_ENDPOINT must not end with the bucket name. Use https://<account-id>.r2.cloudflarestorage.com');
            }
            if (blank($c['url'] ?? null)) {
                $this->problem('AWS_URL is empty. R2 files are only public through the bucket\'s custom domain or r2.dev address: bucket → Settings → Public access, then put that address in AWS_URL.');
            }
        } elseif ($endpoint !== '') {
            if (str_contains($endpoint, 'amazonaws.com')) {
                $this->problem('For Amazon S3 leave AWS_ENDPOINT empty (it is only for R2 and other S3-compatible storage).');
            }
        } elseif (($c['region'] ?? '') === 'auto') {
            $this->problem('AWS_DEFAULT_REGION=auto is only for R2. For Amazon S3 use the bucket\'s region, e.g. ap-south-1 or us-east-1.');
        }
    }

    private function explain(Throwable $e): string
    {
        $m = $e->getMessage().' '.($e->getPrevious()?->getMessage() ?? '');

        return match (true) {
            str_contains($m, 'InvalidAccessKeyId') => 'AWS_ACCESS_KEY_ID is wrong (or the token was deleted).',
            str_contains($m, 'SignatureDoesNotMatch') => 'AWS_SECRET_ACCESS_KEY is wrong (copy it again; it is shown only once when the token is made).',
            str_contains($m, 'NoSuchBucket') => 'AWS_BUCKET "'.config('filesystems.disks.s3.bucket').'" does not exist at this endpoint/region.',
            str_contains($m, 'AccessDenied') => 'Access denied: the key has no write permission on this bucket (R2 token needs "Object Read & Write" for this bucket).',
            str_contains($m, 'AuthorizationHeaderMalformed'), str_contains($m, 'PermanentRedirect') => 'Wrong AWS_DEFAULT_REGION for this bucket.',
            str_contains($m, 'Could not resolve host'), str_contains($m, 'cURL error 6') => 'AWS_ENDPOINT host can\'t be reached; check the address.',
            str_contains($m, 'AccessControlListNotSupported') => 'The bucket blocks ACLs; remove "visibility" from the s3 disk config.',
            default => Str::limit(trim($m), 400),
        };
    }

    private function ok(string $text): void
    {
        $this->line("  <fg=green>✓</> {$text}");
    }

    private function problem(string $text): void
    {
        $this->problems++;
        $this->line("  <fg=red>✗</> {$text}");
    }

    private function summary(): int
    {
        $this->line('');
        if ($this->problems === 0) {
            $this->info('Storage looks good.');

            return self::SUCCESS;
        }
        $this->error("{$this->problems} problem(s) found. Fix them, run php artisan config:clear && php artisan optimize, then run this again.");

        return self::FAILURE;
    }
}
