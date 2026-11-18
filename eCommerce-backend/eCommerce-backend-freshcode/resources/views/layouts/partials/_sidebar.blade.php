<aside id="panel-sidebar" class="navbar navbar-vertical navbar-expand-lg overflow-auto" data-bs-theme="dark">
    <div class="container-fluid">
        <div class="navbar-brand navbar-brand-autodark sidebar-brand">
            @php
                use App\Models\Setting;use Illuminate\Support\Facades\Auth;
                $panel = request()->segment(1);
            @endphp
            @php
                $isImpersonatingForLogo = session('impersonating_as_seller', false);
                $linkPanel = $isImpersonatingForLogo ? 'seller' : $panel;
            @endphp
            <a href="{{ url($linkPanel) }}" class="sidebar-brand-link">
                <img
                    src="{{ !empty($systemSettings['logo']) ? $systemSettings['logo'] : asset('logos/hyper-logo-white.png') }}"
                    alt="{{$systemSettings['appName'] ?? ""}}" width="150" class="sidebar-logo-full"
                    onerror="this.onerror=null;this.src='{{ hyperAsset('logos/hyper-logo-white.png') }}'">
                <img
                    src="{{ $systemSettings['favicon'] ?? asset('logos/hyper-favicon.png') }}"
                    alt="" width="32" height="32" class="sidebar-logo-slim" aria-hidden="true">
            </a>
            <button class="sidebar-mobile-close" type="button" data-mobile-sidebar-close
                    aria-label="Close sidebar">
                <span aria-hidden="true">&times;</span>
            </button>
            <button type="button" class="sidebar-size-toggle d-none d-lg-inline-flex"
                    data-sidebar-size-toggle aria-label="Collapse sidebar" aria-pressed="false"
                    title="Collapse sidebar">
                <i class="ti ti-chevron-left" aria-hidden="true"></i>
            </button>
        </div>

        <div class="collapse navbar-collapse" id="sidebar-menu" data-sidebar-scroll-container>
            <ul class="navbar-nav pt-lg-3">
                @php
                    $userRole = Auth::user();
                    $isImpersonating = session('impersonating_as_seller', false);
                    $currentPanel = $isImpersonating ? 'seller' : ($userRole->access_panel->value ?? $panel);
                    $menuItems = app(\App\Services\PanelNavigationService::class)
                        ->visibleMenu($userRole, $currentPanel, $isImpersonating);
                @endphp

                @foreach ($menuItems as $key => $item)
                    @if (($item['type'] ?? '') === 'section')
                        <li class="nav-item mt-2 mb-1 px-3">
                            <span class="nav-link-title text-uppercase fw-bold fs-7 text-secondary tracking-wide" style="font-size: 0.65rem; letter-spacing: 0.08em; padding: 0.25rem 0;">
                                {{ __($item['title']) }}
                            </span>
                        </li>
                        @continue
                    @endif

                    @php
                        $isDropdown = is_array($item['route']);
                        $isActive = isset($page, $item['active']) && $page === $item['active'];
                        $menuGradient = in_array($item['menu_gradient'] ?? null, ['primary', 'success', 'warning', 'danger', 'info'], true)
                            ? 'sidebar-menu-gradient sidebar-menu-gradient--'.$item['menu_gradient']
                            : '';
                        $visibleSubRoutes = $isDropdown ? $item['route'] : null;
                    @endphp

                    <li class="nav-item {{ $isDropdown ? 'dropdown' : '' }} {{ $isActive ? 'active' : '' }}"
                        data-tour-menu="{{ $key }}"
                        data-sidebar-label="{{ __($item['title']) }}">
                        @if ($isDropdown)
                            <a class="nav-link dropdown-toggle {{ $isActive ? 'active show' : '' }} {{ $menuGradient }}"
                               href="#navbar-{{ $key }}" data-bs-toggle="dropdown" data-bs-auto-close="false"
                               aria-expanded="{{ $isActive ? 'true' : 'false' }}">
                                <span class="nav-link-icon d-md-none d-lg-inline-block">
                                    <i class="ti {{ $item['icon'] }} fs-2"></i>
                                </span>
                                <span class="nav-link-title">{{ __($item['title']) }}</span>
                            </a>
                            @if (count($visibleSubRoutes) > 0)
                                <div class="dropdown-menu {{ $isActive ? 'show' : '' }}">
                                    <div class="sidebar-flyout-title">{{ __($item['title']) }}</div>
                                    @foreach ($visibleSubRoutes as $subRoute)
                                        @php
                                            if (is_array($subRoute)) {
                                                $routeName = $subRoute['route'] ?? array_values($subRoute)[0];
                                                $routeParams =
                                                    $subRoute['params'] ?? (is_array($subRoute) ? $subRoute : []);
                                            } else {
                                                $routeName = $subRoute;
                                                $routeParams = [];
                                            }
                                            $isSubActive =
                                                isset($subRoute) &&
                                                isset($sub_page) &&
                                                $subRoute['sub_active'] === $sub_page;
                                        @endphp
                                        <a class="dropdown-item {{ $isSubActive ? 'active' : '' }}"
                                           @if ($isSubActive) aria-current="page" @endif
                                           href="{{route($subRoute['sub_route'],$subRoute['route_param'] ?? []) }}">
                                            <i class="ti ti-point{{ $isSubActive ? '-filled' : '' }} fs-2"></i>{{ __($subRoute['sub_title']) }}
                                        </a>
                                    @endforeach
                                </div>
                            @endif
                        @else
                            <a class="nav-link {{ $isActive ? 'active' : '' }} {{ $menuGradient }}"
                               @if ($isActive) aria-current="page" @endif
                               href="{{ route($item['route']) }}">
                                <span class="nav-link-icon">
                                    <i class="ti {{ $item['icon'] }} fs-2"></i>
                                </span>
                                <span class="nav-link-title">{{ __($item['title']) }}</span>
                            </a>
                        @endif
                    </li>
                @endforeach
            </ul>
        </div>
        <div class="sidebar-version d-flex justify-content-center align-content-center pt-2 pb-5 fw-medium">
            V{{Setting::getCurrentVersion()}}</div>
    </div>
</aside>
<button type="button" class="sidebar-mobile-backdrop" data-mobile-sidebar-close tabindex="-1"
        aria-label="Close sidebar"></button>
