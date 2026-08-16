<?php

namespace Database\Factories;

use App\Models\CouponCode;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<CouponCode>
 */
class CouponCodeFactory extends Factory
{
    protected $model = CouponCode::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'code' => Str::upper(Str::random(8)),
            'is_active' => true,
        ];
    }
}
