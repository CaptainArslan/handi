<?php

namespace App\Providers\Filament;

use App\Filament\Pages\Dashboard;
use App\Filament\Support\Avatar;
use App\Filament\Support\BrandColors;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\FontProviders\LocalFontProvider;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Enums\Width;
use Filament\View\PanelsRenderHook;
use Illuminate\Support\Facades\Blade;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login()
            ->profile()
            ->brandName('Handi Admin')
            ->brandLogo(fn () => view('filament.brand'))
            ->brandLogoHeight('2.5rem')
            ->favicon(asset('images/brand/handi-mark.png'))
            ->font('Poppins', url: asset('css/handi-fonts.css'), provider: LocalFontProvider::class)
            ->darkMode(false)
            ->defaultAvatarProvider(Avatar::class)
            ->sidebarCollapsibleOnDesktop()
            ->maxContentWidth(Width::Full)
            ->spa()
            ->unsavedChangesAlerts()
            // Brand theme (plain CSS, no build step) + bundled Poppins font.
            ->renderHook(PanelsRenderHook::HEAD_END, fn () => Blade::render(<<<'HTML'
                <link rel="stylesheet" href="{{ asset('css/handi-admin.css') }}?v=8">
                <script>
                    // Logo animation: respect "reduce motion" and keep it playing after SPA navigation.
                    (function () {
                        const reduce = window.matchMedia('(prefers-reduced-motion: reduce)');
                        const sync = () => document.querySelectorAll('video.handi-logo-video').forEach((v) => {
                            if (reduce.matches) { v.pause(); v.removeAttribute('autoplay'); } else { v.muted = true; v.play().catch(() => {}); }
                        });
                        document.addEventListener('DOMContentLoaded', sync);
                        document.addEventListener('livewire:navigated', sync);
                    })();
                </script>
                HTML))
            // Topbar: today's date (left of search) and the signed-in admin (left of the avatar).
            ->renderHook(PanelsRenderHook::GLOBAL_SEARCH_BEFORE, fn () => Blade::render(<<<'HTML'
                <span class="handi-topbar-date">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><rect x="3.5" y="5" width="17" height="15" rx="3"/><path d="M3.5 10h17M8 3v4M16 3v4"/></svg>
                    {{ now(config('handi.timezone'))->format('D, j M Y') }}
                </span>
                HTML))
            ->renderHook(PanelsRenderHook::USER_MENU_BEFORE, fn () => Blade::render(<<<'HTML'
                @if ($user = filament()->auth()->user())
                    <span class="handi-topbar-user">
                        <span class="handi-topbar-user-name">{{ $user->name }}</span>
                        <span class="handi-topbar-user-role">Super admin</span>
                    </span>
                @endif
                HTML))
            ->renderHook(PanelsRenderHook::SIDEBAR_FOOTER, fn () => Blade::render(<<<'HTML'
                <div class="handi-sidebar-footer"><strong>What to Cook</strong>Super admin · {{ config('app.env') }}</div>
                HTML))
            ->renderHook(PanelsRenderHook::AUTH_LOGIN_FORM_BEFORE, fn () => Blade::render('<p class="handi-login-note">Super admin access only</p>'))
            ->renderHook(PanelsRenderHook::SIMPLE_LAYOUT_END, fn () => '<div class="handi-login-foot">© '.date('Y').' Handi · What to Cook</div>')
            ->colors([
                'primary' => BrandColors::PRIMARY,
                'warning' => BrandColors::WARNING,
                'danger' => BrandColors::DANGER,
                'success' => BrandColors::SUCCESS,
                'gray' => BrandColors::GRAY,
            ])
            ->navigationGroups(['People', 'Dish library'])
            ->globalSearchKeyBindings(['command+k', 'ctrl+k'])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\\Filament\\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\\Filament\\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\\Filament\\Widgets')
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                PreventRequestForgery::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
