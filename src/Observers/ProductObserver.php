<?php

namespace Eduard\Search\Observers;

use Eduard\Search\Models\Product;
use Eduard\Search\Helpers\Search\Core as CoreSearch;

class ProductObserver
{
    /**
     * @var CoreSearch
     */
    public $coreSearch;

    public function __construct(CoreSearch $coreSearch) {
        $this->coreSearch = $coreSearch;
    }

    public function created(Product $product)
    {
        $this->coreSearch->vectorization($product);
    }

    public function updated(Product $product)
    {
        $this->coreSearch->vectorization($product);
    }
}
