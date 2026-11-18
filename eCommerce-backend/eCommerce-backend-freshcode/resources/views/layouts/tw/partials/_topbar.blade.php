@php
    use App\Services\NotificationService;
    use App\Enums\AdminPermissionEnum;
    use App\Enums\SellerPermissionEnum;
    use App\Enums\DefaultSystemRolesEnum;
    use App\Enums\Subscription\SellerSubscriptionStatusEnum;
    use App\Models\Setting;
    use App\Models\SellerSubscription;
    use App\Http\Middleware\ComingSoon;
    use Illuminate\Support\Facades\Auth;

    $tbUser = Auth::user();
    $tbPanel = request()->segment(1);
    $isImpersonating = session('impersonating_as_seller', false);

    $iconBtn = 'w-9 h-9 rounded-lg hover:bg-neutral-100 dark:hover:bg-white/5 flex items-center justify-center transition text-neutral-600 dark:text-neutral-300';
    $menuBox = 'absolute right-0 mt-1.5 rounded-xl border border-neutral-200 dark:border-white/10 bg-white dark:bg-ink-800 shadow-lg p-1 z-40';
    $menuItem = 'flex items-center gap-2.5 px-2.5 py-2 rounded-lg text-[13px] hover:bg-neutral-100 dark:hover:bg-white/5 transition';

    $canViewNotifications = false;
    if ($tbPanel === 'admin') {
        $canViewNotifications = ($tbUser && $tbUser->hasRole(DefaultSystemRolesEnum::SUPER_ADMIN()));
        if (!$canViewNotifications) {
            try { $canViewNotifications = $tbUser?->hasPermissionTo(AdminPermissionEnum::NOTIFICATION_VIEW()) ?? false; } catch (\Throwable $e) {}
        }
    } elseif ($tbPanel === 'seller') {
        $canViewNotifications = ($isImpersonating || ($tbUser && $tbUser->hasRole(DefaultSystemRolesEnum::SELLER())));
        if (!$canViewNotifications) {
            try { $canViewNotifications = $tbUser?->hasPermissionTo(SellerPermissionEnum::NOTIFICATION_VIEW()) ?? false; } catch (\Throwable $e) {}
        }
    }

    $notifications = collect();
    $unreadCount = 0;
    if ($canViewNotifications) {
        try {
            $sentTo = $tbPanel === 'admin' ? 'admin' : 'seller';
            $nid = $tbPanel === 'admin' ? $tbUser?->id : $tbUser?->seller()?->user_id;
            $headerData = app(NotificationService::class)->getHeaderNotifications(userId: $nid, sentTo: $sentTo);
            $notifications = $headerData['notifications'] ?? collect();
            $unreadCount = $headerData['unread_count'] ?? 0;
        } catch (\Throwable $e) {}
    }

@endphp

<header class="h-14 shrink-0 border-b border-neutral-200 dark:border-white/[.06] bg-white/80 dark:bg-ink-900/80 backdrop-blur-md flex items-center gap-2 px-3 lg:px-6 sticky top-0 z-30">
    <button data-hui-sidebar-open class="lg:hidden {{ $iconBtn }} shrink-0">
        <i class="ti ti-menu-2 text-[18px]"></i>
    </button>

    <div class="min-w-0 me-auto">
        <div class="text-[15px] font-semibold tracking-tight truncate">{{ $page_title ?? ($page_pretitle ?? 'Dashboard') }}</div>
        @isset($page_pretitle)
            <div class="text-[12px] text-neutral-400 -mt-0.5 truncate hidden sm:block">{{ $page_pretitle }}</div>
        @endisset
    </div>

    <div class="flex items-center gap-1 shrink-0">
        {{-- Market selector (admin) --}}
        @if ($tbPanel === 'admin' && !empty($adminMarkets) && count($adminMarkets) && !empty($adminCurrentMarket))
            <div class="relative">
                <button type="button" data-hui-menu-toggle="hui-market" aria-expanded="false"
                        class="h-9 px-2.5 rounded-lg hover:bg-neutral-100 dark:hover:bg-white/5 transition flex items-center gap-1.5 text-neutral-600 dark:text-neutral-300" data-hui-tip="{{ __('labels.filter_by_market') }}">
                    <i class="ti ti-world text-[17px]"></i>
                    <span class="hidden md:inline text-[13px] font-medium">{{ $adminCurrentMarket->code ?? $adminCurrentMarket->name }}</span>
                    <i class="ti ti-chevron-down text-[13px] hidden md:inline text-neutral-400"></i>
                </button>
                <div id="hui-market" data-hui-menu class="{{ $menuBox }} w-56">
                    <div class="px-2.5 py-1.5 text-[11px] font-medium uppercase tracking-wider text-neutral-400">{{ __('labels.filter_by_market') }}</div>
                    @foreach ($adminMarkets as $m)
                        <form action="{{ route('admin.markets.switch') }}" method="POST">
                            @csrf
                            <input type="hidden" name="market_id" value="{{ $m->id }}">
                            <button type="submit" class="{{ $menuItem }} w-full {{ $adminCurrentMarket->id === $m->id ? 'text-brand-700 dark:text-brand font-medium' : '' }}">
                                <i class="ti ti-check text-[15px] {{ $adminCurrentMarket->id === $m->id ? 'text-brand' : 'invisible' }}"></i>
                                <span class="truncate">{{ $m->name }}</span>
                                <span class="ms-auto text-[11px] text-neutral-400">{{ $m->code }}</span>
                            </button>
                        </form>
                    @endforeach
                </div>
            </div>
        @endif

        {{-- Store selector (seller) --}}
        @if ($tbPanel === 'seller' && !empty($sellerStores) && count($sellerStores) && !empty($sellerCurrentStore))
            <div class="relative">
                <button type="button" @if(count($sellerStores) > 1) data-hui-menu-toggle="hui-store" @endif aria-expanded="false"
                        class="h-9 px-2.5 rounded-lg hover:bg-neutral-100 dark:hover:bg-white/5 transition flex items-center gap-1.5 text-neutral-600 dark:text-neutral-300" data-hui-tip="{{ __('labels.store') }}">
                    <i class="ti ti-building-store text-[17px]"></i>
                    <span class="hidden md:inline text-[13px] font-medium truncate max-w-[140px]">{{ $sellerCurrentStore->name }}</span>
                    @if (count($sellerStores) > 1)<i class="ti ti-chevron-down text-[13px] hidden md:inline text-neutral-400"></i>@endif
                </button>
                @if (count($sellerStores) > 1)
                    <div id="hui-store" data-hui-menu class="{{ $menuBox }} w-56">
                        <div class="px-2.5 py-1.5 text-[11px] font-medium uppercase tracking-wider text-neutral-400">{{ __('labels.store') }}</div>
                        @foreach ($sellerStores as $s)
                            <form action="{{ route('seller.stores.switch') }}" method="POST">
                                @csrf
                                <input type="hidden" name="store_id" value="{{ $s->id }}">
                                <button type="submit" class="{{ $menuItem }} w-full {{ $sellerCurrentStore->id === $s->id ? 'text-brand-700 dark:text-brand font-medium' : '' }}">
                                    <i class="ti ti-check text-[15px] {{ $sellerCurrentStore->id === $s->id ? 'text-brand' : 'invisible' }}"></i>
                                    <span class="truncate">{{ $s->name }}</span>
                                </button>
                            </form>
                        @endforeach
                    </div>
                @endif
            </div>
        @endif

        {{-- Seller subscription badge --}}
        @if ($tbPanel === 'seller' && $tbUser && Setting::isSubscriptionEnabled() && !ComingSoon::isComingSoon('subscription'))
            @php
                $sellerEntity = $tbUser?->seller();
                $activeSub = $sellerEntity ? SellerSubscription::currentForSeller((int) $sellerEntity->id) : null;
                $canViewSubscriptions = $tbUser->hasRole(DefaultSystemRolesEnum::SELLER());
                if (!$canViewSubscriptions) {
                    try { $canViewSubscriptions = $tbUser->hasPermissionTo(SellerPermissionEnum::SUBSCRIPTION_VIEW()); } catch (\Throwable $e) { $canViewSubscriptions = false; }
                }
            @endphp
            @if ($canViewSubscriptions && $activeSub)
                <a href="{{ route('seller.subscription-plans.current') }}" title="{{ __('labels.current_subscription') }}"
                   class="hidden sm:flex h-9 px-2.5 rounded-lg border border-neutral-200 dark:border-white/10 hover:bg-neutral-50 dark:hover:bg-white/5 transition items-center gap-2">
                    <span class="badge {{ $activeSub->badgeClass() }} text-uppercase">{{ (string) $activeSub->status }}</span>
                    <span class="hidden md:inline text-[12.5px] truncate max-w-[140px]">{{ $activeSub->plan?->name ?? __('labels.plan') }}</span>
                </a>
            @elseif ($canViewSubscriptions)
                <a href="{{ route('seller.subscription-plans.index') }}" title="{{ __('labels.current_subscription') }}"
                   class="hidden sm:flex h-9 px-2.5 rounded-lg border border-neutral-200 dark:border-white/10 hover:bg-neutral-50 dark:hover:bg-white/5 transition items-center">
                    <span class="badge bg-blue-lt text-uppercase">{{ __('labels.no_active_plan') }}</span>
                </a>
            @endif
        @endif

        {{-- Help / Knowledge Base --}}
        <a href="{{ ($tbPanel === 'seller' ? url('/seller-guide') : url('/admin-guide')) }}?lang={{ app()->getLocale() }}"
           target="_blank" rel="noopener" class="{{ $iconBtn }} hidden sm:flex" data-hui-tip="Knowledge base" aria-label="Knowledge base">
            <i class="ti ti-help text-[17px]"></i>
        </a>

        {{-- Theme toggle --}}
        <button type="button" data-hui-theme-toggle class="{{ $iconBtn }}" data-hui-tip="Toggle theme" aria-label="Toggle theme">
            <i class="ti ti-moon text-[17px] dark:hidden"></i>
            <i class="ti ti-sun text-[17px] hidden dark:inline"></i>
        </button>

        {{-- Language --}}
        <div class="relative">
            <button type="button" data-hui-menu-toggle="hui-lang" aria-expanded="false" class="{{ $iconBtn }}" data-hui-tip="Language" aria-label="Select language">
                <i class="ti ti-language text-[17px]"></i>
            </button>
            <div id="hui-lang" data-hui-menu class="{{ $menuBox }} w-44">
                @foreach (($availableLanguages ?? collect()) as $language)
                    <a href="{{ route('set.language', $language->code) }}" dir="{{ $language->direction }}" class="{{ $menuItem }} {{ app()->getLocale() === $language->code ? 'text-brand-700 dark:text-brand font-medium' : '' }}">
                        <i class="ti ti-check text-[15px] {{ app()->getLocale() === $language->code ? 'text-brand' : 'invisible' }}"></i>{{ $language->native_name }}
                    </a>
                @endforeach
            </div>
        </div>

        {{-- Notifications --}}
        @if ($canViewNotifications)
            <div class="relative">
                <button type="button" data-hui-menu-toggle="hui-notif" aria-expanded="false" class="{{ $iconBtn }} relative" aria-label="Notifications">
                    <i class="ti ti-bell text-[17px]"></i>
                    @if ($unreadCount > 0)
                        <span class="absolute -top-0.5 -right-0.5 min-w-[16px] h-4 px-1 bg-red-500 text-white text-[10px] font-medium rounded-full flex items-center justify-center ring-2 ring-white dark:ring-ink-900">{{ $unreadCount > 9 ? '9+' : $unreadCount }}</span>
                    @endif
                </button>
                <div id="hui-notif" data-hui-menu class="{{ $menuBox }} w-80 max-w-[calc(100vw-1.5rem)]">
                    <div class="px-3 py-2 text-[13px] font-semibold border-b border-neutral-100 dark:border-white/5">{{ __('labels.notifications') }}</div>
                    <div class="max-h-80 overflow-auto py-1">
                        @forelse ($notifications as $notification)
                            <a href="{{ $notification->getRedirectUrlForPanel($tbPanel) }}"
                               class="header-notification-link flex gap-2.5 px-3 py-2.5 rounded-lg hover:bg-neutral-50 dark:hover:bg-white/5 transition"
                               data-mark-read-url="{{ route($tbPanel . '.notifications.mark-read', $notification->id) }}"
                               data-redirect-url="{{ $notification->getRedirectUrlForPanel($tbPanel) }}"
                               data-is-read="{{ $notification->is_read ? '1' : '0' }}">
                                <span class="mt-1.5 w-2 h-2 rounded-full shrink-0 {{ $notification->is_read ? 'bg-neutral-300 dark:bg-white/20' : 'bg-red-500' }}"></span>
                                <div class="min-w-0">
                                    <div class="text-[13px] leading-snug line-clamp-2">{{ $notification->message }}</div>
                                    <div class="text-[11px] text-neutral-400 mt-0.5">{{ $notification->created_at->diffForHumans() }}</div>
                                </div>
                            </a>
                        @empty
                            <div class="px-3 py-8 text-center text-neutral-400">
                                <i class="ti ti-bell-off text-[22px]"></i>
                                <div class="text-[13px] mt-1.5">{{ __('labels.no_notifications') }}</div>
                            </div>
                        @endforelse
                    </div>
                    <div class="p-1 border-t border-neutral-100 dark:border-white/5">
                        <a href="{{ route($tbPanel . '.notifications.index') }}" class="block text-center text-[13px] font-medium text-brand-700 dark:text-brand py-2 rounded-lg hover:bg-neutral-50 dark:hover:bg-white/5 transition">{{ __('labels.view_all_notifications') }}</a>
                    </div>
                </div>
            </div>
            <script>
                document.addEventListener('DOMContentLoaded', function () {
                    document.querySelectorAll('.header-notification-link').forEach(function (link) {
                        link.addEventListener('click', function (event) {
                            event.preventDefault();
                            var redirectUrl = this.dataset.redirectUrl || this.getAttribute('href');
                            var markReadUrl = this.dataset.markReadUrl;
                            var isRead = this.dataset.isRead === '1';
                            var csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
                            if (!markReadUrl || isRead) { window.location.href = redirectUrl; return; }
                            fetch(markReadUrl, { method: 'POST', headers: { 'X-CSRF-TOKEN': csrfToken, 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' } })
                                .finally(function () { window.location.href = redirectUrl; });
                        });
                    });
                });
            </script>
        @endif

        <div class="w-px h-5 bg-neutral-200 dark:bg-white/10 mx-1 hidden sm:block"></div>

        {{-- User menu --}}
        <div class="relative">
            <button type="button" data-hui-menu-toggle="hui-user" aria-expanded="false" class="flex items-center gap-2 h-9 pl-1 pr-2 rounded-lg hover:bg-neutral-100 dark:hover:bg-white/5 transition">
                <span class="w-7 h-7 rounded-full bg-neutral-200 dark:bg-white/10 flex items-center justify-center text-[11px] font-semibold overflow-hidden bg-cover bg-center"
                      @if($tbUser->profile_image ?? false) style="background-image:url({{ $tbUser->profile_image }})" @endif>
                    @if (!($tbUser->profile_image ?? false)){{ strtoupper(substr($tbUser->name ?? 'U', 0, 2)) }}@endif
                </span>
                <div class="hidden xl:block leading-tight">
                    <div class="text-[12.5px] font-medium truncate max-w-[120px]">{{ $tbUser->username ?? $tbUser->name ?? '' }}</div>
                    @if (($tbUser->role ?? '') === 'admin')<div class="text-[11px] text-neutral-400">{{ __('labels.administrator') }}</div>@endif
                </div>
                <i class="ti ti-chevron-down text-[14px] text-neutral-400 hidden sm:inline"></i>
            </button>
            <div id="hui-user" data-hui-menu class="{{ $menuBox }} w-52">
                <div class="px-3 py-2 border-b border-neutral-100 dark:border-white/5">
                    <div class="text-[13px] font-medium truncate">{{ $tbUser->name ?? '' }}</div>
                    <div class="text-[12px] text-neutral-400 truncate">{{ $tbUser->email ?? '' }}</div>
                </div>
                <a href="{{ route($tbPanel . '.profile.index') }}" class="{{ $menuItem }} mt-1"><i class="ti ti-user text-[15px] text-neutral-500"></i>{{ __('labels.profile') }}</a>
                <div class="h-px bg-neutral-100 dark:bg-white/5 my-1"></div>
                <a href="{{ route($tbPanel . '.logout') }}" class="{{ $menuItem }} text-rose-600 hover:bg-rose-50 dark:hover:bg-rose-500/10"><i class="ti ti-logout text-[15px]"></i>{{ __('labels.logout') }}</a>
            </div>
        </div>
    </div>
</header>
