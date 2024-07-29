<?php

namespace Eduard\Search\Helpers\Search;

use Exception;
use Eduard\Search\Events\SearchProccess;
use Eduard\Search\Models\Attributes;
use Eduard\Search\Models\AttributeSearch;
use Eduard\Search\Models\IndexConfiguration;
use Eduard\Search\Models\IndexCatalog;
use Eduard\Search\Models\Product;
use Eduard\Search\Models\ProductAttribute;
use Eduard\Search\Models\RankingSorting;
use Eduard\Account\Helpers\System\CoreHttp;
use Eduard\Search\Models\AttributeFilterType;
use Eduard\Search\Models\BackupQuery;
use Eduard\Search\Models\FiltersAttributes;
use Eduard\Search\Models\HistoryCustomer;
use Eduard\Search\Models\IndexProducts;
use Eduard\Search\Models\ProductIndex;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Session;

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

            if ($index->count_product == 0) {
                throw new Exception("El indice no cuenta con productos disponible para su busqueda.");
            }

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
                $idProductList = $this->searchInIndexProductsTake($index->id, $query, 6);

                if (count($idProductList) > 0) {
                    $this->setBackupQuery($index->id, $customerUuid, $query, $idProductList, $filters);
                }

                $responseProductIds = array_slice($idProductList, 0, $this->indexConfiguration->limit_product_feed);

                if (count($responseProductIds) > 0) {
                    $this->setHistoryResult($index->id, $customerUuid, $query, $responseProductIds);
                }
            } else {
                $idProductList = json_decode($backupQuery->list_products);
                $responseProductIds = array_slice($idProductList, 0, $this->indexConfiguration->limit_product_feed);
            }

            $responseProducts = $this->responseProducts($responseProductIds, $index, true);
            $searchTimeEnd = microtime(true);

            Event::dispatch(
                new SearchProccess(
                    $index->id_client,
                    $index->id,
                    $customerUuid,
                    $query,
                    count($idProductList),
                    (($searchTimeEnd - Session::get('start_time')) * 1000),
                    "feed_response"
                )
            );

            $suggestionTimeStart = microtime(true);
            $suggestionResponse = $this->getSuggestionQuery($index->id, $customerUuid, $query);
            $suggestionTimeEnd = microtime(true);

            Event::dispatch(
                new SearchProccess(
                    $index->id_client,
                    $index->id,
                    $customerUuid,
                    $query,
                    count($suggestionResponse),
                    (($suggestionTimeEnd - $suggestionTimeStart) * 1000),
                    "suggestion_feed_response"
                )
            );

            return $this->coreHttp->constructResponse(
                [
                    "products" => $responseProducts,
                    "count" => count($responseProductIds),
                    "total" => count($idProductList),
                    "suggestion" => $suggestionResponse
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
    
            if ($index->count_product == 0) {
                throw new Exception("El indice no cuenta con productos disponible para su busqueda.");
            }

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
    
                if (count($responseProductIds) > 0) {
                    $this->setHistoryResult($index->id, $customerUuid, $query, $responseProductIds);
                }
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
                    count($idProductList),
                    (($searchTimeEnd - Session::get('start_time')) * 1000),
                    "page_search_response"
                )
            );
    
            return $this->coreHttp->constructResponse(
                [
                    "products" => $responseProducts,
                    "count" => count($responseProductIds),
                    "total" => count($idProductList)
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
        return IndexProducts::where('id_index_catalog', $index)->where('value', 'like', '%' . $query . '%')->where('status', 1)
        ->pluck('id_product')->unique()->values()->toArray();
    }

    /**
     * @inheritDoc
     */
    public function searchInIndexProductsTake($index, $query, $take = 1)
    {
        return IndexProducts::where('id_index_catalog', $index)->where('value', 'like', '%' . $query . '%')->where('status', 1)
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
    
            if ($index->count_product == 0) {
                throw new Exception("El indice no cuenta con productos disponible para su busqueda.");
            }

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
     * @param bool $fead
     * @return array
     */
    public function responseProducts(array $productsId, IndexCatalog $index, bool $fead = false)
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

        return $this->getValuesProduct($rankingSortable, $products, $index->id, $index->id_client, $fead);
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
     * @param bool $feed
     * @return array
     */
    public function getValuesProduct(array $rankingSortable, mixed $products, int $indexId, int $clientId, bool $feed = false)
    {
        $itemsResponse = [];
        $numberFormat = [];
    	$arrayFormat = [];
        $productsAttributes = [];
        $allAttributes = $this->getAllAtributesIdEnabled();

        if ($feed) {
            $price = Attributes::where('code', 'price')->where('id_client', $clientId)->first();
            $specialPrice = Attributes::where('code', 'special_price')->where('id_client', $clientId)->first();
            $cuotaInicial = Attributes::where('code', 'cuota_inicial')->where('id_client', $clientId)->first();
            $cuotaMonto3 = Attributes::where('code', 'minicuota_monto_tres')->where('id_client', $clientId)->first();
            $numberFormat = [$price->id, $specialPrice->id, $cuotaInicial->id, $cuotaMonto3->id];
            $miniCuotaTotal = Attributes::where('code', 'minicuota_cuota_total')->where('id_client', $clientId)->first();
            $arrayFormat = [$miniCuotaTotal->id];
        }

        foreach ($products as $productData) {
            $valueAttribute = [];

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
                        "image" => $this->getPicturesProduct($productData)
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
            if ($value->value == null || $value->value <= 0) {
                return null;
            }

            return array($value->attribute->code => number_format($value->value, 2));
        } else if (in_array($idAttribute, $arrayFormat)) {
            return array($value->attribute->code => json_decode($value->value, true));
        }

        return array($value->attribute->code => $value->value);
    }

    /**
     * @param Product $product
     * @return string|null
     */
    public function getPicturesProduct(Product $product)
    {
        foreach ($product->productMedia as $productMedia) {
            return $productMedia->media->url;
        }

        return null;
    }

    /**
     * @param array $ids
     * @return Product[]
     */
    public function getProductsById(array $ids)
    {
        return Product::whereIn("id", $ids)->get();
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
    public function getAllAtributesIdEnabled()
    {
        return Attributes::where('status', true)->pluck('id')->unique()->toArray();
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
    public function getBackupQuery($idIndex, $customer, $query, $resultProducts, $filters)
    {
        $backup = BackupQuery::where('id_index', $idIndex)->where('customer_uuid', $customer)->where('query', $query)
            ->where('filters', json_encode($filters))->first();

        if (!$backup) {
            return null;
        }

        return $backup;
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
    public function setHistoryResult($idIndex, $customer, $query, $resultProducts)
    {
        $HistoryCustomer = new HistoryCustomer();
        $HistoryCustomer->id_index = $idIndex;
        $HistoryCustomer->customer_uuid = $customer;
        $HistoryCustomer->query = $query;
        $HistoryCustomer->count_result = count($resultProducts);
        $HistoryCustomer->created_at = date('Y-m-d H:i:s');
        $HistoryCustomer->save();
    }

    /**
     * @inheritDoc
     */
    public function getSuggestionQuery($index, $customer, $query)
    {
        return HistoryCustomer::where('customer_uuid', $customer)
        ->where('id_index', $index)->where('query', 'like', '%' . $query . '%')->where('query', '!=', $query)
        ->pluck('query')->unique()->values()->take(6)->toArray();
    }
}
