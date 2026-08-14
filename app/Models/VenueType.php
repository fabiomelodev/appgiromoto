<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class VenueType extends Model
{
    use SoftDeletes;

    protected $guarded = ['id'];

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }
}
