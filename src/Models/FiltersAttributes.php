<?php

namespace Eduard\Search\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Eduard\Search\Models\Attributes;
use Eduard\Account\Models\Client;

class FiltersAttributes extends Model
{
    use HasFactory;

    protected $table = 'filters_attributes';
    protected $fillable = ['id_client', 'id_attribute', 'sort', 'status'];
    protected $hidden = ['created_at', 'updated_at'];
    public $incrementing = false;
    public $timestamps = false;

    /**
     * @inheritDoc
     */
    public function attribute() {
        return $this->hasOne(Attributes::class, 'id', 'id_attribute');
    }

    /**
     * @inheritDoc
     */
    public function indexCatalog() {
        return $this->hasOne(Client::class, 'id', 'id_client');
    }
}