<?php

namespace Jackardios\QueryWizard\Tests\App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SoftDeleteModel extends Model
{
    use SoftDeletes;

    protected $guarded = [];

    public $timestamps = false;
}
