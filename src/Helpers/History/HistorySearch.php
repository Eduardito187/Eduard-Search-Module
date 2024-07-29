<?php

namespace Eduard\Search\Helpers\History;

use Exception;
use Eduard\Search\Models\HistoryQuerySearch;

class HistorySearch
{
    public function __construct() {
        //
    }

    /**
     * @inheritDoc
     */
    public function saveQuerySearchHistory($idClient, $idIndex, $customerUuid, $query, $countItems, $timeExecution, $code)
    {
        try {
            $newHistoryQuerySearch = new HistoryQuerySearch();
            $newHistoryQuerySearch->id_client = $idClient;
            $newHistoryQuerySearch->id_index = $idIndex;
            $newHistoryQuerySearch->customer_uuid = $customerUuid;
            $newHistoryQuerySearch->query = $query;
            $newHistoryQuerySearch->count_items = $countItems;
            $newHistoryQuerySearch->time_execution = $timeExecution;
            $newHistoryQuerySearch->code = $code;
            $newHistoryQuerySearch->created_at = date("Y-m-d H:i:s");
            $newHistoryQuerySearch->updated_at = null;
            $newHistoryQuerySearch->save();
        } catch (Exception $e) {
            return null;
        }
    }
}