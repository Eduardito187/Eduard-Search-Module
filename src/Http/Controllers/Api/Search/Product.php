<?php

namespace Eduard\Search\Http\Controllers\Api\Search;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Eduard\Account\Helpers\System\CoreHttp;
use Eduard\Search\Helpers\Search\Core;

class Product extends Controller
{
    /**
     * @var Core
     */
    protected $core;

    /**
     * @var CoreHttp
     */
    protected $coreHttp;

    public function __construct(Core $core, CoreHttp $coreHttp) {
        $this->core = $core;
        $this->coreHttp = $coreHttp;
    }

    /**
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\Response
     */
    public function searchProductFeed(Request $request)
    {
        return response()->json(
            $this->core->productFeed(
                $request->all(),
                $request->header()
            )
        );
    }

    /**
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\Response
     */
    public function searchProductResult(Request $request)
    {
        return response()->json(
            $this->core->productResult(
                $request->all(),
                $request->header()
            )
        );
    }

    /**
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\Response
     */
    public function getFiltersPage(Request $request)
    {
        return response()->json(
            $this->core->getFiltersPage(
                $request->all(),
                $request->header()
            )
        );
    }
}
