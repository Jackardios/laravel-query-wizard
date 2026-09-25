<?php

namespace Jackardios\QueryWizard\Tests\App\Models;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Builder;
use Stringable;

class TypedScopeModel extends TestModel
{
    protected $table = 'test_models';

    public function scopeFlagged(Builder $query, bool $flag): Builder
    {
        return $query->where('name', $flag ? 'yes' : 'no');
    }

    public function scopeFlaggedOrNamed(Builder $query, bool|string $value): Builder
    {
        return $query->where('name', is_bool($value) ? ($value ? 'yes' : 'no') : $value);
    }

    public function scopeCountOrFlag(Builder $query, int|bool $value): Builder
    {
        return $query->where('id', '>=', (int) $value);
    }

    public function scopeNamesIn(Builder $query, array $names): Builder
    {
        return $query->whereIn('name', $names);
    }

    public function scopeCreatedBefore(Builder $query, DateTimeInterface $date): Builder
    {
        return $query->where('created_at', '<', $date);
    }

    public function scopeLabelled(Builder $query, Stringable&DateTimeInterface $label): Builder
    {
        return $query;
    }
}
