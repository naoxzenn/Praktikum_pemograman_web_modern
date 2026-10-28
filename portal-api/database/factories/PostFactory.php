<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Post>
 */
class PostFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id'     => User::factory(),
            'category_id' => \App\Models\PostCategory::factory(),
            'title'       => $this->faker->sentence(6),
            'slug'        => $this->faker->unique()->slug(4),
            'summary'     => $this->faker->sentence(15),
            'content'     => '<p>' . $this->faker->paragraphs(3, true) . '</p>',
            'status'      => 'draft',
        ];
    }

    public function published(): static
    {
        return $this->state(fn (array $attributes) => [
            'status'       => 'published',
            'published_at' => now(),
        ]);
    }

    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'pending',
        ]);
    }
}
