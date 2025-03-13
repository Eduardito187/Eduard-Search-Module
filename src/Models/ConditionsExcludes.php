<?php

namespace Eduard\Search\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ConditionsExcludes extends Model
{
    use HasFactory;

    protected $table = 'conditions_excludes';
    protected $fillable = ['name', 'code', 'status'];
    protected $hidden = ['created_at', 'updated_at'];
    protected $primaryKey = 'id';
    public $incrementing = true;
    protected $keyType = 'integer';
    public $timestamps = false;
}