<?php

namespace Eduard\Search\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Eduard\Search\Models\Product;
use Eduard\Search\Models\Attributes;
use Eduard\Search\Models\IndexCatalog;

class ProductAttribute extends Model
{
    use HasFactory;

    protected $table = 'product_attribute';
    protected $fillable = ['id_product', 'id_attribute', 'id_index', 'value'];
    public $incrementing = false;
    public $timestamps = false;

    /**
     * @inheritDoc
     */
    public function product() {
        return $this->hasOne(Product::class, 'id', 'id_product');
    }

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