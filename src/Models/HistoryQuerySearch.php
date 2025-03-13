<?php

namespace Eduard\Search\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Eduard\Search\Models\IndexCatalog;
use Eduard\Account\Models\Client;

class HistoryQuerySearch extends Model
{
    use HasFactory;

    protected $table = 'history_query_search';
    protected $fillable = ['id_client', 'id_index', 'customer_uuid', 'query', 'time_execution', 'code', 'count_items'];
    protected $hidden = ['created_at', 'updated_at'];
    protected $primaryKey = 'id';
    public $incrementing = true;
    protected $keyType = 'integer';
    public $timestamps = false;

    /**
     * @inheritDoc
     */
    public function getClient() {
        return $this->hasOne(Client::class, 'id', 'id_client');
    }

    /**
     * @inheritDoc
     */
    public function getIndex() {
        return $this->hasOne(IndexCatalog::class, 'id', 'id_index');
    }
}