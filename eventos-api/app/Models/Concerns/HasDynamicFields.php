<?php

namespace App\Models\Concerns;

/**
 * Binds an entity (contact / participation / partner) to its builder-defined
 * fields, projecting captured values into the `profile_data` JSONB column for
 * fast reads (architecture §3.4, §6.12). The full validate-against-form-fields
 * engine lands with the Form Builder phase; this provides the read/write API.
 */
trait HasDynamicFields
{
    /** Read a builder-defined attribute from the JSONB projection. */
    public function dynamic(string $key, mixed $default = null): mixed
    {
        return data_get($this->profile_data ?? [], $key, $default);
    }

    /** Set a builder-defined attribute (does not persist until saved). */
    public function setDynamic(string $key, mixed $value): static
    {
        $data = $this->profile_data ?? [];
        data_set($data, $key, $value);
        $this->profile_data = $data;

        return $this;
    }

    /** Merge a full submission projection in one shot. */
    public function projectDynamic(array $values): static
    {
        $this->profile_data = array_replace($this->profile_data ?? [], $values);

        return $this;
    }
}
