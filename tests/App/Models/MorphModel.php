<?php

namespace Jackardios\QueryWizard\Tests\App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class MorphModel extends Model
{
    protected $guarded = [];

    public $timestamps = false;

    public function parent(): MorphTo
    {
        return $this->morphTo();
    }
}
