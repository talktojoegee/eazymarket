<?php

namespace Database\Factories;

use App\Enums\HomePage\ItemPurposeEnum;
use App\Models\HomeSection;
use App\Models\HomeSectionItem;
use Illuminate\Database\Eloquent\Factories\Factory;

class HomeSectionItemFactory extends Factory
{
    protected $model = HomeSectionItem::class;

    public function definition(): array
    {
        return [
            'home_section_id' => HomeSection::factory(),
            'purpose'         => ItemPurposeEnum::DISPLAY(),
            'itemable_type'   => null,
            'itemable_id'     => null,
            'title'           => null,
            'link_url'        => null,
            'sort_order'      => 0,
        ];
    }
}
