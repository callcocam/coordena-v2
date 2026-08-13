<?php

namespace App\Models;

use App\Enums\SpeakerRole;
use App\Support\Phone;
use Database\Factories\SpeakerFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * Orador do acervo, vinculado a uma congregação.
 *
 * @property string $id
 * @property string $congregation_id
 * @property string $name
 * @property SpeakerRole $role
 * @property string|null $phone
 * @property bool $is_active
 * @property string|null $notes
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Congregation $congregation
 * @property-read Collection<int, PublicTalkOutline> $outlines
 */
#[Fillable(['congregation_id', 'name', 'role', 'phone', 'is_active', 'notes'])]
class Speaker extends Model
{
    /** @use HasFactory<SpeakerFactory> */
    use HasFactory, HasUlids, SoftDeletes;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'role' => SpeakerRole::class,
            'is_active' => 'boolean',
        ];
    }

    /**
     * Get the congregation this speaker belongs to.
     *
     * @return BelongsTo<Congregation, $this>
     */
    public function congregation(): BelongsTo
    {
        return $this->belongsTo(Congregation::class);
    }

    /**
     * Get the outlines this speaker is prepared to deliver.
     *
     * @return BelongsToMany<PublicTalkOutline, $this>
     */
    public function outlines(): BelongsToMany
    {
        return $this->belongsToMany(PublicTalkOutline::class, 'speaker_outlines', 'speaker_id', 'outline_id');
    }

    /**
     * Scope the query to active speakers.
     *
     * @param  Builder<static>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /**
     * Normalize the phone before persisting.
     */
    protected function phone(): Attribute
    {
        return Attribute::set(fn (?string $value): ?string => Phone::normalize($value));
    }
}
