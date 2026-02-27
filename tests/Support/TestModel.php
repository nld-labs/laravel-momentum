<?php

namespace NLD\Momentum\Tests\Support;

use Illuminate\Database\Eloquent\Model;

class TestModel extends Model
{
    protected $fillable = ['id', 'name'];

    public function scopeSearch($query, $search)
    {
        return $query->where('name', $search);
    }

    public function scopeCustomScope($query, $search)
    {
        return $query->where('search', $search);
    }

    public function items()
    {
        return $this->hasMany(TestModel::class, 'parent_id');
    }

    public function orderBySomeMethod($query, $desc)
    {
        return $query->orderBy('model_method', $desc ? 'desc' : 'asc');
    }
}
