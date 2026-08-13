<?php

namespace Database\Factories;

use App\Models\Role;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Role>
 */
class RoleFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<Role>
     */
    protected $model = Role::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->jobTitle();

        return [
            'team_id' => null,
            'key' => Str::slug($name).'-'.fake()->unique()->numberBetween(1, 999999),
            'name' => $name,
            'is_default' => false,
            'is_super' => false,
        ];
    }

    /**
     * A super role that grants every permission.
     */
    public function super(): static
    {
        return $this->state(fn (): array => ['is_super' => true]);
    }

    /**
     * The base role assigned to every member on joining.
     */
    public function default(): static
    {
        return $this->state(fn (): array => ['is_default' => true]);
    }

    /**
     * A global catalog role, shared across all teams.
     */
    public function global(): static
    {
        return $this->state(fn (): array => ['team_id' => null]);
    }

    /**
     * A custom role owned by a specific team.
     */
    public function forTeam(Team $team): static
    {
        return $this->state(fn (): array => ['team_id' => $team->id]);
    }
}
