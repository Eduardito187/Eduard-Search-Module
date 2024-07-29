<?php

namespace Eduard\Search\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Eduard\Search\Models\Attributes;
use Eduard\Search\Models\IndexCatalog;

class AttributeSearch extends Model
{
    use HasFactory;

    protected $table = 'attribute_search';
    protected $fillable = ['id_attribute', 'id_index', 'order'];
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
        return $this->hasOne(IndexCatalog::class, 'id', 'id_index');
    }
}