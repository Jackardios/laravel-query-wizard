<?php

namespace Jackardios\QueryWizard\Tests\App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class ScopeModel extends Model
{
    protected $guarded = [];

    public $timestamps = false;

    public static function boot(): void
    {
        parent::boot();

        static::addGlobalScope('nameNotTest', function (Builder $builder) {
            $builder->where('name', '<>', 'test');
        });
    }
}
