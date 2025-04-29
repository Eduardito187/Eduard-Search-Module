<?php

namespace Eduard\Search\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Eduard\Search\Models\Product;

class ProductVectors extends Model
{
    use HasFactory;

    protected $table = 'product_vectors';
    protected $primaryKey = 'product_id';
    public $incrementing = false;
    protected $keyType = 'int';
    protected $fillable = ['product_id', 'vector'];
    public $timestamps = false;

    protected $casts = [
        'vector' => 'array'
    ];

    /**
     * @inheritDoc
     */
    public function product() {
        return $this->hasOne(Product::class, 'id', 'product_id');
    }
}
