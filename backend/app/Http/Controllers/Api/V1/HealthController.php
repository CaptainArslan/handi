<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Dish;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * @tags Health
 */
class HealthController extends Controller
{
    /**
     * API health check
     *
     * Public, no token. Answers **200** when the API and everything it depends on works, **503** when a
     * check fails, so it can be used by the app, uptime monitors and load balancers alike.
     * Checks: database (query + seeded catalog), cache (write/read) and photo storage (write).
     * Never exposes secrets: only `ok` / `fail` and timings per check (error text only when `APP_DEBUG` is on).
     *
     * @unauthenticated
     */
    public function __invoke(): JsonResponse
    {
        $checks = [
            'database' => $this->check(function () {
                DB::select('select 1');

                return ['categories' => Category::count(), 'library_dishes' => Dish::count()];
            }),
            'cache' => $this->check(function () {
                $key = 'health:'.Str::random(8);
                Cache::put($key, 'ok', 10);
                $ok = Cache::pull($key) === 'ok';
                if (! $ok) {
                    throw new \RuntimeException('Cache read-back failed.');
                }

                return [];
            }),
            'storage' => $this->check(function () {
                $disk = Storage::disk(config('handi.media_disk', 'public'));
                $path = '.health-'.Str::random(8);
                $disk->put($path, 'ok');
                $disk->delete($path);

                return [];
            }),
        ];

        $healthy = collect($checks)->every(fn (array $c) => $c['status'] === 'ok');

        return response()->json([
            'status' => $healthy ? 'ok' : 'degraded',
            'app' => config('app.name'),
            'version' => config('scramble.info.version', '1.0.0'),
            'environment' => app()->environment(),
            'time' => now()->toIso8601String(),
            'checks' => $checks,
        ], $healthy ? 200 : 503)->header('Cache-Control', 'no-store');
    }

    /** @return array{status: string, ms: float} */
    private function check(callable $probe): array
    {
        $start = microtime(true);
        try {
            $extra = $probe() ?: [];

            return ['status' => 'ok', 'ms' => round((microtime(true) - $start) * 1000, 1)] + $extra;
        } catch (Throwable $e) {
            report($e);

            return ['status' => 'fail', 'ms' => round((microtime(true) - $start) * 1000, 1)]
                + (config('app.debug') ? ['error' => $e->getMessage()] : []);
        }
    }
}
