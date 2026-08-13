<?php

namespace App\Models;

use App\Enums\PublicTalkOutlineStatus;
use Database\Factories\PublicTalkOutlineFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Carbon;

/**
 * Esboço oficial de discurso público (catálogo S-99).
 *
 * @property string $id
 * @property int $number
 * @property string $title
 * @property string|null $theme
 * @property string|null $reference_url
 * @property PublicTalkOutlineStatus $status
 * @property int|null $replaced_by_number
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, Speaker> $speakers
 */
#[Fillable(['number', 'title', 'theme', 'reference_url', 'status', 'replaced_by_number'])]
class PublicTalkOutline extends Model
{
    /** @use HasFactory<PublicTalkOutlineFactory> */
    use HasFactory, HasUlids;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'number' => 'integer',
            'replaced_by_number' => 'integer',
            'status' => PublicTalkOutlineStatus::class,
        ];
    }

    /**
     * Get the speakers prepared to deliver this outline.
     *
     * @return BelongsToMany<Speaker, $this>
     */
    public function speakers(): BelongsToMany
    {
        return $this->belongsToMany(Speaker::class, 'speaker_outlines', 'outline_id', 'speaker_id');
    }
}
