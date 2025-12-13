<?php

namespace Jackardios\QueryWizard\Tests\App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Jackardios\QueryWizard\Tests\App\data\factories\SoftDeleteModelFactory;

class SoftDeleteModel extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected static function newFactory(): SoftDeleteModelFactory
    {
        return SoftDeleteModelFactory::new();
    }

    protected $guarded = [];

    public $timestamps = false;
}
