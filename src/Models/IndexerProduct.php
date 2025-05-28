<?php

namespace Eduard\Search\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Eduard\Search\Models\IndexCatalog;
use Eduard\Search\Models\Product;

class IndexerProduct extends Model
{
    use HasFactory;

    protected $table = 'index_products';
    protected $fillable = ['id_product', 'id_index_catalog'];
    protected $hidden = ['created_at'];
    protected $primaryKey = 'id';
    public $incrementing = true;
    protected $keyType = 'integer';
    public $timestamps = false;

    /**
     * @inheritDoc
     */
    public function getProduct() {
        return $this->hasOne(Product::class, 'id', 'id_product');
    }

    /**
     * @inheritDoc
     */
    public function getIndex() {
        return $this->hasOne(IndexCatalog::class, 'id', 'id_index_catalog');
    }
}
