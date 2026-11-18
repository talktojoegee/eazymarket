@php
    use App\Services\NotificationService;
    use App\Enums\AdminPermissionEnum;
    use App\Enums\SellerPermissionEnum;
    use App\Enums\DefaultSystemRolesEnum;
    use Illuminate\Support\Facades\Auth;
    use App\Models\Setting;
    use App\Models\SellerUser;
    use App\Models\SellerSubscription;
    use App\Enums\Subscription\SellerSubscriptionStatusEnum;
    use App\Http\Middleware\ComingSoon;
@endphp
<div class="page-header d-print-none mt-0" data-tour-target="page-header">
    <div class="container-xl navbar align-items-center py-2 px-2 gap-2 ps-lg-5">
        <button type="button" class="btn btn-icon d-lg-none me-2 sidebar-mobile-open"
                data-mobile-sidebar-open aria-controls="panel-sidebar" aria-expanded="false"
                aria-label="Open sidebar">
            <i class="ti ti-menu-2 fs-2" aria-hidden="true"></i>
        </button>
        <div class="nav-item d-none d-sm-flex global-search-trigger-wrap">
            <button type="button" class="btn btn-outline-primary btn-sm w-100 d-flex align-items-center justify-content-between px-3 py-2 shadow-none global-search-trigger"
                    data-global-search-open title="Global Search (Ctrl + K)" aria-label="Open global search">
                <span class="d-flex align-items-center gap-2 text-truncate">
                    <i class="ti ti-search fs-3"></i>
                    <span class="small fw-normal">{{ __('labels.search') }} menu, products, orders...</span>
                </span>
                <kbd>Ctrl K</kbd>
            </button>
        </div>
        <div class="nav-item d-sm-none global-search-mobile-trigger">
            <button type="button" class="btn btn-outline-primary btn-icon" data-global-search-open
                    title="Global Search" aria-label="Open global search">
                <i class="ti ti-search fs-2"></i>
            </button>
        </div>
        <div class="ms-auto global-header-actions">
            <!-- Page title actions -->
            <div class="col-auto ms-auto d-print-none">
                <div class="btn-list">
                    @php
                        $currentPanel = request()->segment(1);
                        $userAuth = Auth::user();
                        $isImpersonating = false;
                    @endphp

                    <!-- Theme switcher -->
                    <div class="nav-item dropdown d-flex me-3">
                        <a href="#" onclick="toggleTheme()" class="nav-link px-0" tabindex="-1"
                           aria-label="Switch theme">
                            <i id="theme-icon" class="ti ti-moon fs-2"></i>
                        </a>
                    </div>

                    <!-- Global market selector (admin panel is scoped to one market at a time) -->
                    @if($currentPanel === 'admin' && !empty($adminMarkets) && count($adminMarkets) && !empty($adminCurrentMarket))
                        <div class="nav-item dropdown d-flex me-3">
                            <a href="#" class="nav-link px-0 d-flex align-items-center gap-1"
                               data-bs-toggle="dropdown" tabindex="-1" aria-label="{{ __('labels.filter_by_market') }}"
                               title="{{ __('labels.filter_by_market') }}">
                                <i class="ti ti-world fs-2"></i>
                                <span class="d-none d-md-inline fw-medium">{{ $adminCurrentMarket->code ?? $adminCurrentMarket->name }}</span>
                                <i class="ti ti-chevron-down fs-4 d-none d-md-inline"></i>
                            </a>
                            <div class="dropdown-menu dropdown-menu-end dropdown-menu-arrow">
                                <div class="dropdown-header">{{ __('labels.filter_by_market') }}</div>
                                @foreach($adminMarkets as $m)
                                    <form action="{{ route('admin.markets.switch') }}" method="POST" class="m-0">
                                        @csrf
                                        <input type="hidden" name="market_id" value="{{ $m->id }}">
                                        <button type="submit"
                                                class="dropdown-item d-flex align-items-center {{ $adminCurrentMarket->id === $m->id ? 'active' : '' }}">
                                            <i class="ti ti-check me-2 {{ $adminCurrentMarket->id === $m->id ? '' : 'invisible' }}"></i>
                                            <span>{{ $m->name }}</span>
                                            <span class="text-muted ms-auto ps-3">{{ $m->code }}</span>
                                        </button>
                                    </form>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    <!-- Global store selector (seller panel is scoped to one of the seller's stores) -->
                    @if($currentPanel === 'seller' && !empty($sellerStores) && count($sellerStores) && !empty($sellerCurrentStore))
                        <div class="nav-item dropdown d-flex me-3">
                            <a href="#" class="nav-link px-0 d-flex align-items-center gap-1"
                               data-bs-toggle="dropdown" tabindex="-1" aria-label="{{ __('labels.store') }}"
                               title="{{ __('labels.store') }}">
                                <i class="ti ti-building-store fs-2"></i>
                                <span class="d-none d-md-inline fw-medium text-truncate" style="max-width: 160px;">{{ $sellerCurrentStore->name }}</span>
                                @if(count($sellerStores) > 1)
                                    <i class="ti ti-chevron-down fs-4 d-none d-md-inline"></i>
                                @endif
                            </a>
                            @if(count($sellerStores) > 1)
                                <div class="dropdown-menu dropdown-menu-end dropdown-menu-arrow">
                                    <div class="dropdown-header">{{ __('labels.store') }}</div>
                                    @foreach($sellerStores as $s)
                                        <form action="{{ route('seller.stores.switch') }}" method="POST" class="m-0">
                                            @csrf
                                            <input type="hidden" name="store_id" value="{{ $s->id }}">
                                            <button type="submit"
                                                    class="dropdown-item d-flex align-items-center {{ $sellerCurrentStore->id === $s->id ? 'active' : '' }}">
                                                <i class="ti ti-check me-2 {{ $sellerCurrentStore->id === $s->id ? '' : 'invisible' }}"></i>
                                                <span class="text-truncate">{{ $s->name }}</span>
                                            </button>
                                        </form>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    @endif

                    <!-- Knowledge Base / Help -->
                    <div class="nav-item d-flex me-3">
                        <button type="button" class="nav-link px-0 border-0 bg-transparent"
                                data-bs-toggle="offcanvas" data-bs-target="#offcanvasTourDrawer"
                                aria-controls="offcanvasTourDrawer" title="User Guide" aria-label="Open user guide">
                            <i class="ti ti-help fs-2"></i>
                        </button>
                    </div>
                    <!-- Seller active subscription badge (via model helper) -->
                    @if($currentPanel === 'seller' && $userAuth && Setting::isSubscriptionEnabled() && !ComingSoon::isComingSoon('subscription'))
                        @php
                            $sellerEntity = $userAuth?->seller();
                            $activeSub = $sellerEntity ? SellerSubscription::currentForSeller((int)$sellerEntity->id) : null;
                            $canViewSubscriptions = $userAuth->hasRole(DefaultSystemRolesEnum::SELLER());

                            if (!$canViewSubscriptions) {
                                try {
                                    $canViewSubscriptions = $userAuth->hasPermissionTo(SellerPermissionEnum::SUBSCRIPTION_VIEW());
                                } catch (\Throwable $e) {
                                    $canViewSubscriptions = false;
                                }
                            }
                        @endphp
                        @if($canViewSubscriptions && $activeSub)
                            <a href="{{ route('seller.subscription-plans.current') }}"
                               class="btn btn-pill me-3 align-items-center btn-sm"
                               title="{{ __('labels.current_subscription') }}">
                                <span
                                    class="badge {{ $activeSub->badgeClass() }} text-uppercase me-2">{{ (string)$activeSub->status }}</span>
                                <span class="text-truncate d-none d-md-inline-flex"
                                      style="max-width: 180px;">{{ $activeSub->plan?->name ?? __('labels.plan') }}</span>
                            </a>
                        @elseif($canViewSubscriptions)
                            <a href="{{ route('seller.subscription-plans.index') }}"
                               class="btn btn-pill me-3 align-items-center btn-sm"
                               title="{{ __('labels.current_subscription') }}">
                                <span class="badge bg-primary-lt text-uppercase">{{__('labels.no_active_plan')}}</span>
                            </a>
                        @endif
                    @endif

                    <!-- Language dropdown -->
                    <div class="nav-item dropdown d-flex me-3">
                        <a href="#" class="nav-link px-0" data-bs-toggle="dropdown" tabindex="-1"
                           aria-label="Select language">
                            <i class="ti ti-language fs-2"></i>
                        </a>
                        <div class="dropdown-menu dropdown-menu-end dropdown-menu-arrow">
                            @foreach(($availableLanguages ?? collect()) as $language)
                                <a href="{{ route('set.language', $language->code) }}"
                                   class="dropdown-item {{ app()->getLocale() === $language->code ? 'active' : '' }}"
                                   dir="{{ $language->direction }}">{{ $language->native_name }}</a>
                            @endforeach
                        </div>
                    </div>

                    <!-- Notification dropdown -->
                    @php
                        $currentPanel = request()->segment(1);
                        $userAuth = Auth::user();
                        $canViewNotifications = false;
                        if ($currentPanel === 'admin') {
                            if ($userAuth && $userAuth->hasRole(DefaultSystemRolesEnum::SUPER_ADMIN())) {
                                $canViewNotifications = true;
                            } else {
                                try {
                                    $canViewNotifications = $userAuth?->hasPermissionTo(AdminPermissionEnum::NOTIFICATION_VIEW()) ?? false;
                                } catch (\Throwable $e) {
                                    $canViewNotifications = false;
                                }
                            }
                        } elseif ($currentPanel === 'seller') {
                            if ($isImpersonating || ($userAuth && $userAuth->hasRole(DefaultSystemRolesEnum::SELLER()))) {
                                $canViewNotifications = true;
                            } else {
                                try {
                                    $canViewNotifications = $userAuth?->hasPermissionTo(SellerPermissionEnum::NOTIFICATION_VIEW()) ?? false;
                                } catch (\Throwable $e) {
                                    $canViewNotifications = false;
                                }
                            }
                        } else {
                            $canViewNotifications = false;
                        }
                    @endphp
                    @if($canViewNotifications)
                        @php
                            $sentTo = $currentPanel === 'admin' ? 'admin' : 'seller';
                              $sellerEntity = $userAuth?->seller();
                            $id = $currentPanel === 'admin' ? $userAuth['id'] : $sellerEntity->user_id;
                            $headerData   = app(NotificationService::class)->getHeaderNotifications(
                                userId: $id,
                                sentTo: $sentTo
                            );
                            $notifications = $headerData['notifications'];
                            $unreadCount   = $headerData['unread_count'];
                        @endphp
                        <div class="nav-item dropdown me-3">
                            <button type="button" class="btn btn-icon border-0 shadow-none nav-link px-0"
                                    data-bs-toggle="dropdown" data-header-notification-button tabindex="-1" aria-label="Show notifications">
                                <i class="ti ti-bell fs-2"></i>
                                @if($unreadCount > 0)
                                    <span class="badge bg-red badge-sm mt-1 badge-notification text-red-fg"
                                          data-header-notification-count="{{ $unreadCount }}" data-count="{{ $unreadCount }}">
                                        {{ $unreadCount > 9 ? '9+' : $unreadCount }}
                                        <span class="visually-hidden">unread messages</span>
                                    </span>
                                @endif
                            </button>
                            <div class="dropdown-menu dropdown-menu-end panel-header-notifications"
                                 data-header-notification-menu>
                                <section class="panel-header-notifications__surface"
                                         aria-label="{{ __('labels.notifications') }}">
                                    <header class="panel-header-notifications__header">
                                        <h3>{{ __('labels.notifications') }}</h3>
                                        @if($unreadCount > 0)
                                            <span class="panel-header-notifications__count">
                                                {{ $unreadCount > 99 ? '99+' : $unreadCount }} {{ __('labels.unread') }}
                                            </span>
                                        @endif
                                    </header>
                                    <div class="panel-header-notifications__list">
                                        @forelse($notifications as $notification)
                                            @php
                                                $notificationType = $notification->data['type'] ?? 'general';
                                                $notificationIcon = match ($notificationType) {
                                                    'order', 'new_order', 'order_update', 'order_ready_for_pickup' => 'ti-shopping-bag',
                                                    'payment', 'wallet_transaction', 'withdrawal_request', 'withdrawal_process' => 'ti-credit-card',
                                                    'delivery' => 'ti-truck-delivery',
                                                    'product' => 'ti-package',
                                                    'support_message' => 'ti-message-circle',
                                                    'system' => 'ti-settings',
                                                    default => 'ti-bell',
                                                };
                                                $notificationTitle = filled($notification->title)
                                                    ? $notification->title
                                                    : $notification->message;
                                            @endphp
                                            <a href="{{ $notification->getRedirectUrlForPanel($currentPanel) }}"
                                               class="header-notification-link panel-header-notification {{ $notification->is_read ? 'is-read' : 'is-unread' }}"
                                               data-mark-read-url="{{ route($currentPanel . '.notifications.mark-read', $notification->id) }}"
                                               data-redirect-url="{{ $notification->getRedirectUrlForPanel($currentPanel) }}"
                                               data-is-read="{{ $notification->is_read ? '1' : '0' }}">
                                                <span class="panel-header-notification__icon" aria-hidden="true">
                                                    <i class="ti {{ $notificationIcon }}"></i>
                                                </span>
                                                <span class="panel-header-notification__content">
                                                    <strong>{{ $notificationTitle }}</strong>
                                                    @if(filled($notification->title) && filled($notification->message))
                                                        <span>{{ $notification->message }}</span>
                                                    @endif
                                                    <time datetime="{{ $notification->created_at->toIso8601String() }}">
                                                        {{ $notification->created_at->diffForHumans() }}
                                                    </time>
                                                </span>
                                                <i class="ti ti-chevron-right panel-header-notification__chevron" aria-hidden="true"></i>
                                            </a>
                                        @empty
                                            <div class="panel-header-notifications__empty">
                                                <i class="ti ti-bell-off" aria-hidden="true"></i>
                                                <span>{{ __('labels.no_notifications') }}</span>
                                            </div>
                                        @endforelse
                                    </div>
                                    <footer class="panel-header-notifications__footer">
                                        <a href="{{ route($currentPanel . '.notifications.index') }}"
                                           class="panel-header-notifications__view-all">
                                            {{ __('labels.view_all_notifications') }}
                                            <i class="ti ti-arrow-right" aria-hidden="true"></i>
                                        </a>
                                    </footer>
                                </section>
                            </div>
                        </div>
                        <script>
                            document.addEventListener('DOMContentLoaded', function () {
                                document.querySelectorAll('.header-notification-link').forEach(function (link) {
                                    link.addEventListener('click', function (event) {
                                        event.preventDefault();

                                        const redirectUrl = this.dataset.redirectUrl || this.getAttribute('href');
                                        const markReadUrl = this.dataset.markReadUrl;
                                        const isRead = this.dataset.isRead === '1';
                                        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

                                        if (!markReadUrl || isRead) {
                                            window.location.href = redirectUrl;
                                            return;
                                        }

                                        fetch(markReadUrl, {
                                            method: 'POST',
                                            headers: {
                                                'X-CSRF-TOKEN': csrfToken,
                                                'X-Requested-With': 'XMLHttpRequest',
                                                'Accept': 'application/json',
                                            },
                                        }).finally(function () {
                                            window.location.href = redirectUrl;
                                        });
                                    });
                                });
                            });
                        </script>
                    @endif

                    <!-- Admin profile dropdown -->
                    <div class="nav-item dropdown d-flex">
                        <a href="#" class="nav-link d-flex lh-1 text-reset p-0" data-bs-toggle="dropdown"
                           aria-label="Open user menu">
                            @if($user->profile_image)
                                <span class="avatar avatar-sm" id="profile"
                                      style="background-image: url({{ $user->profile_image }});"></span>
                            @else
                                <span class="avatar avatar-sm bg-primary text-primary-fg"
                                      id="profile">{{ strtoupper(substr($user->name, 0, 2)) }}</span>
                            @endif
                            <div class="d-none d-xl-block ps-2">
                                <div>{{ $user->username ?? "" }}</div>
                                <div class="mt-1 small text-muted">
                                    @if ($user->role == 'admin')
                                        {{ __('labels.administrator')}}
                                    @endif
                                </div>
                            </div>
                        </a>
                        <div class="dropdown-menu dropdown-menu-end dropdown-menu-arrow">
                            <a href="{{route(request()->segment(1).'.profile.index')}}" class="dropdown-item">
                                <i class="ti ti-user me-2 fs-2"></i>{{ __('labels.profile') }}
                            </a>
                            <div class="dropdown-divider"></div>
                            <a href="{{ route(request()->segment(1).'.logout') }}" class="dropdown-item js-panel-logout">
                                <i class="ti ti-logout me-2 fs-2"></i>{{ __('labels.logout') }}
                            </a>
                        </div>
                    </div>
                </div>
                <!-- BEGIN MODAL -->
                <!-- END MODAL -->
            </div>
        </div>
    </div>
    @if(($systemSettings['demoMode'] ?? false))
        <div class="alert alert-danger alert-dismissible m-3 text-danger" role="alert">
            <div class="alert-icon">
                <!-- Download SVG icon from http://tabler.io/icons/icon/info-circle -->
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none"
                     stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                     class="icon alert-icon icon-2">
                    <path d="M3 12a9 9 0 1 0 18 0a9 9 0 0 0 -18 0"></path>
                    <path d="M12 9h.01"></path>
                    <path d="M11 12h1v4h1"></path>
                </svg>
            </div>
            @php
                $demoMessage = $user->access_panel->value === 'admin'
                    ? ($systemSettings['adminDemoModeMessage'] ?? null)
                    : ($systemSettings['sellerDemoModeMessage'] ?? null);
            @endphp
            {{ $demoMessage ?: __('labels.demo_mode_message') }}
        </div>
    @endif
</div>
