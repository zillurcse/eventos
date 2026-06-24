<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Generates a time-ordered UUID (v7) public identifier on create for
 * externally-exposed resources (events, tickets, orders, participations…),
 * so sequential BIGINT ids never leak in URLs/QR codes (architecture §3.3).
 */
trait HasUuid
{
    public static function bootHasUuid(): void
    {
        static::creating(function (Model $model): void {
            $column = $model->uuidColumn();

            if (empty($model->{$column})) {
                $model->{$column} = (string) (method_exists(Str::class, 'uuid7')
                    ? Str::uuid7()
                    : Str::orderedUuid());
            }
        });
    }

    public function uuidColumn(): string
    {
        return 'uuid';
    }
}
