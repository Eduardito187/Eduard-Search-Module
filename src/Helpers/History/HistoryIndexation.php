<?php

namespace Eduard\Search\Helpers\History;

use Exception;
use Eduard\Search\Models\HistoryIndexProccess;

class HistoryIndexation
{
    public function __construct() {
        //
    }

    /**
     * @inheritDoc
     */
    public function saveIndexationHistory($countItems, $idClient, $idIndex)
    {
        try {
            $newHistoryIndexProccess = new HistoryIndexProccess();
            $newHistoryIndexProccess->count = $countItems;
            $newHistoryIndexProccess->id_client = $idClient;
            $newHistoryIndexProccess->id_index = $idIndex;
            $newHistoryIndexProccess->created_at = date("Y-m-d H:i:s");
            $newHistoryIndexProccess->updated_at = null;
            $newHistoryIndexProccess->save();
        } catch (Exception $e) {
            return null;
        }
    }
}