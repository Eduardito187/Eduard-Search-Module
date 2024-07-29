<?php

namespace Eduard\Search\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Eduard\Search\Models\Product;
use Eduard\Search\Models\Media;
use Eduard\Search\Models\IndexCatalog;

class ProductMedia extends Model
{
    use HasFactory;

    protected $table = 'product_media';
    protected $fillable = ['id_product', 'id_media', 'id_index'];
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
    public function media() {
        return $this->hasOne(Media::class, 'id', 'id_media');
    }

    /**
     * @inheritDoc
     */
    public function indexCatalog() {
        return $this->hasOne(IndexCatalog::class, 'id', 'id_index');
    }
}