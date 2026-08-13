<?php

namespace App\Models;

use App\Enums\ExchangeOpt;
use App\Support\Phone;
use Database\Factories\CongregationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * Congregação do acervo pessoal do dono (compartilhado entre os times dele).
 *
 * @property string $id
 * @property string $owner_user_id
 * @property string $name
 * @property string|null $city
 * @property string|null $circuit
 * @property string|null $address
 * @property string|null $contact_name
 * @property string|null $contact_phone
 * @property string|null $contact_email
 * @property string|null $secretary_name
 * @property string|null $secretary_phone
 * @property string|null $secretary_email
 * @property int|null $meeting_weekday
 * @property string|null $meeting_time
 * @property ExchangeOpt $exchange_opt
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read User $owner
 * @property-read Collection<int, Speaker> $speakers
 */
#[Fillable([
    'owner_user_id',
    'name',
    'city',
    'circuit',
    'address',
    'contact_name',
    'contact_phone',
    'contact_email',
    'secretary_name',
    'secretary_phone',
    'secretary_email',
    'meeting_weekday',
    'meeting_time',
    'exchange_opt',
])]
class Congregation extends Model
{
    /** @use HasFactory<CongregationFactory> */
    use HasFactory, HasUlids, SoftDeletes;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'meeting_weekday' => 'integer',
            'exchange_opt' => ExchangeOpt::class,
        ];
    }

    /**
     * Get the owner of the acervo this congregation belongs to.
     *
     * @return BelongsTo<User, $this>
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    /**
     * Get the speakers of this congregation.
     *
     * @return HasMany<Speaker, $this>
     */
    public function speakers(): HasMany
    {
        return $this->hasMany(Speaker::class);
    }

    /**
     * Scope the query to the acervo of the given owner.
     *
     * @param  Builder<static>  $query
     */
    public function scopeOwnedBy(Builder $query, User|string $owner): void
    {
        $query->where('owner_user_id', $owner instanceof User ? $owner->id : $owner);
    }

    /**
     * Normalize the contact phone before persisting.
     */
    protected function contactPhone(): Attribute
    {
        return Attribute::set(fn (?string $value): ?string => Phone::normalize($value));
    }

    /**
     * Normalize the secretary phone before persisting.
     */
    protected function secretaryPhone(): Attribute
    {
        return Attribute::set(fn (?string $value): ?string => Phone::normalize($value));
    }
}
