<?php

namespace Database\Factories;

use App\Models\Sale;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * @extends Factory<Sale>
 */
class SaleFactory extends Factory
{
    protected $model = Sale::class;

    public function definition(): array
    {
        $name = fake()->words(2, true).' Sale';

        return [
            'name' => Str::title($name),
            'slug' => Str::slug($name).'-'.fake()->unique()->numberBetween(1000, 9999),
            'description' => null,
            'starts_at' => Carbon::now()->subDays(2),
            'ends_at' => Carbon::now()->addDays(2),
            'closed_at' => null,
        ];
    }

    /** A sale that is running right now. */
    public function live(): static
    {
        return $this->state(fn () => [
            'starts_at' => Carbon::now()->subDays(1),
            'ends_at' => Carbon::now()->addDays(3),
            'closed_at' => null,
        ]);
    }

    /** Past its end date, but not yet closed out by the scheduler. */
    public function ended(): static
    {
        return $this->state(fn () => [
            'starts_at' => Carbon::now()->subDays(10),
            'ends_at' => Carbon::now()->subDays(3),
            'closed_at' => null,
        ]);
    }

    /** Ended and closed out, so reports are locked. */
    public function closed(): static
    {
        return $this->state(fn () => [
            'starts_at' => Carbon::now()->subDays(10),
            'ends_at' => Carbon::now()->subDays(3),
            'closed_at' => Carbon::now()->subDays(3),
        ]);
    }
}
