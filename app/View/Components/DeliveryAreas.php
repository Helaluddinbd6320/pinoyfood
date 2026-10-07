<?php

namespace App\View\Components;

use App\Models\Area;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\View\Component;

class DeliveryAreas extends Component
{
    public Collection $areas;

    public function __construct()
    {
        $this->areas = Area::query()
            ->where('is_active', true)
            ->where('name', '!=', 'Other')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
    }

    public function render(): View
    {
        return view('components.delivery-areas');
    }
}