<?php

namespace Database\Factories;

use App\Enums\HomePageScopeEnum;
use App\Models\HomeLayout;
use Illuminate\Database\Eloquent\Factories\Factory;

class HomeLayoutFactory extends Factory
{
    protected $model = HomeLayout::class;

    public function definition(): array
    {
        return [
            'scope_type' => HomePageScopeEnum::GLOBAL(),
            'scope_id'   => null,
            'is_active'  => true,
        ];
    }
}
