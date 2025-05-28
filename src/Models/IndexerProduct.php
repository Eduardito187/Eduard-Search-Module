<?php

namespace Eduard\Search\Models;

use Eduard\Search\Models\Product;
use Eduard\Search\Models\IndexCatalog;
use Eduard\Search\Models\IndexProducts;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

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
    public function getProduct()
    {
        return $this->hasOne(Product::class, 'id', 'id_product');
    }

    /**
     * @inheritDoc
     */
    public function getIndex()
    {
        return $this->hasOne(IndexCatalog::class, 'id', 'id_index_catalog');
    }

    /**
     * @inheritDoc
     */
    public function indexes()
    {
        return IndexProducts::query()->whereColumn('id_product', 'index_products.id_product')->whereColumn('id_index_catalog', 'index_products.id_index_catalog');
    }
}
