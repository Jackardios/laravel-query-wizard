<?php

namespace Jackardios\QueryWizard\Tests\App\Models;

class AppendModelWithBuiltInAppends extends AppendModel
{
    protected $table = 'append_models';

    protected $appends = ['fullname'];
}
