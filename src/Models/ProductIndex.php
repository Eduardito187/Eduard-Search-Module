<?php

namespace Eduard\Search\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Eduard\Search\Models\Product;
use Eduard\Search\Models\IndexCatalog;

class ProductIndex extends Model
{
    use HasFactory;

    protected $table = 'product_index';
    protected $fillable = ['id_product', 'id_index', 'status', 'updated_at'];
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
    public function indexCatalog() {
        return $this->hasOne(IndexCatalog::class, 'id', 'id_index');
    }
}