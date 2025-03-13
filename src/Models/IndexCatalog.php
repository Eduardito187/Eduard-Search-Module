<?php

namespace Eduard\Search\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Eduard\Search\Models\IndexConfiguration;
use Eduard\Search\Models\ProductIndex;
use Eduard\Account\Models\Client;

class IndexCatalog extends Model
{
    use HasFactory;

    protected $table = 'index_catalog';
    protected $fillable = ['code', 'name', 'last_indexing', 'count_product', 'id_client'];
    protected $hidden = ['created_at', 'updated_at'];
    protected $primaryKey = 'id';
    public $incrementing = true;
    protected $keyType = 'integer';
    public $timestamps = false;

    /**
     * @inheritDoc
     */
    public function indexConfiguration() {
        return $this->hasOne(IndexConfiguration::class, 'id_index_catalog', 'id');
    }

    /**
     * @inheritDoc
     */
    public function client() {
        return $this->hasOne(Client::class, 'id', 'id_client');
    }

    /**
     * @inheritDoc
     */
    public function productsIndex() {
        return $this->hasMany(ProductIndex::class, 'id_index', 'id_index');
    }

    /**
     * @inheritDoc
     */
    public function historyIndex() {
        return $this->hasMany(HistoryIndexProccess::class, 'id_index', 'id');
    }

    /**
     * @inheritDoc
     */
    public function recentMonthHistoryIndex() {
        return $this->hasMany(HistoryIndexProccess::class, 'id_index', 'id')->where('created_at', '>=', now()->subDays(30));
    }

    /**
     * @inheritDoc
     */
    public function historyQuerySearch() {
        return $this->hasMany(HistoryQuerySearch::class, 'id_index', 'id');
    }

    /**
     * @inheritDoc
     */
    public function recentMonthHistoryQuerySearch() {
        return $this->hasMany(HistoryQuerySearch::class, 'id_index', 'id')->whereIn('code', ['feed_response', 'page_search_response'])->where('created_at', '>=', now()->subDays(30));
    }

    /**
     * @inheritDoc
     */
    public function recentMonthHistoryQuerySearchFeed() {
        return $this->hasMany(HistoryQuerySearch::class, 'id_index', 'id')->where('code', 'feed_response')->where('created_at', '>=', now()->subDays(30));
    }

    /**
     * @inheritDoc
     */
    public function recentMonthHistoryQuerySearchPage() {
        return $this->hasMany(HistoryQuerySearch::class, 'id_index', 'id')->where('code', 'page_search_response')->where('created_at', '>=', now()->subDays(30));
    }

    /**
     * @inheritDoc
     */
    public function recentMonthHistoryQuerySearchSuggestion() {
        return $this->hasMany(HistoryQuerySearch::class, 'id_index', 'id')->where('code', 'suggestion_feed_response')->where('created_at', '>=', now()->subDays(30));
    }
}