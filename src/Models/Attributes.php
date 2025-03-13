<?php

namespace Eduard\Search\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Eduard\Search\Models\TypeAttribute;
use Eduard\Account\Models\Client;

class Attributes extends Model
{
    use HasFactory;

    protected $table = 'attributes';
    protected $fillable = ['name', 'code', 'label', 'id_type', 'id_client', 'status'];
    protected $hidden = ['created_at', 'updated_at'];
    protected $primaryKey = 'id';
    public $incrementing = true;
    protected $keyType = 'integer';
    public $timestamps = false;

    /**
     * @inheritDoc
     */
    public function typeAttribute() {
        return $this->hasOne(TypeAttribute::class, 'id_type', 'id');
    }

    /**
     * @inheritDoc
     */
    public function client() {
        return $this->hasOne(Client::class, 'id_client', 'id');
    }
}