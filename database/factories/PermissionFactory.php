<?php

namespace Database\Factories;

use App\Models\Permission;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Permission>
 */
class PermissionFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<Permission>
     */
    protected $model = Permission::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $group = fake()->randomElement(['team', 'member', 'invitation', 'congregation', 'service']);
        $action = fake()->unique()->slug(2);

        return [
            'name' => "{$group}:{$action}",
            'label' => fake()->sentence(3),
            'group' => $group,
        ];
    }
}
