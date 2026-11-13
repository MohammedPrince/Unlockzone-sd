{{-- The items inside the admin's notification bell. Expects $items, $notifier (AdminNotificationService), $admin. --}}
@forelse ($items as $item)
    @php
        [$title, $color, $icon] = \App\Services\AdminNotificationService::TYPES[$item->action] ?? [$item->action, 'muted', 'clock'];
        $link = $notifier->link($item);
        $who = $item->user ? $item->user->username : (($item->properties['by'] ?? null) === 'provider' ? __('Provider') : __('System'));
    @endphp
    <a @class(['notif-item', 'unread' => $notifier->isUnread($admin, $item)]) href="{{ $link ?? route('admin.activity') }}">
        <span class="notif-icon n-{{ $color }}">@include('partials.icon', ['name' => $icon])</span>
        <span class="notif-text">
            <b>{{ __($title) }}</b>
            <span dir="auto">{{ $item->description }}</span>
            <small>{{ $who }} · <time datetime="{{ $item->created_at?->toIso8601String() }}">{{ $item->created_at?->locale(app()->getLocale())->diffForHumans() }}</time></small>
        </span>
    </a>
@empty
    <div class="notif-empty">{{ __('Nothing new yet. Orders, sign-ups and provider problems will appear here.') }}</div>
@endforelse
