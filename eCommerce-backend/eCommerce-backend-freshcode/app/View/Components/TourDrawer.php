<?php

namespace App\View\Components;

use App\Support\TourCatalog;
use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\Component;

final class TourDrawer extends Component
{
    public array $groups;

    public array $tours;

    public string $panel;

    public string $knowledgeBaseUrl;

    public function __construct(TourCatalog $catalog)
    {
        $this->panel = request()->segment(1) ?: 'admin';
        $user = Auth::user();
        $visible = $user ? $catalog->visibleFor($user, $this->panel) : ['groups' => [], 'tours' => []];
        $this->groups = $visible['groups'];
        $this->tours = $visible['tours'];
        $guidePath = $this->panel === 'seller' ? '/seller-guide' : '/admin-guide';
        $this->knowledgeBaseUrl = url($guidePath).'?lang='.urlencode(app()->getLocale());
    }

    public function render(): View|Closure|string
    {
        return view('components.tour-drawer');
    }
}
