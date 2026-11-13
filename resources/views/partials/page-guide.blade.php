{{--
    Collapsible "How this page works" box (same idea as SunXFinder's page guide).
    Expects: $key (unique per page, remembers hidden state), $title, $intro, $steps (array of strings, may contain <b>).
    Optional: $note (short closing tip).
--}}
<details class="page-guide no-print" data-guide="{{ $key }}" open>
    <summary>
        <span class="page-guide-icon" aria-hidden="true">i</span>
        <span class="page-guide-title">{{ __($title) }}</span>
        <span class="page-guide-toggle" aria-hidden="true"><span class="when-open">{{ __('Hide') }}</span><span class="when-closed">{{ __('Show') }}</span></span>
    </summary>

    <div class="page-guide-body">
        @if (!empty($intro))
            <p>{{ __($intro) }}</p>
        @endif

        @if (!empty($steps))
            <ol>
                @foreach ($steps as $step)
                    <li>{!! __($step) !!}</li>
                @endforeach
            </ol>
        @endif

        @if (!empty($note))
            <p class="page-guide-note">{{ __($note) }}</p>
        @endif
    </div>
</details>
