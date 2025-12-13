<?php

namespace Jackardios\QueryWizard\Tests\App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Jackardios\QueryWizard\Tests\App\data\factories\AppendModelFactory;

class AppendModel extends Model
{
    use HasFactory;

    protected static function newFactory(): AppendModelFactory
    {
        return AppendModelFactory::new();
    }
    protected $guarded = [];

    public $timestamps = false;

    public function getFullnameAttribute(): string
    {
        return $this->firstname.' '.$this->lastname;
    }

    public function getReversenameAttribute(): string
    {
        return $this->lastname.' '.$this->firstname;
    }
}
