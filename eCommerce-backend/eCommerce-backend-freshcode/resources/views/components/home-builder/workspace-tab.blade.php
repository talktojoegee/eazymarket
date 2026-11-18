@props([
    'pane',
    'icon',
    'title',
    'description',
    'active' => false,
])

<li class="nav-item" role="presentation">
    <a href="#hb-pane-{{ $pane }}"
       @class(['nav-link', 'hb-workspace-tab', 'active' => $active])
       data-pane="{{ $pane }}"
       role="tab"
       aria-controls="hb-pane-{{ $pane }}"
       aria-selected="{{ $active ? 'true' : 'false' }}">
        <span class="hb-workspace-tab-icon"><i class="ti {{ $icon }}"></i></span>
        <span class="hb-workspace-tab-copy">
            <strong>{{ $title }}</strong>
            <small>{{ $description }}</small>
        </span>
        <i class="ti ti-chevron-right hb-workspace-tab-arrow"></i>
    </a>
</li>
