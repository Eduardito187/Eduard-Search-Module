<?php

namespace Eduard\Search\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Eduard\Search\Models\IndexCatalog;
use Eduard\Account\Models\AutorizationToken;
use Eduard\Account\Models\Client;

class AccessIndex extends Model
{
    use HasFactory;

    protected $table = 'access_index';
    protected $fillable = ['id_autorization_token', 'id_index', 'id_client'];
    public $incrementing = false;
    public $timestamps = false;

    /**
     * @inheritDoc
     */
    public function client() {
        return $this->hasOne(Client::class, 'id', 'id_client');
    }

    /**
     * @inheritDoc
     */
    public function index() {
        return $this->hasOne(IndexCatalog::class, 'id', 'id_index');
    }

    /**
     * @inheritDoc
     */
    public function autorizationToken() {
        return $this->hasOne(AutorizationToken::class, 'id', 'id_autorization_token');
    }
}