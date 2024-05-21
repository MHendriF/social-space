<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Group>
 */
class GroupFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => $name = str(fake()->word())->title(),
            'slug' => str($name)->slug(),
            'user_id' => rand(1, 10),
            'auto_approval' => rand(0, 1),
            'about' => fake()->paragraphs(2, true),
        ];
    }
}
