<x-layouts.app title="Terms of use" description="The terms for using How's the Water and the data it shows.">
    <article class="max-w-2xl">
        <p class="text-sm text-muted">Last updated 16 October 2026</p>
        <h1 class="font-display mt-1 text-3xl font-semibold tracking-tight">Terms of use</h1>

        <div class="mt-6 grid gap-8 leading-relaxed text-ink-2">
            <section class="grid gap-3">
                <p>How's the Water is a free site run by Chris Chrisostomou. By using it, its RSS feeds or its API, you agree to these terms.</p>
            </section>

            <section class="grid gap-3">
                <h2 class="font-display text-lg font-semibold text-ink">What the site is</h2>
                <p>The site reports what public monitors say about storm overflows, river levels, rainfall, flood warnings and bathing water samples. It is not a safety service and is not advice. Read <a
                        href="{{ route('home') }}#limits" class="text-accent hover:underline">what this site does not do</a> before relying on it.</p>
                <p>Make your own judgement at the water, follow local signs and official advice, and in an emergency call 999.</p>
            </section>

            <section class="grid gap-3">
                <h2 class="font-display text-lg font-semibold text-ink">The data</h2>
                <p>Data comes from the Environment Agency, Natural Resources Wales, SEPA (the Scottish Environment Protection Agency), the water companies and Ordnance Survey. It is near real-time and unvalidated, and it can be late, missing or wrong. Neither the data nor the site comes with any
                    guarantee of accuracy or availability, and the site may change or stop at any time.</p>
                <p>The Environment Agency, Natural Resources Wales, SEPA and the water companies do not endorse this site, and nothing here should suggest they do.</p>
            </section>

            <section class="grid gap-3">
                <h2 class="font-display text-lg font-semibold text-ink">Reusing the data</h2>
                <p>You can reuse the underlying data under its original licence, crediting the source as that licence asks:</p>
                <ul class="grid gap-2">
                    <li class="flex gap-3"><span class="text-muted">-</span><span>Environment Agency flood, river level, rainfall and bathing water data: <a
                                href="https://www.nationalarchives.gov.uk/doc/open-government-licence/version/3/" rel="noopener" class="text-accent hover:underline">Open Government Licence v3.0</a>.</span></li>
                    <li class="flex gap-3"><span class="text-muted">-</span><span>Natural Resources Wales river level, rainfall, flood warning and bathing water data: <a
                                href="https://www.nationalarchives.gov.uk/doc/open-government-licence/version/3/" rel="noopener" class="text-accent hover:underline">Open Government Licence v3.0</a>, crediting Natural Resources Wales.</span></li>
                    <li class="flex gap-3"><span class="text-muted">-</span><span>SEPA river level, rainfall and bathing water data: <a
                                href="https://www.nationalarchives.gov.uk/doc/open-government-licence/version/3/" rel="noopener" class="text-accent hover:underline">Open Government Licence v3.0</a>, crediting SEPA as the owner of the data.</span></li>
                    <li class="flex gap-3"><span class="text-muted">-</span><span>Storm overflow data published by the water companies through Stream: <a href="https://creativecommons.org/licenses/by/4.0/" rel="noopener"
                                class="text-accent hover:underline">CC BY 4.0</a>.</span></li>
                    <li class="flex gap-3"><span class="text-muted">-</span><span>Storm overflow data from Scottish Water: published by Scottish Water for reuse, crediting Scottish Water as the source.</span></li>
                    <li class="flex gap-3"><span class="text-muted">-</span><span>River centrelines from OS Open Rivers: Open Government Licence v3.0, contains OS data © Crown copyright and database right.</span></li>
                    <li class="flex gap-3"><span class="text-muted">-</span><span>Map tiles: © OpenStreetMap contributors, <a href="https://www.openstreetmap.org/copyright" rel="noopener"
                                class="text-accent hover:underline">ODbL</a>.</span></li>
                </ul>
                <p>The Environment Agency asks that data is not used for safety-critical purposes, and that applies to anything you build with it too.</p>
            </section>

            <section class="grid gap-3">
                <h2 class="font-display text-lg font-semibold text-ink">Fair use</h2>
                <p>Search, the RSS feeds and the API are rate limited per IP address. Please do not scrape the site heavily or try to get around the limits. Access can be blocked for clients that harm the service for
                    others. If you want a copy of the data, <a href="https://mou.me/contact" rel="noopener" class="text-accent hover:underline">get in touch</a> and we can sort something out.
                </p>
            </section>

            <section class="grid gap-3">
                <h2 class="font-display text-lg font-semibold text-ink">Liability</h2>
                <p>As far as the law allows, no liability is accepted for any loss or harm arising from use of the site or its data. Nothing in these terms limits liability that cannot be limited by law.</p>
            </section>

            <section class="grid gap-3">
                <h2 class="font-display text-lg font-semibold text-ink">General</h2>
                <p>These terms are governed by the law of England and Wales. They may be updated, and the date at the top will change when they are. How personal data is handled is covered by the <a
                        href="{{ route('privacy') }}" class="text-accent hover:underline">privacy notice</a>.</p>
            </section>
        </div>
    </article>
</x-layouts.app>
