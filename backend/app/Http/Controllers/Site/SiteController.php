<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Http\Requests\SiteContactRequest;
use App\Models\Category;
use App\Models\Dish;
use App\Services\ContactService;
use App\Support\CatalogCache;
use App\Support\Media;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\ViewErrorBag;
use Illuminate\View\View;

/**
 * The public website at the domain root: the product page, legal pages and the
 * account-deletion page the app stores require. The admin panel is not linked from here.
 */
class SiteController extends Controller
{
    public function home(): View
    {
        $library = CatalogCache::remember('site:library', function () {
            $dishes = Dish::active()->with('category')->orderBy('name')->get();

            return [
                'total' => $dishes->count(),
                'categories' => Category::active()->orderBy('sort_order')->get()
                    ->map(fn (Category $c) => [
                        'name' => $c->name,
                        'dishes' => $dishes->where('category_id', $c->id)->pluck('name')->take(8)->values()->all(),
                        'count' => $dishes->where('category_id', $c->id)->count(),
                    ])
                    ->filter(fn ($c) => $c['count'] > 0)
                    ->values()
                    ->all(),
            ];
        });

        return view('site.home', [
            'library' => $library,
            'art' => self::art(),
            'today' => Carbon::now(config('handi.timezone')),
        ]);
    }

    public function legal(string $document): View
    {
        return view('site.legal', [
            'doc' => config('legal.'.$document),
            'document' => $document,
            'updated' => Carbon::parse(config('legal.updated_at')),
        ]);
    }

    public function support(): View
    {
        return view('site.support');
    }

    /** Contact form. `?sent=1` shows the thank-you message after a successful send. */
    public function contact(Request $request): View
    {
        return view('site.contact', ['errors' => new ViewErrorBag, 'old' => [], 'sent' => $request->boolean('sent')]);
    }

    /** No session on the site: errors re-render the form (SiteContactRequest), success redirects (no double send on refresh). */
    public function sendContact(SiteContactRequest $request, ContactService $contacts): RedirectResponse
    {
        $contacts->submit([...$request->validated(), 'device' => 'Website'], $request->file('attachment'), null, $request);

        return redirect()->to(route('site.contact', ['sent' => 1]).'#form', 303);
    }

    public function deleteAccount(): View
    {
        return view('site.delete-account');
    }

    public function sitemap(): Response
    {
        $urls = [route('site.home'), route('site.privacy'), route('site.terms'), route('site.support'), route('site.contact'), route('site.delete-account')];

        return response()
            ->view('site.sitemap', ['urls' => $urls, 'updated' => config('legal.updated_at')])
            ->header('Content-Type', 'application/xml; charset=UTF-8');
    }

    /** Bundled dish art as small WebP files (public/images/site/{key}-{size}.webp) with each tile's tint. */
    public static function art(): array
    {
        return collect(['biryani', 'karahi', 'lentils', 'bhindi', 'qeema', 'paratha', 'karhi', 'aloogobi', 'fish'])
            ->mapWithKeys(fn (string $k) => [$k => ['bg' => Media::artBg($k), 'src' => asset("images/site/{$k}-160.webp"), 'src2x' => asset("images/site/{$k}-320.webp"), 'lg' => asset("images/site/{$k}-240.webp"), 'lg2x' => asset("images/site/{$k}-480.webp")]])
            ->all();
    }
}
