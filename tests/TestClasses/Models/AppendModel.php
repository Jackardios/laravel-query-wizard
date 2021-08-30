<?php

namespace Jackardios\QueryWizard\Tests\TestClasses\Models;

use Illuminate\Database\Eloquent\Model;

class AppendModel extends Model
{
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