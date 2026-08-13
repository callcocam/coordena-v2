<?php

namespace App\Http\Requests\Teams;

use App\Enums\TeamPermission;
use App\Models\Role;
use App\Models\Team;
use App\Support\PermissionCatalog;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class SaveCargoRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $team = $this->route('team');

        return $team instanceof Team && Gate::allows('manageRoles', $team);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $team = $this->route('team');

        abort_if(! $team instanceof Team, 404);

        return [
            'name' => ['required', 'string', 'max:255', $this->uniqueKeyRule($team)],
            'permissions' => ['array'],
            'permissions.*' => ['string', Rule::in($this->selectablePermissions())],
        ];
    }

    /**
     * The stable key derived from the submitted name.
     */
    public function cargoKey(): string
    {
        return Str::slug((string) $this->validated('name'));
    }

    /**
     * Ensure the derived key is unique within the team, ignoring the edited cargo.
     */
    protected function uniqueKeyRule(Team $team): callable
    {
        return function (string $attribute, mixed $value, callable $fail) use ($team): void {
            $currentRole = $this->route('role');

            $exists = Role::query()
                ->where('team_id', $team->id)
                ->byKey(Str::slug((string) $value))
                ->when($currentRole instanceof Role, fn ($query) => $query->whereKeyNot($currentRole->id))
                ->exists();

            if ($exists) {
                $fail(__('Já existe um cargo com esse nome nesta congregação.'));
            }
        };
    }

    /**
     * Permission names that may be granted to a custom cargo.
     *
     * `role:manage` is excluded so custom cargos cannot escalate their own management rights.
     *
     * @return list<string>
     */
    protected function selectablePermissions(): array
    {
        return array_values(array_diff(
            PermissionCatalog::names(),
            [TeamPermission::ManageRoles->value],
        ));
    }
}
