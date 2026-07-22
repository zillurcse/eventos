<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Form definition — registration, speaker, partner, survey (architecture §6.12).
 */
class Form extends Model
{
    use BelongsToOrganization, SoftDeletes, HasUuid;

    protected $guarded = [];

    protected $casts = ['settings' => 'array'];

    public function sections(): HasMany
    {
        return $this->hasMany(FormSection::class);
    }

    public function fields(): HasMany
    {
        return $this->hasMany(FormField::class)->orderBy('sort_order');
    }

    public function logicRules(): HasMany
    {
        return $this->hasMany(FormLogicRule::class);
    }

    public function submissions(): HasMany
    {
        return $this->hasMany(FormSubmission::class);
    }

    /** A per-audience profile form (Event Settings › Profile). */
    public function isProfileForm(): bool
    {
        return str_starts_with((string) $this->key, 'profile.');
    }

    /**
     * The field keys a collection surface actually shows — visible, and opted
     * into that surface in the builder. Both the renderer and the validator
     * read this, so a required field a surface hides can never block it.
     *
     * @param  'registration'|'onboarding'|'public'  $surface
     * @return array<int,string>
     */
    public function surfaceKeys(string $surface): array
    {
        return $this->fields
            ->reject(fn ($f) => in_array($f->type, ['section_break', 'recaptcha'], true))
            ->filter(fn ($f) => ($f->meta['visible'] ?? true) !== false)
            ->filter(fn ($f) => ($f->meta['surfaces'][$surface] ?? true) !== false)
            ->pluck('key')
            ->values()
            ->all();
    }

    /**
     * Registration additionally forces the email field in: signup creates a
     * login, which cannot exist without an address, so an organizer toggling
     * "add to user registration" off for it must not break the door.
     *
     * @return array<int,string>
     */
    public function registrationKeys(): array
    {
        return array_values(array_unique(array_merge(
            $this->surfaceKeys('registration'),
            $this->fields->where('type', 'email')->pluck('key')->all(),
        )));
    }
}
