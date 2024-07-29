<?php

namespace Eduard\Search\Http\Controllers\Api\Import;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Eduard\Account\Helpers\System\CoreHttp;
use Eduard\Search\Helpers\Search\Import;

class Process extends Controller
{
    /**
     * @var Import
     */
    protected $import;

    /**
     * @var CoreHttp
     */
    protected $coreHttp;

    public function __construct(Import $import, CoreHttp $coreHttp) {
        $this->import = $import;
        $this->coreHttp = $coreHttp;
    }

    /**
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\Response
     */
    public function importSingle(Request $request)
    {
        return response()->json(
            $this->import->singleProduct(
                $request->all(),
                $request->header()
            )
        );
    }

    /**
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\Response
     */
    public function importCollection(Request $request)
    {
        return response()->json(
            $this->import->collectionsProduct(
                $request->all(),
                $request->header()
            )
        );
    }

    /**
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\Response
     */
    public function importIndexSearch(Request $request)
    {
        return response()->json(
            $this->import->processIndexCatalog(
                $request->all(),
                $request->header()
            )
        );
    }

    /**
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\Response
     */
    public function updateStatusIndexSearch(Request $request)
    {
        return response()->json(
            $this->import->updateSearchIndex(
                $request->all(),
                $request->header()
            )
        );
    }

    /**
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\Response
     */
    public function importAttributesFilter(Request $request)
    {
        return response()->json(
            $this->import->proccessImportAttributeFilter(
                $request->all(),
                $request->header()
            )
        );
    }

    /**
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\Response
     */
    public function importAttributes(Request $request)
    {
        return response()->json(
            $this->import->proccessImportAttributes(
                $request->all(),
                $request->header()
            )
        );
    }

    /**
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\Response
     */
    public function importAttributesOrder(Request $request)
    {
        return response()->json(
            $this->import->proccessImportAttributesOrder(
                $request->all(),
                $request->header()
            )
        );
    }

    /**
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\Response
     */
    public function importAttributesSearch(Request $request)
    {
        return response()->json(
            $this->import->proccessImportAttributesSearch(
                $request->all(),
                $request->header()
            )
        );
    }

    /**
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\Response
     */
    public function importAttributesRulesExclude(Request $request)
    {
        return response()->json(
            $this->import->proccessImportAttributesRulesExclude(
                $request->all(),
                $request->header()
            )
        );
    }

    /**
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\Response
     */
    public function disableSingleProduct(Request $request)
    {
        return response()->json(
            $this->import->changeStatusProduct(
                $request->all(),
                $request->header()
            )
        );
    }

    /**
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\Response
     */
    public function disableCollectionProduct(Request $request)
    {
        return response()->json(
            $this->import->changeStatusCollectionProduct(
                $request->all(),
                $request->header()
            )
        );
    }

    /**
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\Response
     */
    public function desactivateSingleProduct(Request $request)
    {
        return response()->json(
            $this->import->changeStatusIndexProduct(
                $request->all(),
                $request->header()
            )
        );
    }

    /**
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\Response
     */
    public function desactivateCollectionProduct(Request $request)
    {
        return response()->json(
            $this->import->changeStatusIndexCollectionProduct(
                $request->all(),
                $request->header()
            )
        );
    }

    /**
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\Response
     */
    public function deleteSingleProduct(Request $request)
    {
        return response()->json(
            $this->import->deleteSingleProduct(
                $request->all(),
                $request->header()
            )
        );
    }

    /**
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\Response
     */
    public function deleteCollectionProduct(Request $request)
    {
        return response()->json(
            $this->import->deleteCollectionProduct(
                $request->all(),
                $request->header()
            )
        );
    }
}
