@extends('site.layout')

@php
    $img = fn (string $k, int $size = 56, string $alt = '', bool $lazy = true) =>
        '<img src="'.$art[$k]['src'].'" srcset="'.$art[$k]['src'].' 1x, '.$art[$k]['src2x'].' 2x" width="'.$size.'" height="'.$size.'" alt="'.e($alt).'" style="background:'.$art[$k]['bg'].'"'.($lazy ? ' loading="lazy" decoding="async"' : ' decoding="async"').'>';
    $android = config('handi.store_links.android');
    $icons = [
        'sun' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"/></svg>',
        'repeat' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M17 2l4 4-4 4"/><path d="M3 11V9a3 3 0 0 1 3-3h15M7 22l-4-4 4-4"/><path d="M21 13v2a3 3 0 0 1-3 3H3"/></svg>',
        'wallet' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="6" width="18" height="14" rx="3"/><path d="M3 10h18M16 15h2"/></svg>',
        'offline' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M2 2l20 20M8.5 16.5a5 5 0 0 1 7 0M5 12.9a10 10 0 0 1 5.2-2.8M19 12.9a10 10 0 0 0-2.3-1.6M12 20h.01"/></svg>',
        'bell' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9M10 21h4"/></svg>',
        'camera' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 8h3l2-3h6l2 3h3v12H4z"/><circle cx="12" cy="13" r="3.5"/></svg>',
        'arrow' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6"/></svg>',
    ];
@endphp

@push('head')
    <link rel="preload" as="image" href="{{ $art['biryani']['src'] }}" imagesrcset="{{ $art['biryani']['src'] }} 1x, {{ $art['biryani']['src2x'] }} 2x">
    <script type="application/ld+json">{!! json_encode([
        '@context' => 'https://schema.org',
        '@type' => 'MobileApplication',
        'name' => 'Handi — What to Cook',
        'operatingSystem' => 'Android',
        'applicationCategory' => 'LifestyleApplication',
        'description' => 'Three dishes your family already cooks, suggested every morning and matched to the weather.',
        'offers' => ['@type' => 'Offer', 'price' => '0', 'priceCurrency' => 'PKR'],
        'url' => url('/'),
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}</script>
@endpush

@section('content')
    {{-- Hero --}}
    <section class="hero" aria-labelledby="hero-title">
        <div class="wrap hero-grid">
            <div>
                <span class="eyebrow">For home cooks in Pakistan</span>
                <h1 id="hero-title"><span class="urdu">Aaj kya pakaen?</span><br>Three dishes every morning, <em>from your own kitchen.</em></h1>
                <p class="lead">Handi learns the dishes your family already cooks, then suggests three every day, matched to today’s weather and what you haven’t had lately. One tap logs dinner, and tomorrow gets smarter.</p>
                <div class="hero-actions">
                    <a class="btn btn-primary" href="#download">Get the app {!! $icons['arrow'] !!}</a>
                    <a class="btn btn-ghost" href="#how">See how it works</a>
                </div>
                <p class="hero-note"><i aria-hidden="true"></i> Free · set up in about 2 minutes · no ads</p>
            </div>

            <div class="hero-art">
                <div class="phone" role="img" aria-label="The Handi app showing today’s three picks: Chicken Biryani, Daal Chawal and Aloo Gobi">
                    <div class="screen" aria-hidden="true">
                        <div class="notch"></div>
                        <div>
                            <div class="app-date">{{ strtoupper($today->format('l j F')) }}</div>
                            <div class="app-hello"><span class="av">AK</span><b>Good morning, Ayesha</b></div>
                        </div>
                        <div class="app-wx"><span class="sun"></span><span><b>31°</b> · Sunny in Lahore · light food today</span></div>
                        <div class="app-steps"><span class="on"><i></i>Pick</span><span><i></i>Cook</span><span><i></i>Log</span></div>
                        <div class="pick top">{!! $img('biryani', 56, '', false) !!}<div><b>Chicken Biryani</b><small>Last cooked 12 days ago</small><div class="tags"><span class="tag">Good for today</span><span class="tag amber">Medium effort</span></div></div></div>
                        <div class="pick">{!! $img('lentils', 56, '', false) !!}<div><b>Daal Chawal</b><small>Last cooked 9 days ago</small><div class="tags"><span class="tag">Quick</span><span class="tag amber">Low cost</span></div></div></div>
                        <div class="pick">{!! $img('aloogobi', 56, '', false) !!}<div><b>Aloo Gobi</b><small>Last cooked 2 weeks ago</small><div class="tags"><span class="tag">Light</span><span class="tag amber">Low cost</span></div></div></div>
                        <div class="app-cta">Cook Chicken Biryani today</div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- Pick → Cook → Log --}}
    <section class="section band-white" id="how" aria-labelledby="how-title">
        <div class="wrap">
            <div class="section-head center">
                <span class="eyebrow">How it works</span>
                <h2 id="how-title">Pick. Cook. Log. That’s the whole day.</h2>
                <p class="lead">No recipes to scroll, no menu to plan. Just three good answers to the question every kitchen asks.</p>
            </div>
            <div class="flow">
                <div class="flow-step"><span class="dot">1</span><h3>Pick</h3><p>At your reminder time, open today’s three dishes and choose one. Not feeling any? Shuffle.</p></div>
                <div class="flow-step"><span class="dot">2</span><h3>Cook</h3><p>Make it for lunch or dinner. Picks fit your family size, budget and how much effort you have today.</p></div>
                <div class="flow-step"><span class="dot">3</span><h3>Log</h3><p>One tap saves it to your history, so it won’t come back too soon and tomorrow’s picks get better.</p></div>
            </div>
        </div>
    </section>

    {{-- Onboarding, mirroring the app's six setup screens --}}
    <section class="section" id="setup" aria-labelledby="setup-title">
        <div class="wrap">
            <div class="section-head">
                <span class="eyebrow">Set up in about 2 minutes</span>
                <h2 id="setup-title">Tell Handi about your kitchen once</h2>
                <p class="lead">These are the same six steps you see in the app. You can see your first picks before you even create an account.</p>
            </div>
            <ol class="onboard">
                <li class="ob">
                    <div class="ob-top"><span class="ob-num">1</span><small>Family</small></div>
                    <div class="mini" aria-hidden="true">
                        <div class="bar"><i style="width:17%"></i></div>
                        <div class="row"><span>People eating</span><span class="stepper"><i>−</i>4<i>+</i></span></div>
                        <span>Budget</span>
                        <div class="seg"><span>Low</span><span class="on">Normal</span><span>High</span></div>
                    </div>
                    <h3>Tell us about your family</h3>
                    <p>How many people you cook for and your usual budget, so picks balance effort and cost.</p>
                </li>
                <li class="ob">
                    <div class="ob-top"><span class="ob-num">2</span><small>Dishes</small></div>
                    <div class="mini" aria-hidden="true">
                        <div class="bar"><i style="width:34%"></i></div>
                        <div class="field">Search dishes</div>
                        <div class="chips"><span class="chip on">✓ Biryani</span><span class="chip on">✓ Karahi</span><span class="chip">Haleem</span><span class="chip on">✓ Daal Chawal</span><span class="chip on">✓ Bhindi</span><span class="chip">Nihari</span><span class="chip on">✓ Qeema</span></div>
                        <div class="go">Continue with 12 dishes</div>
                    </div>
                    <h3>What does your family eat?</h3>
                    <p>Pick at least 10 from {{ $library['total'] ?: 'our' }} familiar dishes, or add your own family recipes.</p>
                </li>
                <li class="ob">
                    <div class="ob-top"><span class="ob-num">3</span><small>Preferences</small></div>
                    <div class="mini" aria-hidden="true">
                        <div class="bar"><i style="width:50%"></i></div>
                        <div class="row"><span>Lunch</span><span class="toggle"></span></div>
                        <div class="row"><span>Dinner</span><span class="toggle"></span></div>
                        <div class="row"><span>Breakfast</span><span class="toggle off"></span></div>
                        <div class="row"><span>Don’t repeat for</span><b>5 days</b></div>
                    </div>
                    <h3>A few preferences</h3>
                    <p>Which meals you plan and how long before a dish can come back, so the picks feel like your kitchen.</p>
                </li>
                <li class="ob">
                    <div class="ob-top"><span class="ob-num">4</span><small>Weather</small></div>
                    <div class="mini" aria-hidden="true">
                        <div class="bar"><i style="width:67%"></i></div>
                        <div class="loc"><span class="pin"><i></i></span><b>Cook for today’s weather</b><span style="color:var(--muted);font-size:12px">Hot day, light food. Rainy day, pakoras.</span></div>
                        <div class="go">Use my location</div>
                        <div class="go ghost">Choose city instead</div>
                    </div>
                    <h3>Cook for today’s weather</h3>
                    <p>Your location is rounded to about 1 km and never shared, or simply pick your city.</p>
                </li>
                <li class="ob">
                    <div class="ob-top"><span class="ob-num">5</span><small>Preview</small></div>
                    <div class="mini" aria-hidden="true">
                        <div class="bar"><i style="width:84%"></i></div>
                        <b>Here’s today for you</b>
                        <div class="mini-picks">
                            <div class="mini-pick">{!! $img('karahi', 30) !!}Chicken Karahi</div>
                            <div class="mini-pick">{!! $img('bhindi', 30) !!}Bhindi Masala</div>
                            <div class="mini-pick">{!! $img('paratha', 30) !!}Aloo Paratha</div>
                        </div>
                        <div class="go">Save my kitchen</div>
                    </div>
                    <h3>See today’s picks first</h3>
                    <p>Before you sign up, Handi shows the three dishes it would suggest for you right now.</p>
                </li>
                <li class="ob">
                    <div class="ob-top"><span class="ob-num">6</span><small>Save</small></div>
                    <div class="mini" aria-hidden="true">
                        <div class="bar"><i style="width:100%"></i></div>
                        <div class="field">e.g. John Doe</div>
                        <div class="field">name@example.com</div>
                        <div class="field">At least 8 characters</div>
                        <div class="go">Create account</div>
                    </div>
                    <h3>Save your kitchen</h3>
                    <p>Your dishes and history stay safe on any phone. Your first reminder comes the next morning.</p>
                </li>
            </ol>
        </div>
    </section>

    {{-- Features --}}
    <section class="section band-white" aria-labelledby="features-title">
        <div class="wrap">
            <div class="section-head">
                <span class="eyebrow">Why it works</span>
                <h2 id="features-title">Picks that feel like they came from your own kitchen</h2>
            </div>
            <div class="features">
                <article class="feature"><span class="ic amber">{!! $icons['sun'] !!}</span><h3>Matched to the weather</h3><p>Light food on hot days, something warm when it rains. Weather is checked for your city each morning.</p></article>
                <article class="feature"><span class="ic">{!! $icons['repeat'] !!}</span><h3>No repeats too soon</h3><p>Dishes you cooked recently sit out for as long as you choose, so the week stays varied.</p></article>
                <article class="feature"><span class="ic amber">{!! $icons['wallet'] !!}</span><h3>Fits budget and effort</h3><p>Every dish knows if it’s quick or heavy, cheap or special. Picks balance your day.</p></article>
                <article class="feature"><span class="ic">{!! $icons['offline'] !!}</span><h3>Works offline</h3><p>Today’s picks and your dishes stay on your phone. Meals you log offline sync when you’re back online.</p></article>
                <article class="feature"><span class="ic amber">{!! $icons['bell'] !!}</span><h3>One morning reminder</h3><p>A single notification at the time you choose. No spam, and you can turn it off any time.</p></article>
                <article class="feature"><span class="ic">{!! $icons['camera'] !!}</span><h3>Your own dishes, your photos</h3><p>Add family recipes with a photo, or copy our AI prompt to make a picture that matches the app.</p></article>
            </div>
        </div>
    </section>

    {{-- Library --}}
    <section class="section" id="dishes" aria-labelledby="dishes-title">
        <div class="wrap library">
            <div class="plates" aria-hidden="true">
                @foreach (['biryani', 'karahi', 'lentils', 'bhindi', 'qeema', 'paratha', 'karhi', 'aloogobi', 'fish'] as $k)
                    <div class="plate" style="background:{{ $art[$k]['bg'] }}">{!! $img($k, 160) !!}</div>
                @endforeach
            </div>
            <div>
                <span class="eyebrow">The dish library</span>
                <h2 id="dishes-title" style="font-size:clamp(28px,4vw,40px);margin-block:12px 16px;">Start from dishes you already know</h2>
                @if ($library['total'])
                    <p class="big-num">{{ $library['total'] }}</p>
                    <p class="lead" style="margin-top:6px;">home-style dishes across {{ count($library['categories']) }} categories, from everyday daal to Sunday biryani. Add your own any time.</p>
                    <div class="cats">
                        @foreach (array_slice($library['categories'], 0, 4) as $cat)
                            <div class="cat">
                                <b>{{ $cat['name'] }} <span>· {{ $cat['count'] }}</span></b>
                                <div class="chips">@foreach ($cat['dishes'] as $name)<span class="chip">{{ $name }}</span>@endforeach</div>
                            </div>
                        @endforeach
                    </div>
                @else
                    <p class="lead">Everyday daal to Sunday biryani, plus your own family recipes.</p>
                @endif
            </div>
        </div>
    </section>

    {{-- FAQ --}}
    <section class="section band-white" id="faq" aria-labelledby="faq-title">
        <div class="wrap">
            <div class="section-head">
                <span class="eyebrow">Questions</span>
                <h2 id="faq-title">Good to know</h2>
            </div>
            <div class="faq">
                <details><summary>Is Handi free?</summary><p>Yes. There are no ads and no paid plan.</p></details>
                <details><summary>Does it give recipes?</summary><p>No. Handi suggests dishes your family already knows how to cook. It solves the “what should we make today?” part, not the how.</p></details>
                <details><summary>Why does it need my location?</summary><p>Only to check today’s weather. It’s rounded to about 1 km and never shared. You can choose your city instead.</p></details>
                <details><summary>Does it work without internet?</summary><p>Yes. Today’s picks and your dishes are saved on your phone, and meals you log offline are sent when you’re back online.</p></details>
                <details><summary>Can I add my own dishes?</summary><p>Yes, with a category, weather, effort, cost and an optional photo. They join your daily suggestions from the next day.</p></details>
                <details><summary>How do I delete my account?</summary><p>In the app: Settings → Delete account. You can also <a href="{{ route('site.delete-account') }}">ask us to delete it</a>.</p></details>
            </div>
        </div>
    </section>

    {{-- Download --}}
    <section class="section" id="download" aria-labelledby="download-title">
        <div class="wrap">
            <div class="cta-band">
                <div>
                    <h2 id="download-title">Stop asking “aaj kya pakaen?”</h2>
                    <p>Set up your kitchen in two minutes and get your first three picks tomorrow morning.</p>
                </div>
                <div class="actions">
                    @if ($android)
                        <a class="btn btn-amber" href="{{ $android }}" rel="noopener">Get it on Google Play {!! $icons['arrow'] !!}</a>
                    @else
                        <span class="soon">Android app · coming soon to Google Play</span>
                    @endif
                </div>
            </div>
        </div>
    </section>
@endsection
