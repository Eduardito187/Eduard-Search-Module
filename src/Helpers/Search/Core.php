<?php

namespace Eduard\Search\Helpers\Search;

use Exception;
use Eduard\Search\Models\Product;
use Illuminate\Support\Facades\DB;
use Eduard\Search\Models\Attributes;
use Eduard\Search\Models\BackupQuery;
use Illuminate\Support\Facades\Event;
use Eduard\Search\Models\IndexCatalog;
use Eduard\Search\Models\ProductIndex;
use Eduard\Search\Models\IndexProducts;
use Illuminate\Support\Facades\Session;
use Eduard\Search\Events\SearchProccess;
use Eduard\Search\Models\RankingSorting;
use Eduard\Search\Models\AttributeSearch;
use Eduard\Search\Models\ProductAttribute;
use Eduard\Account\Helpers\System\CoreHttp;
use Eduard\Search\Models\FiltersAttributes;
use Eduard\Search\Models\IndexConfiguration;
use Eduard\Search\Models\AttributeFilterType;
use Eduard\Search\Models\ProductMedia;

class Core
{
    /**
     * @var IndexConfiguration|null
     */
    protected $indexConfiguration = null;

    /**
     * @var string|null
     */
    protected $currentValue;

    /**
     * @var CoreHttp
     */
    public $coreHttp;

    public function __construct(CoreHttp $coreHttp) {
        $this->coreHttp = $coreHttp;
    }

    /**
     * @param array $body
     * @param array $header
     */
    public function productFeed(array $body, array $header = [])
    {
        try {
            $this->coreHttp->validateApiKey($header);

            if (!is_array($body) || !isset($body["query"])) {
                throw new Exception("Parametro de busqueda no valido.");
            }

            $query = $body["query"];
            $filters = null;
            $index = $this->getIndexByApiKey($header["api-key"][0]);
            $customerUuid = $header["customer-uuid"][0];
            $limit_search = $body["limit_search"] ?? $this->indexConfiguration->limit_product_feed;
            $suggestions_limit = $body["suggestions_limit"] ?? 0;
            $history_limit = $body["history_limit"] ?? 0;

            if (isset($body["filters"])) {
                $filters = $body["filters"];
            }

            $attributesSearch = $this->getSearchAttributesByIndex($index);

            if (count($attributesSearch) == 0) {
                throw new Exception("El indice no cuenta con atributos para su busqueda.");
            }

            $idProductList = [];
            $backupQuery = $this->getBackupQuery($index->id, $customerUuid, $query, $idProductList, $filters);
            $responseProductIds = [];

            if ($backupQuery == null) {
                $idProductList = $this->searchInIndexProducts($index->id, $query);

                if (count($idProductList) > 0) {
                    $this->setBackupQuery($index->id, $customerUuid, $query, $idProductList, $filters);
                }

                $responseProductIds = array_slice($idProductList, 0, $limit_search);
            } else {
                $idProductList = json_decode($backupQuery->list_products);
                $responseProductIds = array_slice($idProductList, 0, $limit_search);
            }

            $responseProducts = $this->responseProducts($responseProductIds, $index);
            $searchTimeEnd = microtime(true);

            Event::dispatch(
                new SearchProccess(
                    $index->id_client,
                    $index->id,
                    $customerUuid,
                    $query,
                    count($responseProductIds),
                    (($searchTimeEnd - Session::get('start_time')) * 1000),
                    "feed_response",
                    json_encode($responseProductIds)
                )
            );

            $suggestionTimeStart = microtime(true);
            $suggestionResponse = $this->getSuggestionQuery($query, $suggestions_limit);
            $suggestionTimeEnd = microtime(true);
            $historyTimeStart = microtime(true);
            $historyResponse = $this->getBackupHistory($index->id, $customerUuid, $query, $history_limit);
            $historyTimeEnd = microtime(true);

            Event::dispatch(
                new SearchProccess(
                    $index->id_client,
                    $index->id,
                    $customerUuid,
                    $query,
                    count($suggestionResponse),
                    (($suggestionTimeEnd - $suggestionTimeStart) * 1000),
                    "suggestion_feed_response",
                    json_encode($suggestionResponse)
                )
            );

            Event::dispatch(
                new SearchProccess(
                    $index->id_client,
                    $index->id,
                    $customerUuid,
                    $query,
                    count($historyResponse),
                    (($historyTimeEnd - $historyTimeStart) * 1000),
                    "history_feed_response",
                    json_encode($historyResponse)
                )
            );

            return $this->coreHttp->constructResponse(
                [
                    "products" => $responseProducts,
                    "count" => count($responseProductIds),
                    "total" => count($idProductList),
                    "suggestion" => $suggestionResponse,
                    "history" => $historyResponse
                ],
                "Proceso ejecutado exitosamente.",
                200,
                true
            );
        } catch (Exception $e) {
            return $this->coreHttp->constructResponse([], $e->getMessage(), 500, false);
        }
    }

    /**
     * @param array $body
     * @param array $header
     */
    public function productResult(array $body, array $header = [])
    {
        try {
            $this->coreHttp->validateApiKey($header);
    
            if (!array_key_exists("customer-uuid", $header)) {
                throw new Exception("No existe un customer uuid.");
            }

            if (!is_array($body) || !isset($body["query"])) {
                throw new Exception("Parametro de busqueda no valido.");
            }

            $query = $body["query"];
            $filters = null;
            $pagination = 1;
            $index = $this->getIndexByApiKey($header["api-key"][0]);
            $customerUuid = $header["customer-uuid"][0];

            if (isset($body["pagination"])) {
                $pagination = $body["pagination"];
            }

            if (isset($body["filters"])) {
                $filters = $body["filters"];
            }

            $idProductList = [];
            $backupQuery = $this->getBackupQuery($index->id, $customerUuid, $query, $idProductList, $filters);
            $responseProductIds = [];

            if ($backupQuery == null) {
                $idProductList = $this->searchInIndexProducts($index->id, $query);

                if ($filters != null && count($filters) > 0) {
                    foreach ($filters as $key => $filter) {
                        if (isset($filter["code"]) && is_string($filter["code"])) {
                            $attribute = $this->getAttributeByCode($filter["code"]);
                            $typeFilter = $this->getTypeFilter($index->id_client, $attribute->id);

                            if ($attribute != null) {
                                if (isset($filter["value"]) && is_array($filter["value"]) && count($filter["value"]) > 0) {
                                    if ($typeFilter == "list") {
                                        $idProductList = $this->getProductFilterApply($attribute->id, $index->id, $idProductList, $filter["value"]);
                                    } else if ($typeFilter == "slider") {
                                        $idProductList = $this->getProductFilterApplyRange($attribute->id, $index->id, $idProductList, $filter["value"][0], $filter["value"][1]);
                                    }
                                }
                            }
                        }
                    }
                }

                if (count($idProductList) > 0) {
                    $this->setBackupQuery($index->id, $customerUuid, $query, $idProductList, $filters);
                }
        
                $responseProductIds = array_slice($idProductList, (($pagination - 1) * $this->indexConfiguration->page_limit), $this->indexConfiguration->page_limit);
            } else {
                $idProductList = json_decode($backupQuery->list_products);
                $responseProductIds = array_slice($idProductList, (($pagination - 1) * $this->indexConfiguration->page_limit), $this->indexConfiguration->page_limit);
            }

            $responseProducts = $this->responseProducts($responseProductIds, $index);
            $searchTimeEnd = microtime(true);

            Event::dispatch(
                new SearchProccess(
                    $index->id_client,
                    $index->id,
                    $customerUuid,
                    $query,
                    count($responseProductIds),
                    (($searchTimeEnd - Session::get('start_time')) * 1000),
                    "page_search_response",
                    json_encode($responseProductIds)
                )
            );
    
            return $this->coreHttp->constructResponse(
                [
                    "products" => $responseProducts,
                    "count" => count($responseProductIds),
                    "total" => count($idProductList),
                    "currentPage" => intval($pagination),
                    "pageTotal" => ceil(count($idProductList) / $this->indexConfiguration->page_limit)
                ],
                "Proceso ejecutado exitosamente.",
                200,
                true
            );
        } catch (Exception $e) {
            return $this->coreHttp->constructResponse([], $e->getMessage(), 500, false);
        }
    }

    /**
     * @inheritDoc
     */
    public function searchInIndexProducts($index, $query)
    {
        if (strlen($query) < 2) return [];

        if ($this->isValidCustomString($query)) {
            return IndexProducts::where('id_index_catalog', $index)
                ->where('value', 'like', "%$query%")->where('status', 1)->orderBy('index_priority', 'desc')
                ->pluck('id_product')->unique()->values()->toArray();
        } else {
            $queryTerms = array_filter(explode(' ', strtolower($query)),fn($term) => strlen($term) >= 3);
            $fulltextQuery = implode('* +',$queryTerms).'*';

            return IndexProducts::where('id_index_catalog', $index)->whereRaw("MATCH(value) AGAINST (? IN BOOLEAN MODE)", ["+$fulltextQuery"])
                ->where('status', 1)->orderBy('index_priority', 'desc')->pluck('id_product')->unique()->values()->toArray();
        }
    }

    /**
     * @inheritDoc
     */
    public function isValidCustomString($query)
    {
        if (!preg_match('/^[a-zA-Z0-9_-]+$/', $query)) {
            return false;
        }
    
        if (preg_match('/^[a-zA-Z]+$/', $query)) {
            return false;
        }
    
        return true;
    }

    /**
     * @inheritDoc
     */
    public function searchInIndexProductsTake($index, $query, $take = 1)
    {
        return IndexProducts::where('id_index_catalog', $index)->where('value', 'like', '%' . $query . '%')->where('status', 1)->orderBy('index_priority', 'desc')
        ->pluck('id_product')->unique()->values()->take($take)->toArray();
    }

    /**
     * @param array $body
     * @param array $header
     */
    public function getFiltersPage(array $body, array $header = [])
    {
        try {
            $this->coreHttp->validateApiKey($header);
    
            if (!array_key_exists("customer-uuid", $header)) {
                throw new Exception("No existe un customer uuid.");
            }

            if (!is_array($body) || !isset($body["query"])) {
                throw new Exception("Parametro de busqueda no valido.");
            }

            $query = $body["query"];
            $filters = null;
            $index = $this->getIndexByApiKey($header["api-key"][0]);

            if (isset($body["filters"])) {
                $filters = $body["filters"];
            }
    
            $attributesSearch = $this->getSearchAttributesByIndex($index);
    
            if (count($attributesSearch) == 0) {
                throw new Exception("El indice no cuenta con atributos para su busqueda.");
            }

            $idProductList = [];
            $backupQuery = $this->getBackupQuery($index->id, $header["customer-uuid"][0], $query, $idProductList, $filters);

            if ($backupQuery == null) {
                $this->productResult($body, $header);
                $backupQuery = $this->getBackupQuery($index->id, $header["customer-uuid"][0], $query, $idProductList, $filters);
            }

            $responseProductIds = json_decode($backupQuery->list_products ?? '[]');
            $filterOrder = $this->getFilterOrder($index->id_client);
            $filterResponse = [];

            foreach ($filterOrder as $key => $filter) {
                $filterData = $this->getValueAttributeFilter($filter->id_attribute, $index->id, $responseProductIds);

                if (count($filterData) > 0) {
                    $typeFilter = $this->getTypeFilter($index->id_client, $filter->id_attribute);
    
                    $filterResponse[] = [
                        "label" => $filter->attribute->label,
                        "code" => $filter->attribute->code,
                        "type" => $typeFilter,
                        "data" => $filterData
                    ];
                }
            }
    
            return $this->coreHttp->constructResponse(
                [
                    "filters" => $filterResponse,
                    "total" => count($filterResponse)
                ],
                "Proceso ejecutado exitosamente.",
                200,
                true
            );
        } catch (Exception $e) {
            return $this->coreHttp->constructResponse([], $e->getMessage(), 500, false);
        }
    }

    /**
     * @inheritDoc
     */
    public function getTypeFilter($idClient, $idAttribute)
    {
        $type = AttributeFilterType::where("id_client", $idClient)->where("id_attribute", $idAttribute)->first();

        if (!$type) {
            return "list";
        }

        return $type->type;
    }

    /**
     * @inheritDoc
     */
    public function getProductFilterApplyRange($idAttribute, $idIndex, $idProducts, $min, $max)
    {
        return ProductAttribute::where('id_attribute', $idAttribute)
            ->where('id_index', $idIndex)->whereBetween('value', [$min, $max])
            ->whereIn('id_product', $idProducts)->whereNotNull('value')->pluck('id_product')->toArray();
    }

    /**
     * @inheritDoc
     */
    public function getProductFilterApply($idAttribute, $idIndex, $idProducts, $valueList)
    {
        return ProductAttribute::where('id_attribute', $idAttribute)->where('id_index', $idIndex)->whereIn('value', $valueList)
            ->whereIn('id_product', $idProducts)->whereNotNull('value')->pluck('id_product')->toArray();
    }

    /**
     * @inheritDoc
     */
    public function getValueAttributeFilter($idAttribute, $idIndex, $idProducts)
    {
        return ProductAttribute::where('id_attribute', $idAttribute)->where('id_index', $idIndex)
            ->whereIn('id_product', $idProducts)->whereNotNull('value')
            ->distinct()->pluck('value')->toArray(); 
    }

    /**
     * @inheritDoc
     */
    public function getFilterOrder($idClient)
    {
        return FiltersAttributes::where("id_client", $idClient)->orderBy('sort')->get();
    }

    /**
     * @inheritDoc
     */
    public function getAttributeByCode($code)
    {
        return Attributes::where('code', $code)->first();
    }

    /**
     * @param array $productsId
     * @param IndexCatalog $index
     * @return array
     */
    public function responseProducts(array $productsId, IndexCatalog $index)
    {
        if (count($productsId) == 0) {
            return [];
        }

        $rankingSorting = $this->getRankingAttributesByIndex($index);
        $rankingSortable = [];
        $products = $this->getProductsById($productsId);

        foreach ($rankingSorting as $value) {
            $rankingSortable[$value] = [];
        }

        return $this->getValuesProduct($rankingSortable, $products, $index->id, $index->id_client);
    }

    /**
     * @inheritDoc
     */
    public function existInRulesExclude($idAttribute, $value, $allRules)
    {
        foreach ($allRules as $key => $rule) {
            if ($rule->id_attribute == $idAttribute) {
                switch ($rule->id_condition) {
                    case 1:
                        return $value >= $rule->value;
                    case 2:
                        return $value > $rule->value;
                    case 3:
                        return $value <= $rule->value;
                    case 4:
                        return $value < $rule->value;
                    case 5:
                        return $value == $rule->value;
                    default:
                        return false;
                }
            }
        }

        return false;
    }

    /**
     * @param array $rankingSortable
     * @param mixed $products
     * @param int $indexId
     * @param int $clientId
     * @return array
     */
    public function getValuesProduct(array $rankingSortable, mixed $products, int $indexId, int $clientId)
    {
        $itemsResponse = [];
        $numberFormat = [];
    	$arrayFormat = [];
        $allAttributes = $this->getAllAtributesIdEnabled($clientId);
        $price = Attributes::where('code', 'price')->where('id_client', $clientId)->first();
        $specialPrice = Attributes::where('code', 'special_price')->where('id_client', $clientId)->first();
        $numberFormat = [$price->id, $specialPrice->id];

        foreach ($products as $productData) {
            $valueAttribute = [];
            $productsAttributes = [];

            foreach ($allAttributes as $key => $idAttribute) {
                $valueAttribute = $this->getProductAttributeIndexValue($idAttribute, $productData->id, $indexId, $numberFormat, $arrayFormat);

                if ($valueAttribute !== null) {
                    $productsAttributes = array_merge($productsAttributes, $valueAttribute);

                    if (array_key_exists($idAttribute, $rankingSortable)) {
                        $productAttribute = $rankingSortable[$idAttribute];
                        $productAttribute[$productData->id] = $this->currentValue;
                        $rankingSortable[$idAttribute] = $productAttribute;
                    }
                }
            }

            if (isset($productsAttributes["price"])) {
                $itemsResponse[$productData->id] = array_merge(
                    array(
                        "name" => $productData->name,
                        "sku" => $productData->sku,
                        "image" => $this->getPicturesProduct($productData->id, $indexId)
                    ),
                    $productsAttributes
                );
            }
        }

        if (count($rankingSortable) > 0) {
            //Solo toma en cuenta el primer ranking sortable
            $keyAttributeSortable = array_key_first($rankingSortable);
            $attributeSortable = $this->getRatingSorting($keyAttributeSortable, $indexId);
            $rankingSortable = $rankingSortable[$keyAttributeSortable];
    
            if ($attributeSortable != null) {
                $sorting = $attributeSortable->sortingType->name;
    
                if ($sorting == "ASC") {
                    asort($rankingSortable);
                } else if ($sorting == "DESC") {
                    arsort($rankingSortable);
                }
            }
    
            $rankingSortable = array_keys($rankingSortable);
    
            uksort($itemsResponse, function ($a, $b) use ($rankingSortable) {
                return array_search($a, $rankingSortable) - array_search($b, $rankingSortable);
            });
        }

        return array_values($itemsResponse);
    }

    /**
     * @param int $idAttribute
     * @param int $idProduct
     * @param int $idIndex
     * @param array $numberFormat
     * @param array $arrayFormat
     * @return array|null
     */
    public function getProductAttributeIndexValue($idAttribute, $idProduct, $idIndex, $numberFormat, $arrayFormat)
    {
        $value = ProductAttribute::where("id_attribute", $idAttribute)->where("id_product", $idProduct)->where("id_index", $idIndex)->first();
        $this->currentValue = null;

        if (!$value ) {
            return null;
        }

        $this->currentValue = $value->value;

        if (in_array($idAttribute, $numberFormat)) {
            if ($value->value == null || $value->value <= 0 || $value->value == "0") {
                return null;
            }

            return array($value->attribute->code => number_format($value->value, 2));
        } else if (in_array($idAttribute, $arrayFormat)) {
            return array($value->attribute->code => json_decode($value->value, true));
        }

        return array($value->attribute->code => $value->value);
    }

    /**
     * @inheritDoc
     */
    public function getPicturesProduct($idProduct, $idIndex)
    {
        $productMedia = ProductMedia::where("id_product", $idProduct)->where("id_index", $idIndex)->first();

        if (!$productMedia) return null;

        return $productMedia->media->url;
    }

    /**
     * @param array $ids
     * @return Product[]
     */
    public function getProductsById(array $ids)
    {
        return Product::whereIn("id", $ids)->orderByRaw('FIELD(id, ' . implode(',', $ids) . ')')->get();
    }

    /**
     * @param string $apiKey
     * @return bool
     */
    public function existeApiKey(string $apiKey)
    {
        return IndexConfiguration::where('api_key', $apiKey)->where('status', true)->exists();
    }

    /**
     * @param string $apiKey
     * @return IndexCatalog|null
     */
    public function getIndexByApiKey(string $apiKey)
    {
        $indexConfiguration = IndexConfiguration::where('api_key', $apiKey)->where('status', true)->first();

        if (!$indexConfiguration) {
            throw new Exception("El ApiKey no esta asignado a un indice valido.");
        }

        $this->indexConfiguration = $indexConfiguration;
        return $indexConfiguration->indexCatalog;
    }

    /**
     * @param IndexCatalog $index
     * @return AttributeSearch[]
     */
    public function getSearchAttributesByIndex(IndexCatalog $index)
    {
        return AttributeSearch::where('id_index', $index->id)->orderBy('order', 'asc')->get();
    }

    /**
     * @param IndexCatalog $index
     * @return array
     */
    public function getRankingAttributesByIndex(IndexCatalog $index)
    {
        return RankingSorting::where('id_index', $index->id)->orderBy('order', 'asc')->pluck('id_attribute')->unique()->toArray();
    }

    /**
     * @param int $idAttribute
     * @param int $idIndex
     * @return RankingSorting
     */
    public function getRatingSorting($idAttribute, $idIndex)
    {
        return RankingSorting::where('id_index', $idIndex)->where('id_attribute', $idAttribute)->first();
    }

    /**
     * @return array
     */
    public function getAllAtributesIdEnabled($idClient)
    {
        return Attributes::where('status', true)->where('id_client', $idClient)->pluck('id')->unique()->toArray();
    }

    /**
     * @return array
     */
    public function getProductsIndexFilters($ids, $idIndex)
    {
        return ProductIndex::where('status', true)->where('id_index', $idIndex)->whereIn('id_product', $ids)->pluck('id_product')->unique()->toArray();
    }

    /**
     * @return array
     */
    public function getProductsFilters($ids)
    {
        return Product::where('status', true)->whereIn('id', $ids)->pluck('id')->unique()->toArray();
    }

    /**
     * @param int $idAttribute
     * @param int $idIndex
     * @param int $idProduct
     * @return array
     */
    public function getProductValueSearch(int $idAttribute, int $idIndex, int $idProduct)
    {
        return ProductAttribute::join('product', function ($join) use ($idAttribute, $idIndex) {
                $join->on('product_attribute.id_product', '=', 'product.id');
            })
            ->where('product_attribute.id_attribute', $idAttribute)
            ->where('product_attribute.id_index', $idIndex)
            ->where('product.status', 1)
            ->where('product_attribute.id_product', $idProduct)
            ->pluck('product_attribute.value')
            ->unique()
            ->toArray();
        
    }

    /**
     * @param int $idAttribute
     * @param int $idIndex
     * @param string $query
     * @param array $excludeIds
     * @return array
     */
    public function getProductsIdFilters(int $idAttribute, int $idIndex, string $query, array $excludeIds = [])
    {
        /*
        return ProductAttribute::where('id_attribute', $idAttribute)
            ->where('id_index', $idIndex)->where('value', 'like', '%'.$query.'%')
            ->whereNotIn('id_product', $excludeIds)->pluck('id_product')->unique()->toArray();
            */

        return ProductAttribute::join('product_index', function ($join) use ($idAttribute, $idIndex) {
                $join->on('product_attribute.id_product', '=', 'product_index.id_product')
                     ->on('product_attribute.id_index', '=', 'product_index.id_index');
            })
            ->where('product_attribute.id_attribute', $idAttribute)
            ->where('product_attribute.id_index', $idIndex)
            ->where('product_attribute.value', 'like', '%' . $query . '%')
            ->where('product_index.status', 1)
            ->whereNotIn('product_attribute.id_product', $excludeIds)
            ->pluck('product_attribute.id_product')
            ->unique()
            ->toArray();
        
    }

    /**
     * @param int $idProduct
     * @return array
     */
    public function getProductInfoBasic(int $idProduct)
    {
        $product = Product::find($idProduct);

        if ($product == null) {
            return [];
        }

        if ($product->status == false) {
            return [];
        }

        return [$product->sku, $product->name];
    }

    /**
     * @param int $idClient
     * @param string $parametter
     * @param array $excludeIds
     * @return array
     */
    public function getProductsLike(int $idClient, string $parametter, array $excludeIds = [])
    {
        return Product::where('id_client', $idClient)->where('status', 1)->whereNotIn('id', $excludeIds)->where(function ($query) use ($parametter) {
            $query->where('sku', 'like', '%'.$parametter.'%')
                  ->orWhere('name', 'like', '%'.$parametter.'%');
            })->pluck('id')->unique()->toArray();
    }

    /**
     * @inheritDoc
     */
    public function deleteBackupHistory($id)
    {
        DB::table('backup_query')->whereRaw("list_products LIKE CONCAT('%,', ?, ',%')", [$id])
        ->orWhereRaw("list_products LIKE CONCAT('[', ?, ',%')", [$id])->orWhereRaw("list_products LIKE CONCAT('%,', ?, ']')", [$id])
        ->orWhereRaw("list_products LIKE CONCAT('[', ?, ']')", [$id])->orWhereRaw("list_products = ?", [$id])->delete();
    }

    /**
     * @inheritDoc
     */
    public function getBackupQuery($idIndex, $customer, $query, $resultProducts, $filters)
    {
        //->where('filters', json_encode($filters))
        $backup = BackupQuery::where('id_index', $idIndex)->where('query', $query)->first();

        if ($backup != null) {
            return $backup;
        }

        return null;
    }

    /**
     * @inheritDoc
     */
    public function getBackupHistory($idIndex, $customer, $query, $history_limit)
    {
        return BackupQuery::where('id_index', $idIndex)->where('customer_uuid', $customer)->whereRaw("MATCH(query) AGAINST (? IN BOOLEAN MODE)", [$query])
            ->where('query', '!=', $query)->limit($history_limit)->pluck('query')->unique()->values()->toArray();
    }

    /**
     * @inheritDoc
     */
    public function setBackupQuery($idIndex, $customer, $query, $resultProducts, $filters)
    {
        $newItem = new BackupQuery();
        $newItem->id_index = $idIndex;
        $newItem->customer_uuid = $customer;
        $newItem->query = $query;
        $newItem->list_products = json_encode($resultProducts);
        $newItem->filters = json_encode($filters);
        $newItem->created_at = date('Y-m-d H:i:s');
        $newItem->save();
    }

    /**
     * @inheritDoc
     */
    public function getSuggestionQuery($query, $limite)
    {
        $tokensBusqueda = $this->tokenizar($query);

        if (empty($tokensBusqueda)) return [];

        try {
            return DB::table('product_vector_tokens')->select('token')->where(function ($query) use ($tokensBusqueda) {
                foreach ($tokensBusqueda as $word) {
                    $query->orWhere(function ($subQuery) use ($word) {
                        $subQuery->where('token', 'like', '%' . strtolower($word) . '%')
                                ->where('token', '!=', strtolower($word))
                                ->whereRaw("token REGEXP '[^0-9\\.]'")
                                ->whereRaw("CHAR_LENGTH(token) > 3");
                    });
                }
            })->distinct()->limit($limite)->pluck('token')->toArray();
        } catch (Exception $e) {
            return [];
        }
    }

    /**
     * @inheritDoc
     */
    public function vectorization($product)
    {
        $this->deleteBackupHistory($product->id);
        $texto = implode(' ', array_filter([
            $product->name,
            $product->sku
        ]));

        $tokens = $this->tokenizar($texto);
        $tf = $this->calcularTF($tokens);
        $idf = array_fill_keys(array_keys($tf), 1);
        $vector = $this->vectorizar($tf, $idf);
        DB::table('product_vector_tokens')->where('product_id', $product->id)->delete();

        $tokensInsert = [];
        foreach ($vector as $token => $relevance) {
            if (strlen($token) > 3) {
                $tokensInsert[] = [
                    'product_id' => $product->id,
                    'token' => strtolower($token),
                    'relevance' => floatval($relevance),
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
        }

        DB::table('product_vector_tokens')->insert($tokensInsert);
    }

    /**
     * @inheritDoc
     */
    private function tokenizar(string $texto): array
    {
        $texto = mb_strtolower($texto);
        $limpio = preg_replace('/[^\p{L}\p{N}]+/u', ' ', $texto);
        $limpio = preg_replace('/\s+/', ' ', $limpio);
        $limpio = trim($limpio);

        return explode(' ', $limpio);
    }

    /**
     * @inheritDoc
     */
    private function calcularTF(array $tokens): array
    {
        $frecuence = array_count_values($tokens);
        $total = count($tokens);

        foreach ($frecuence as &$valor) {
            $valor = $valor / $total;
        }

        return $frecuence;
    }

    /**
     * @inheritDoc
     */
    private function vectorizar(array $tf, array $idf): array
    {
        $vector = [];

        foreach ($idf as $key => $idfVal) {
            if (strlen($key) > 3 && $this->validVector($key)) {
                $vector[$key] = ($tf[$key] ?? 0) * $idfVal;
            }
        }

        return $vector;
    }

    /**
     * @inheritDoc
     */
    private function validVector($key)
    {
        if ($key === '') {
            return false;
        }

        $validateString = preg_match('/[a-zA-Z]/', $key);
        $validateNumber = preg_match('/[0-9]/', $key);

        return ($validateString xor $validateNumber);
    }
}
