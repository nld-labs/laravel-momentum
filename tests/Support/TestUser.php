<?php

namespace NLD\Momentum\Tests\Support;

use Illuminate\Foundation\Auth\User as Authenticatable;

class TestUser extends Authenticatable
{
    protected $table = 'users';

    protected $fillable = ['name', 'email'];
}
