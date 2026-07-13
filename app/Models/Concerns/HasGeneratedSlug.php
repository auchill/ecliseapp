<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

trait HasGeneratedSlug
{
    protected static function bootHasGeneratedSlug(): void
    {
        static::saving(function (Model $model): void {
            if (! $model->isDirty('name') && filled($model->slug)) {
                return;
            }

            $model->slug = static::uniqueGeneratedSlug($model);
        });
    }

    protected static function uniqueGeneratedSlug(Model $model): string
    {
        $base = Str::slug((string) $model->name) ?: 'item';
        $slug = $base;
        $counter = 2;

        while ($model->newQuery()
            ->where('slug', $slug)
            ->when($model->exists, fn ($query) => $query->whereKeyNot($model->getKey()))
            ->exists()) {
            $slug = "{$base}-{$counter}";
            $counter++;
        }

        return $slug;
    }
}
