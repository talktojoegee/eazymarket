@php
    use App\Models\Setting;
    use Illuminate\Support\Facades\Auth;

    $panel = request()->segment(1);
    $isImpersonatingForLogo = session('impersonating_as_seller', false);
    $linkPanel = $isImpersonatingForLogo ? 'seller' : $panel;

    $userRole = Auth::user();
    $isImpersonating = session('impersonating_as_seller', false);
    $currentPanel = $isImpersonating ? 'seller' : ($userRole->access_panel->value ?? $panel);
    $menuItems = app(\App\Services\PanelNavigationService::class)
        ->visibleMenu($userRole, $currentPanel, $isImpersonating);
@endphp

<aside id="hui-sidebar" class="w-[240px] shrink-0 bg-white dark:bg-ink-900 border-r border-neutral-200 dark:border-white/[.06] flex flex-col fixed lg:sticky top-0 h-screen z-40 -translate-x-full lg:translate-x-0 transition-transform duration-200">
    <div class="h-14 flex items-center gap-2.5 px-4 border-b border-neutral-100 dark:border-white/[.05] shrink-0">
        <a href="{{ url($linkPanel) }}" class="flex items-center gap-2.5">
            <img src="{{ !empty($systemSettings['logo']) ? $systemSettings['logo'] : asset('logos/hyper-logo-white.png') }}"
                 alt="{{ $systemSettings['appName'] ?? '' }}" class="h-7 w-auto hidden dark:block"
                 onerror="this.onerror=null;this.src='{{ hyperAsset('logos/hyper-logo-white.png') }}'">
            <img src="{{ !empty($systemSettings['logoDark']) ? $systemSettings['logoDark'] : (!empty($systemSettings['logo']) ? $systemSettings['logo'] : asset('logos/hyper-logo.png')) }}"
                 alt="{{ $systemSettings['appName'] ?? '' }}" class="h-7 w-auto dark:hidden"
                 onerror="this.onerror=null;this.src='{{ hyperAsset('logos/hyper-logo.png') }}'">
        </a>
        <button data-hui-sidebar-close class="ms-auto lg:hidden w-8 h-8 rounded-lg flex items-center justify-center text-neutral-500 hover:bg-neutral-100 dark:hover:bg-white/5">
            <i class="ti ti-x text-[18px]"></i>
        </button>
    </div>

    <nav class="flex-1 px-3 py-3 space-y-0.5 text-[13.5px] overflow-y-auto" data-sidebar-scroll-container>
        @foreach ($menuItems as $key => $item)
            @if (($item['type'] ?? '') === 'section')
                <div class="pt-4 pb-1.5 px-2.5 text-[11px] font-medium uppercase tracking-wider text-neutral-400">
                    {{ __($item['title']) }}
                </div>
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

            @if ($isDropdown)
                <div>
                    <button type="button" data-hui-collapse="hui-sub-{{ $key }}" aria-expanded="{{ $isActive ? 'true' : 'false' }}"
                            class="w-full flex items-center gap-2.5 h-9 px-2.5 rounded-lg text-neutral-600 dark:text-neutral-400 hover:bg-neutral-50 dark:hover:bg-white/[.04] hover:text-ink dark:hover:text-white transition {{ $menuGradient }}">
                        <i class="ti {{ $item['icon'] }} text-[17px] {{ $isActive ? 'text-brand-700 dark:text-brand' : 'text-neutral-500 dark:text-neutral-400' }}"></i>
                        <span class="{{ $isActive ? 'font-medium text-ink dark:text-white' : '' }}">{{ __($item['title']) }}</span>
                        <i data-hui-chevron class="ti ti-chevron-down text-[15px] ms-auto text-neutral-400 transition-transform" style="{{ $isActive ? 'transform:rotate(180deg)' : '' }}"></i>
                    </button>
                    <div id="hui-sub-{{ $key }}" class="{{ $isActive ? '' : 'hidden' }} mt-0.5 ml-3.5 pl-3 border-l border-neutral-200 dark:border-white/10 space-y-0.5">
                        @foreach ($visibleSubRoutes as $subRoute)
                            @php
                                $routeName = $subRoute['sub_route'] ?? (is_array($subRoute) ? array_values($subRoute)[0] : $subRoute);
                                $isSubActive = isset($sub_page) && ($subRoute['sub_active'] ?? null) === $sub_page;
                            @endphp
                            <a href="{{ route($routeName, $subRoute['route_param'] ?? []) }}"
                               @if ($isSubActive) aria-current="page" @endif
                               class="flex items-center gap-2 h-8 px-2.5 rounded-lg transition {{ $isSubActive ? 'bg-neutral-100 dark:bg-white/[.06] text-ink dark:text-white font-medium' : 'text-neutral-500 dark:text-neutral-400 hover:bg-neutral-50 dark:hover:bg-white/[.04] hover:text-ink dark:hover:text-white' }}">
                                <span class="w-1.5 h-1.5 rounded-full {{ $isSubActive ? 'bg-brand' : 'bg-neutral-300 dark:bg-white/20' }}"></span>
                                {{ __($subRoute['sub_title']) }}
                            </a>
                        @endforeach
                    </div>
                </div>
            @else
                <a href="{{ route($item['route']) }}"
                   @if ($isActive) aria-current="page" @endif
                   class="nav-item flex items-center gap-2.5 h-9 px-2.5 rounded-lg transition relative {{ $isActive ? 'bg-neutral-100 dark:bg-white/[.06] font-medium' : 'text-neutral-600 dark:text-neutral-400 hover:bg-neutral-50 dark:hover:bg-white/[.04] hover:text-ink dark:hover:text-white' }} {{ $menuGradient }}">
                    @if ($isActive)<span class="absolute left-0 top-1/2 -translate-y-1/2 w-[3px] h-4 rounded-r bg-brand"></span>@endif
                    <i class="ti {{ $item['icon'] }} text-[17px] {{ $isActive ? 'text-brand-700 dark:text-brand' : 'text-neutral-500 dark:text-neutral-400' }}"></i>
                    <span>{{ __($item['title']) }}</span>
                </a>
            @endif
        @endforeach
    </nav>

    <div class="px-3 py-2 border-t border-neutral-100 dark:border-white/[.05] text-center text-[11px] text-neutral-400 shrink-0">
        v{{ Setting::getCurrentVersion() }}
    </div>
</aside>

<div data-hui-sidebar-backdrop class="hidden lg:hidden fixed inset-0 bg-ink/40 backdrop-blur-[1px] z-30"></div>
