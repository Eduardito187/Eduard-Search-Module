<?php

namespace Eduard\Search\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Eduard\Search\Models\Attributes;
use Eduard\Search\Models\IndexCatalog;
use Eduard\Search\Models\SortingType;

class RankingSorting extends Model
{
    use HasFactory;

    protected $table = 'ranking_sorting';
    protected $fillable = ['id_attribute', 'id_index', 'id_sort_type', 'order'];
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

    /**
     * @inheritDoc
     */
    public function sortingType() {
        return $this->hasOne(SortingType::class, 'id', 'id_sort_type');
    }
}