{{--
    Body of a legal page. Expects: $intro (string), $sections ([[id, title, [items]]...], items may contain <b>),
    $updated (string date), $ar (bool). Contact details come from Admin > Settings.
--}}
@php
    $email = trim((string) setting('contact_email'));
    $phone = trim((string) setting('contact_phone'));
    $whatsapp = setting('whatsapp_enabled') === '1' ? preg_replace('/[^0-9]+/', '', (string) setting('whatsapp_number')) : '';
@endphp
<article class="legal">
    <div class="card">
        <div class="card-body">
            <p class="legal-updated">{{ $ar ? 'آخر تحديث' : 'Last updated' }}: {{ $updated }}</p>
            <p class="legal-intro">{{ $intro }}</p>
            <nav class="guide-toc" aria-label="{{ $ar ? 'المحتويات' : 'Contents' }}">
                @foreach ($sections as $i => [$id, $title])
                    <a href="#{{ $id }}"><span>{{ $i + 1 }}</span>{{ $title }}</a>
                @endforeach
            </nav>
        </div>
    </div>

    @foreach ($sections as $i => [$id, $title, $items])
        <section class="card guide-section" id="{{ $id }}">
            <div class="card-head"><h2><span class="guide-num">{{ $i + 1 }}</span> {{ $title }}</h2></div>
            <div class="card-body">
                <ul class="legal-list">
                    @foreach ($items as $item)
                        <li>{!! $item !!}</li>
                    @endforeach
                </ul>
            </div>
        </section>
    @endforeach

    @if ($extra ?? null)
        <section class="card guide-section" id="additional">
            <div class="card-head"><h2>{{ $ar ? 'شروط إضافية' : 'Additional terms' }}</h2></div>
            <div class="card-body"><div class="rich" dir="auto">{{ rich_text($extra) }}</div></div>
        </section>
    @endif

    <div class="card legal-contact" id="contact">
        <div class="card-body">
            <h2>{{ $ar ? 'تواصل معنا' : 'Contact us' }}</h2>
            <p class="muted">{{ $ar ? 'لأي سؤال عن هذه الصفحة أو عن بياناتك:' : 'For any question about this page or your data:' }}</p>
            <div class="legal-contact-links">
                @if ($email !== '') <a href="mailto:{{ $email }}" dir="ltr">@include('partials.icon', ['name' => 'mail']) {{ $email }}</a> @endif
                @if ($phone !== '') <a href="tel:{{ preg_replace('/[^0-9+]+/', '', $phone) }}" dir="ltr">@include('partials.icon', ['name' => 'phone']) {{ $phone }}</a> @endif
                @if ($whatsapp !== '') <a class="btn btn-contact wa" href="https://wa.me/{{ $whatsapp }}" target="_blank" rel="noopener noreferrer">@include('partials.icon', ['name' => 'whatsapp']) WhatsApp</a> @endif
            </div>
            <p class="muted legal-other">
                @if (request()->routeIs('legal.terms'))
                    <a href="{{ route('legal.privacy') }}">{{ $ar ? 'اقرأ أيضاً: سياسة الخصوصية' : 'Also read: Privacy Policy' }}</a>
                @else
                    <a href="{{ route('legal.terms') }}">{{ $ar ? 'اقرأ أيضاً: شروط الخدمة' : 'Also read: Terms of Service' }}</a>
                @endif
            </p>
        </div>
    </div>
</article>
