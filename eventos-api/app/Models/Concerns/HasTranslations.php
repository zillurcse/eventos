<?php

namespace App\Models\Concerns;

use App\Models\Translation;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * Resolves localized fields from the translations table with locale fallback
 * (architecture §6.10). Models declare `public array $translatable = [...]`.
 */
trait HasTranslations
{
    public function translations(): MorphMany
    {
        return $this->morphMany(Translation::class, 'translatable');
    }

    /** Translated value for a field, falling back to the base attribute. */
    public function translate(string $field, ?string $locale = null): ?string
    {
        $locale ??= app()->getLocale();

        $hit = $this->translations
            ->firstWhere(fn ($t) => $t->field === $field && $t->locale === $locale);

        return $hit?->value ?? $this->getAttribute($field);
    }

    public function setTranslation(string $field, string $locale, string $value): void
    {
        $this->translations()->updateOrCreate(
            ['field' => $field, 'locale' => $locale],
            ['value' => $value, 'organization_id' => $this->organization_id ?? null],
        );
    }
}
