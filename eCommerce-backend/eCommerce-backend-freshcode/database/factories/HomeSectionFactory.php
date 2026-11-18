<?php

namespace Database\Factories;

use App\Enums\HomePage\BackgroundTypeEnum;
use App\Enums\HomePage\HomeSectionTypeEnum;
use App\Enums\HomePage\OrientationEnum;
use App\Enums\HomePage\ProductSourceEnum;
use App\Models\HomeLayout;
use App\Models\HomeSection;
use Illuminate\Database\Eloquent\Factories\Factory;

class HomeSectionFactory extends Factory
{
    protected $model = HomeSection::class;

    public function definition(): array
    {
        return [
            'home_layout_id' => HomeLayout::factory(),
            'type'           => HomeSectionTypeEnum::PRODUCTS(),
            'title'          => $this->faker->words(2, true),
            'style'          => null,
            'config'         => [
                'source'          => ProductSourceEnum::NEWLY_ADDED(),
                'orientation'     => OrientationEnum::HORIZONTAL(),
                'background_type' => BackgroundTypeEnum::NONE(),
            ],
            'sort_order'     => 0,
            'is_active'      => true,
        ];
    }
}
