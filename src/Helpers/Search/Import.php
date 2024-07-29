<?php

namespace Eduard\Search\Helpers\Search;

use Eduard\Search\Events\IndexationProccess;
use Eduard\Search\Models\Attributes;
use Eduard\Search\Models\AttributeSearch;
use Eduard\Search\Models\IndexCatalog;
use Exception;
use Eduard\Search\Models\IndexConfiguration;
use Eduard\Search\Models\Product;
use Eduard\Search\Models\ProductAttribute;
use Eduard\Search\Models\ProductIndex;
use Eduard\Account\Helpers\System\CoreHttp;
use Eduard\Search\Models\AccessIndex;
use Eduard\Search\Models\AttributesRulesExclude;
use Eduard\Account\Models\AutorizationToken;
use Eduard\Search\Models\ConditionsExcludes;
use Eduard\Search\Models\FiltersAttributes;
use Eduard\Search\Models\Media;
use Eduard\Search\Models\ProductMedia;
use Eduard\Search\Models\RankingSorting;
use Eduard\Search\Models\SortingType;
use Eduard\Search\Models\TypeAttribute;
use Illuminate\Support\Str;
use Eduard\Search\Helpers\Search\Core as CoreSearch;
use Eduard\Search\Models\IndexProducts;
use Illuminate\Support\Facades\Event;

class Import
{
    /**
     * @var IndexConfiguration|null
     */
    protected $indexConfiguration = null;

    /**
     * @var CoreHttp
     */
    public $coreHttp;

    /**
     * @var array
     */
    protected $productProccess = [];

    /**
     * @var CoreSearch
     */
    protected $coreSearch;

    public function __construct(CoreHttp $coreHttp, CoreSearch $coreSearch) {
        $this->coreHttp = $coreHttp;
        $this->coreSearch = $coreSearch;
    }

    /**
     * @inheritDoc
     */
    public function getAuthorizationClient($key)
    {
        return AutorizationToken::where("token", $key)->where("status", 1)->first();
    }

    /**
     * @inheritDoc
     */
    public function getCatalogConfigIndexByKey($key)
    {
        return IndexConfiguration::where("api_key", $key)->where("status", 1)->first();
    }

    /**
     * @inheritDoc
     */
    public function getCatalogConfigIndexByKeyAll($key)
    {
        return IndexConfiguration::where("api_key", $key)->first();
    }

    /**
     * @inheritDoc
     */
    public function verifyProductInIndex($idIndex, $idProduct)
    {
        return ProductIndex::where('id_index', $idIndex)->where('id_product', $idProduct)->exists();
    }

    /**
     * @inheritDoc
     */
    public function deleteValueProductAttribute($idIndex, $idProduct, $idAttribute)
    {
        return ProductAttribute::where('id_index', $idIndex)->where('id_product', $idProduct)->where('id_attribute', $idAttribute)->delete();
    }

    /**
     * @inheritDoc
     */
    public function verifyProductAttributeIndex($idIndex, $idProduct, $idAttribute)
    {
        return ProductAttribute::where('id_index', $idIndex)->where('id_product', $idProduct)->where('id_attribute', $idAttribute)->exists();
    }

    /**
     * @inheritDoc
     */
    public function getProductAttributeIndex($idIndex, $idProduct, $idAttribute)
    {
        return ProductAttribute::where('id_index', $idIndex)->where('id_product', $idProduct)->where('id_attribute', $idAttribute)->first();
    }

    /**
     * @inheritDoc
     */
    public function getAttributeByCode($code)
    {
        return Attributes::where('code', $code)->first();
    }

    /**
     * @inheritDoc
     */
    public function updateAttributes($attributes, $product, $idIndex)
    {
        foreach ($attributes as $attributeArray) {
            $attribute = $this->getAttributeByCode($attributeArray["code"]);

            if ($attribute) {
                $this->deleteValueProductAttribute($idIndex, $product->id, $attribute->id);
                $productAttribute = new ProductAttribute();
                $productAttribute->id_index = $idIndex;
                $productAttribute->id_product = $product->id;
                $productAttribute->id_attribute = $attribute->id;
                $productAttribute->value = $attributeArray["value"];
                $productAttribute->save();
            }
        }
    }

    /**
     * @inheritDoc
     */
    public function createProductIndex($newProduct, $idIndex)
    {
        if (!$this->verifyProductInIndex($idIndex, $newProduct->id)) {
            $newProductIndex = new ProductIndex();
            $newProductIndex->id_product = $newProduct->id;
            $newProductIndex->id_index = $idIndex;
            $newProductIndex->status = true;
            $newProductIndex->updated_at = date("Y-m-d H:i:s");
            $newProductIndex->save();
        }
    }

    /**
     * @inheritDoc
     */
    public function existeApiKey(string $apiKey)
    {
        return IndexConfiguration::where('api_key', $apiKey)->where('status', true)->exists();
    }

    /**
     * @inheritDoc
     */
    public function incrementIndexProductCount()
    {
        $indexCatalog = $this->indexConfiguration->indexCatalog;
        $indexCatalog->count_product = $indexCatalog->count_product + 1;
        $indexCatalog->save();
    }

    /**
     * @inheritDoc
     */
    public function singleProduct($params, $headers)
    {
        try {
            $this->coreHttp->validateApiKey($headers);
    
            if (!is_array($params) || !array_key_exists("sku", $params) || !array_key_exists("name", $params) || !array_key_exists("attributes", $params)) {
                throw new Exception("Formato incorrecto de consulta.");
            }
    
            if (!is_string($params["sku"])) {
                throw new Exception("El parametro que se esta pasando es incorrecto");
            }
    
            $this->indexConfiguration = $this->getCatalogConfigIndexByKey($headers["api-key"][0]);
    
            if (!$this->indexConfiguration) {
                throw new Exception("El api-key no esta asignado a un indice valido.");
            }

            $client = $this->indexConfiguration->indexCatalog->client;
            $this->importProduct($params, $client, $this->indexConfiguration->id_index_catalog);
            $this->createdIndexList($this->productProccess, $this->indexConfiguration->indexCatalog);
            $this->diabledProductList($this->productProccess, $this->indexConfiguration->indexCatalog);

            Event::dispatch(
                new IndexationProccess(
                    1,
                    $client->id,
                    $this->indexConfiguration->id_index_catalog
                )
            );

            return $this->coreHttp->constructResponse([], "Producto creado exitosamente.", 200, true);
        } catch (Exception $e) {
            return $this->coreHttp->constructResponse([], $e->getMessage(), 500, false);
        }
    }

    /**
     * @inheritDoc
     */
    public function proccessImportAttributeFilter($params, $headers)
    {
        try {
            if (!$this->coreHttp->validateTokenRequest($headers)) {
                throw new Exception("Token de acceso no válido.");
            }

            $token = $this->coreHttp->getTokenRequest($headers);
            $clientToken = $this->coreHttp->getClientToken($token);

            if(!$clientToken) {
                throw new Exception("La cuenta no es válida.");
            }

            $client = $clientToken->client;
            
            if (!is_array($params) || !array_key_exists("attributes", $params)) {
                throw new Exception("Formato incorrecto de consulta.");
            }
    
            if (!is_array($params["attributes"])) {
                throw new Exception("El parametro que se esta pasando es incorrecto");
            }

            if (array_key_exists("attributes", $params) ) {
                if (!is_array($params["attributes"])) {
                    throw new Exception("El parametro attributes no cumple con el formato requerido.");
                } else {
                    $this->importAttributesFilters($params["attributes"], $client);
                }
            };

            return $this->coreHttp->constructResponse([], "Proceso ejecutado exitosamente.", 200, true);
        } catch (Exception $e) {
            return $this->coreHttp->constructResponse([], $e->getMessage(), 500, false);
        }
    }

    /**
     * @inheritDoc
     */
    public function proccessImportAttributesRulesExclude($params, $headers)
    {
        try {
            if (!$this->coreHttp->validateTokenRequest($headers)) {
                throw new Exception("Token de acceso no válido.");
            }

            $token = $this->coreHttp->getTokenRequest($headers);
            $clientToken = $this->coreHttp->getClientToken($token);

            if(!$clientToken) {
                throw new Exception("La cuenta no es válida.");
            }

            $client = $clientToken->client;
            
            if (!is_array($params) || !array_key_exists("attributes", $params)) {
                throw new Exception("Formato incorrecto de consulta.");
            }
    
            if (!is_array($params["attributes"])) {
                throw new Exception("El parametro que se esta pasando es incorrecto");
            }

            if (array_key_exists("attributes", $params) ) {
                if (!is_array($params["attributes"])) {
                    throw new Exception("El parametro attributes no cumple con el formato requerido.");
                } else {
                    $this->importAttributesRulesExclude($params["attributes"], $client);
                }
            };

            return $this->coreHttp->constructResponse([], "Proceso ejecutado exitosamente.", 200, true);
        } catch (Exception $e) {
            return $this->coreHttp->constructResponse([], $e->getMessage(), 500, false);
        }
    }

    /**
     * @inheritDoc
     */
    public function proccessImportAttributesSearch($params, $headers)
    {
        try {
            if (!$this->coreHttp->validateTokenRequest($headers)) {
                throw new Exception("Token de acceso no válido.");
            }

            $token = $this->coreHttp->getTokenRequest($headers);
            $clientToken = $this->coreHttp->getClientToken($token);

            if(!$clientToken) {
                throw new Exception("La cuenta no es válida.");
            }

            $client = $clientToken->client;
            
            if (!is_array($params) || !array_key_exists("attributes", $params)) {
                throw new Exception("Formato incorrecto de consulta.");
            }
    
            if (!is_array($params["attributes"])) {
                throw new Exception("El parametro que se esta pasando es incorrecto");
            }

            if (array_key_exists("attributes", $params) ) {
                if (!is_array($params["attributes"])) {
                    throw new Exception("El parametro attributes no cumple con el formato requerido.");
                } else {
                    $this->importAttributesSearch($params["attributes"], $client);
                }
            };

            return $this->coreHttp->constructResponse([], "Proceso ejecutado exitosamente.", 200, true);
        } catch (Exception $e) {
            return $this->coreHttp->constructResponse([], $e->getMessage(), 500, false);
        }
    }

    /**
     * @inheritDoc
     */
    public function proccessImportAttributesOrder($params, $headers)
    {
        try {
            if (!$this->coreHttp->validateTokenRequest($headers)) {
                throw new Exception("Token de acceso no válido.");
            }

            $token = $this->coreHttp->getTokenRequest($headers);
            $clientToken = $this->coreHttp->getClientToken($token);

            if(!$clientToken) {
                throw new Exception("La cuenta no es válida.");
            }

            $client = $clientToken->client;
            
            if (!is_array($params) || !array_key_exists("attributes", $params)) {
                throw new Exception("Formato incorrecto de consulta.");
            }
    
            if (!is_array($params["attributes"])) {
                throw new Exception("El parametro que se esta pasando es incorrecto");
            }

            if (array_key_exists("attributes", $params) ) {
                if (!is_array($params["attributes"])) {
                    throw new Exception("El parametro attributes no cumple con el formato requerido.");
                } else {
                    $this->importAttributesOrders($params["attributes"], $client);
                }
            };

            return $this->coreHttp->constructResponse([], "Proceso ejecutado exitosamente.", 200, true);
        } catch (Exception $e) {
            return $this->coreHttp->constructResponse([], $e->getMessage(), 500, false);
        }
    }

    /**
     * @inheritDoc
     */
    public function proccessImportAttributes($params, $headers)
    {
        try {
            if (!$this->coreHttp->validateTokenRequest($headers)) {
                throw new Exception("Token de acceso no válido.");
            }

            $token = $this->coreHttp->getTokenRequest($headers);
            $clientToken = $this->coreHttp->getClientToken($token);

            if(!$clientToken) {
                throw new Exception("La cuenta no es válida.");
            }

            $client = $clientToken->client;
            
            if (!is_array($params) || !array_key_exists("attributes", $params)) {
                throw new Exception("Formato incorrecto de consulta.");
            }
    
            if (!is_array($params["attributes"])) {
                throw new Exception("El parametro que se esta pasando es incorrecto");
            }

            if (array_key_exists("attributes", $params) ) {
                if (!is_array($params["attributes"])) {
                    throw new Exception("El parametro attributes no cumple con el formato requerido.");
                } else {
                    $this->importAttributes($params["attributes"], $client);
                }
            };

            return $this->coreHttp->constructResponse([], "Proceso ejecutado exitosamente.", 200, true);
        } catch (Exception $e) {
            return $this->coreHttp->constructResponse([], $e->getMessage(), 500, false);
        }
    }

    /**
     * @inheritDoc
     */
    public function updateSearchIndex($params, $headers) {
        try {
            $this->coreHttp->validateApiKey($headers);
            $this->indexConfiguration = $this->getCatalogConfigIndexByKeyAll($headers["api-key"][0]);
    
            if (!$this->indexConfiguration) {
                throw new Exception("El api-key no esta asignado a un indice valido.");
            }

            $this->indexConfiguration->status = $params["status"] ?? false;
            $this->indexConfiguration->save();
            $name = $this->indexConfiguration->indexCatalog->name;

            return $this->coreHttp->constructResponse([], __("El index %1 ha sido modificádo.", $name), 200, true);
        } catch (Exception $e) {
            return $this->coreHttp->constructResponse([], $e->getMessage(), 500, false);
        }
    }

    /**
     * @inheritDoc
     */
    public function processIndexCatalog($params, $headers)
    {
        try {
            $this->coreHttp->validateApiKey($headers);
    
            if (!is_array($params) || !array_key_exists("sku", $params) || !array_key_exists("name", $params) || !array_key_exists("attributes", $params)) {
                throw new Exception("Formato incorrecto de consulta.");
            }
    
            if (!is_string($params["sku"])) {
                throw new Exception("El parametro que se esta pasando es incorrecto");
            }
    
            $this->indexConfiguration = $this->getCatalogConfigIndexByKey($headers["api-key"][0]);
    
            if (!$this->indexConfiguration) {
                throw new Exception("El api-key no esta asignado a un indice valido.");
            }

            $client = $this->indexConfiguration->indexCatalog->client;

            if (array_key_exists("index", $params) ) {
                if (!is_array($params["index"])) {
                    throw new Exception("El parametro index no cumple con el formato requerido.");
                } else {
                    $this->importIndexCatalog($params["index"], $client);
                }
            }

            return $this->coreHttp->constructResponse([], "Index importados correctamente.", 200, true);
        } catch (Exception $e) {
            return $this->coreHttp->constructResponse([], $e->getMessage(), 500, false);
        }
    }

    /**
     * @inheritDoc
     */
    public function collectionsProduct($params, $headers)
    {
        try {
            $this->coreHttp->validateApiKey($headers);
    
            if (!is_array($params) || !array_key_exists("products", $params) || !is_array($params["products"])) {
                throw new Exception("Formato incorrecto de consulta.");
            }
    
            $this->indexConfiguration = $this->getCatalogConfigIndexByKey($headers["api-key"][0]);
    
            if (!$this->indexConfiguration) {
                throw new Exception("El api-key no esta asignado a un indice valido.");
            }

            $client = $this->indexConfiguration->indexCatalog->client;
            $countBatch = 0;

            if (array_key_exists("products", $params) ) {
                if (!is_array($params["products"])) {
                    throw new Exception("El parametro products no cumple con el formato requerido.");
                } else {
                    $countBatch = count($params["products"]);
                    $this->importProducts($params["products"], $client, $this->indexConfiguration->id_index_catalog);
                }
            }

            $this->createdIndexList($this->productProccess, $this->indexConfiguration->indexCatalog);
            $this->diabledProductList($this->productProccess, $this->indexConfiguration->indexCatalog);

            Event::dispatch(
                new IndexationProccess(
                    $countBatch,
                    $client->id,
                    $this->indexConfiguration->id_index_catalog
                )
            );

            return $this->coreHttp->constructResponse([], "Productos creados exitosamente.", 200, true);
        } catch (Exception $e) {
            return $this->coreHttp->constructResponse([], $e->getMessage(), 500, false);
        }
    }

    /**
     * @inheritDoc
     */
    public function createdIndexList($productProccess, $index)
    {
        $attributesSearch = $this->coreSearch->getSearchAttributesByIndex($index);

        foreach ($productProccess as $productId) {
            $indexValues = [];

            foreach ($attributesSearch as $attributeSearchable) {
                $indexValues = array_merge(
                    $indexValues,
                    $this->coreSearch->getProductValueSearch(
                        $attributeSearchable->id_attribute,
                        $index->id,
                        $productId
                    )
                );
            }

            $indexValues = array_merge(
                $indexValues,
                $this->coreSearch->getProductInfoBasic($productId),
            );
            $this->deleteIndexProduct($index->id, $productId);
            $this->savedIndex($productId, $index->id, $indexValues);
        }
    }

    /**
     * @inheritDoc
     */
    public function diabledProductList($listValue, $index)
    {
        $allRulesExcludes = AttributesRulesExclude::all();

        foreach ($allRulesExcludes as $rule) {
            $idProductsDisabled = [];

            switch ($rule->id_condition) {
                case 1:
                    $idProductsDisabled = ProductAttribute::join('product_index', function ($join) use ($rule, $index) {
                        $join->on('product_attribute.id_product', '=', 'product_index.id_product')
                                ->on('product_attribute.id_index', '=', 'product_index.id_index');
                    })
                    ->where('product_attribute.id_attribute', $rule->id_attribute)
                    ->where('product_attribute.value', '>=', $rule->value)
                    ->where('product_attribute.id_index', $index->id)
                    ->where('product_index.status', 1)
                    ->whereIn('product_attribute.id_product', $listValue)
                    ->pluck('product_attribute.id_product')->toArray();
                case 2:
                    $idProductsDisabled = ProductAttribute::join('product_index', function ($join) use ($rule, $index) {
                        $join->on('product_attribute.id_product', '=', 'product_index.id_product')
                                ->on('product_attribute.id_index', '=', 'product_index.id_index');
                    })
                    ->where('product_attribute.id_attribute', $rule->id_attribute)
                    ->where('product_attribute.value', '>', $rule->value)
                    ->where('product_attribute.id_index', $index->id)
                    ->where('product_index.status', 1)
                    ->whereIn('product_attribute.id_product', $listValue)
                    ->pluck('product_attribute.id_product')->toArray();
                case 3:
                    $idProductsDisabled = ProductAttribute::join('product_index', function ($join) use ($rule, $index) {
                        $join->on('product_attribute.id_product', '=', 'product_index.id_product')
                                ->on('product_attribute.id_index', '=', 'product_index.id_index');
                    })
                    ->where('product_attribute.id_attribute', $rule->id_attribute)
                    ->where('product_attribute.value', '<=', $rule->value)
                    ->where('product_attribute.id_index', $index->id)
                    ->where('product_index.status', 1)
                    ->whereIn('product_attribute.id_product', $listValue)
                    ->pluck('product_attribute.id_product')->toArray();
                case 4:
                    $idProductsDisabled = ProductAttribute::join('product_index', function ($join) use ($rule, $index) {
                        $join->on('product_attribute.id_product', '=', 'product_index.id_product')
                                ->on('product_attribute.id_index', '=', 'product_index.id_index');
                    })
                    ->where('product_attribute.id_attribute', $rule->id_attribute)
                    ->where('product_attribute.value', '<', $rule->value)
                    ->where('product_attribute.id_index', $index->id)
                    ->where('product_index.status', 1)
                    ->whereIn('product_attribute.id_product', $listValue)
                    ->pluck('product_attribute.id_product')->toArray();
                case 5:
                    $idProductsDisabled = ProductAttribute::join('product_index', function ($join) use ($rule, $index) {
                        $join->on('product_attribute.id_product', '=', 'product_index.id_product')
                                ->on('product_attribute.id_index', '=', 'product_index.id_index');
                    })
                    ->where('product_attribute.id_attribute', $rule->id_attribute)
                    ->where('product_attribute.value', '=', $rule->value)
                    ->where('product_attribute.id_index', $index->id)
                    ->where('product_index.status', 1)
                    ->whereIn('product_attribute.id_product', $listValue)
                    ->pluck('product_attribute.id_product')->toArray();
            }

            ProductIndex::where('status', true)->whereIn('id_product', $idProductsDisabled)->update(['status' => false]);
            IndexProducts::where('status', true)->whereIn('id_product', $idProductsDisabled)->update(['status' => false]);
        }
    }

    /**
     * @inheritDoc
     */
    public function deleteIndexProduct($idIndex, $idProduct)
    {
        return IndexProducts::where('id_index_catalog', $idIndex)->where('id_product', $idProduct)->delete();
    }

    /**
     * @inheritDoc
     */
    public function savedIndex(int $idProduct, int $idIndex, array $listValue = [])
    {
        foreach ($listValue as $value) {
            try {
                $newIndexProducts = new IndexProducts();
                $newIndexProducts->id_product = $idProduct;
                $newIndexProducts->id_index_catalog = $idIndex;
                $newIndexProducts->value = $value;
                $newIndexProducts->status = 1;
                $newIndexProducts->created_at = date("Y-m-d H:i:s");
                $newIndexProducts->updated_at = null;
                $newIndexProducts->save();
            } catch (Exception $e) {
                return null;
            }
        }
    }

    /**
     * @inheritDoc
     */
    public function onlyCreateProduct($name, $sku, $idClient)
    {
        try {
            $newProduct = new Product();
            $newProduct->name = $name;
            $newProduct->sku = $sku;
            $newProduct->status = 1;
            $newProduct->id_client = $idClient;
            $newProduct->created_at = date("Y-m-d H:i:s");
            $newProduct->updated_at = null;
            $newProduct->save();
            return $newProduct;
        } catch (Exception $e) {
            return null;
        }
    }

    /**
     * @inheritDoc
     */
    public function updateProduct($name, $sku, $idClient, $idIndex)
    {
        $product = Product::where('sku', $sku)->where('id_client', $idClient)->first();

        if (!$product) {
            return null;
        }

        $product->name = $name;
        $product->status = true;
        $product->updated_at = date("Y-m-d H:i:s");
        $product->save();
        $this->updateProductIndex($product->id, $idIndex);

        return $product;
    }


    /**
     * @inheritDoc
     */
    public function updateProductIndex($idProduct, $idIndex)
    {
        ProductIndex::where('id_product', $idProduct)->where('id_index', $idIndex)->update(['status' => true, 'updated_at' => date("Y-m-d H:i:s")]);
    }

    /**
     * @return bool
     */
    public function existProduct($sku, $idClient)
    {
        return Product::where('sku', $sku)->where('id_client', $idClient)->exists();
    }

    /**
     * @inheritDoc
     */
    public function changeStatusIndexProduct($params, $headers)
    {
        try {
            $this->coreHttp->validateApiKey($headers);

            if (!is_array($params) || !array_key_exists("sku", $params)) {
                throw new Exception("Formato incorrecto de consulta.");
            }

            if (!is_string($params["sku"])) {
                throw new Exception("El parametro que se esta pasando es incorrecto");
            }

            $this->indexConfiguration = $this->getCatalogConfigIndexByKey($headers["api-key"][0]);

            if (!$this->indexConfiguration) {
                throw new Exception("El api-key no esta asignado a un indice valido.");
            }

            $product = Product::where("sku", $params["sku"])->first();

            if (!$product) {
                throw new Exception(__("El producto %1 no existe.", $params["sku"]));
            } else {
                $productIndex = ProductIndex::where("id_index", $this->indexConfiguration->id_index_catalog)->where("id_product", $product->id)->first();

                if ($productIndex != null) {
                    $productIndex->status = $params["status"] ?? false;
                    $productIndex->updated_at = date("Y-m-d H:i:s");
                    $productIndex->save();
                } else {
                    throw new Exception(__("El producto %1 no es de tu propiedad.", $params["sku"]));
                }
            }

            return $this->coreHttp->constructResponse([], "Producto procesado exitosamente.", 200, true);
        } catch (Exception $e) {
            return $this->coreHttp->constructResponse([], $e->getMessage(), 500, false);
        }
    }

    /**
     * @inheritDoc
     */
    public function deleteSingleProduct($params, $headers)
    {
        try {
            if (!$this->coreHttp->validateTokenRequest($headers)) {
                throw new Exception("Token de acceso no válido.");
            }

            $token = $this->coreHttp->getTokenRequest($headers);
            $clientToken = $this->coreHttp->getClientToken($token);

            if(!$clientToken) {
                throw new Exception("La cuenta no es válida.");
            }

            $client = $clientToken->client;
            $this->deleteProductAccount($client->id, $params["sku"]);

            return $this->coreHttp->constructResponse([], "Producto eliminado exitosamente.", 200, true);
        } catch (Exception $e) {
            return $this->coreHttp->constructResponse([], $e->getMessage(), 500, false);
        }
    }

    /**
     * @inheritDoc
     */
    public function deleteCollectionProduct($params, $headers)
    {
        try {
            if (!$this->coreHttp->validateTokenRequest($headers)) {
                throw new Exception("Token de acceso no válido.");
            }

            $token = $this->coreHttp->getTokenRequest($headers);
            $clientToken = $this->coreHttp->getClientToken($token);

            if(!$clientToken) {
                throw new Exception("La cuenta no es válida.");
            }

            $client = $clientToken->client;

            if (array_key_exists("products", $params) ) {
                if (!is_array($params["products"])) {
                    throw new Exception("El parametro products no cumple con el formato requerido.");
                } else {
                    foreach ($params["products"] as $key => $productData) {
                        $this->deleteProductAccount($client->id, $productData["sku"]);
                    }
                }
            }

            return $this->coreHttp->constructResponse([], "Productos eliminados exitosamente.", 200, true);
        } catch (Exception $e) {
            return $this->coreHttp->constructResponse([], $e->getMessage(), 500, false);
        }
    }

    /**
     * @inheritDoc
     */
    public function deleteProductAccount($clientId, $sku)
    {
        $product = $this->getProductByClient($clientId, $sku);

        if (!$product) {
            throw new Exception(__("El producto %1 no existe.", $sku));
        }

        ProductAttribute::where("id_product", $product->id)->delete();
        ProductIndex::where("id_product", $product->id)->delete();
        ProductMedia::where("id_product", $product->id)->delete();
        $product->delete();
    }

    /**
     * @inheritDoc
     */
    public function changeStatusIndexCollectionProduct($params, $headers)
    {
        try {
            $this->coreHttp->validateApiKey($headers);

            if (!is_array($params) || !array_key_exists("products", $params)) {
                throw new Exception("Formato incorrecto de consulta.");
            }

            $this->indexConfiguration = $this->getCatalogConfigIndexByKey($headers["api-key"][0]);

            if (!$this->indexConfiguration) {
                throw new Exception("El api-key no esta asignado a un indice valido.");
            }

            foreach ($params as $key => $productArray) {
                $product = Product::where("sku", $productArray["sku"])->first();
    
                if (!$product) {
                    throw new Exception(__("El producto %1 no existe.", $productArray["sku"]));
                } else {
                    $this->updateProductIndex($product->id, $this->indexConfiguration->id_index_catalog);
                }
            }

            return $this->coreHttp->constructResponse([], "Productos procesados exitosamente.", 200, true);
        } catch (Exception $e) {
            return $this->coreHttp->constructResponse([], $e->getMessage(), 500, false);
        }
    }

    /**
     * @inheritDoc
     */
    public function getProductByClient($clientId, $sku)
    {
        return Product::where("sku", $sku)->where('id_client' , $clientId)->first();
    }

    /**
     * @inheritDoc
     */
    public function changeStatusProduct($params, $headers)
    {
        try {
            if (!$this->coreHttp->validateTokenRequest($headers)) {
                throw new Exception("Token de acceso no válido.");
            }

            $token = $this->coreHttp->getTokenRequest($headers);
            $clientToken = $this->coreHttp->getClientToken($token);

            if(!$clientToken) {
                throw new Exception("La cuenta no es válida.");
            }

            $client = $clientToken->client;
            $product = $this->getProductByClient($client->id, $params["sku"]);

            if (!$product) {
                throw new Exception(__("El producto %1 no existe.", $params["sku"]));
            } else {
                $product->status = $params["status"] ?? false;
                $product->save();
            }

            return $this->coreHttp->constructResponse([], "Producto procesado exitosamente.", 200, true);
        } catch (Exception $e) {
            return $this->coreHttp->constructResponse([], $e->getMessage(), 500, false);
        }
    }

    /**
     * @inheritDoc
     */
    public function changeStatusCollectionProduct($params, $headers)
    {
        try {
            if (!$this->coreHttp->validateTokenRequest($headers)) {
                throw new Exception("Token de acceso no válido.");
            }

            $token = $this->coreHttp->getTokenRequest($headers);
            $clientToken = $this->coreHttp->getClientToken($token);

            if(!$clientToken) {
                throw new Exception("La cuenta no es válida.");
            }

            $client = $clientToken->client;

            foreach ($params as $key => $productArray) {
                $product = $this->getProductByClient($client->id, $productArray["sku"]);
    
                if (!$product) {
                    throw new Exception(__("El producto %1 no existe.", $productArray["sku"]));
                } else {
                    $product->status = $productArray["status"] ?? false;
                    $product->save();
                }
            }

            return $this->coreHttp->constructResponse([], "Productos procesados exitosamente.", 200, true);
        } catch (Exception $e) {
            return $this->coreHttp->constructResponse([], $e->getMessage(), 500, false);
        }
    }

    /**
     * @inheritDoc
     */
    public function createIndexCatalog($code, $name, $status, $client)
    {
        try {
            $newIndexCatalog = new IndexCatalog();
            $newIndexCatalog->code = $code;
            $newIndexCatalog->name = $name;
            $newIndexCatalog->last_indexing = null;
            $newIndexCatalog->count_product = 0;
            $newIndexCatalog->id_client = $client->id;
            $newIndexCatalog->created_at = date("Y-m-d H:i:s");
            $newIndexCatalog->updated_at = null;
            $newIndexCatalog->save();
            $this->createIndexCatalogConfig($newIndexCatalog->id, $status);
            $this->createAccessIndex($client->autorizationToken->id, $newIndexCatalog->id, $client->id);
            return $newIndexCatalog->id;
        } catch (Exception $th) {
            return null;
        }
    }

    /**
     * @return bool
     */
    public function existIndex($code, $idClient)
    {
        return IndexCatalog::where('code', $code)->where('id_client', $idClient)->exists();
    }

    /**
     * @inheritDoc
     */
    public function updateIndexCatalog($code, $name, $status, $client)
    {
        $index = IndexCatalog::where("code", $code)->where('id_client', $client->id)->first();

        if ($index) {
            $index->name = $name;
            $index->updated_at = date("Y-m-d H:i:s");
            $index->save();

            $indexConfiguration = IndexConfiguration::where('id_index_catalog', $index->id)->first();

            if (!$indexConfiguration) {
                $indexConfiguration->status = $status;
                $indexConfiguration->updated_at = date("Y-m-d H:i:s");
                $indexConfiguration->save();
            }
        }
    }

    /**
     * @inheritDoc
     */
    public function createAccessIndex($idAutorizationToken, $idIndex, $idClient)
    {
        try {
            $AccessIndex = new AccessIndex();
            $AccessIndex->id_autorization_token = $idAutorizationToken;
            $AccessIndex->id_index = $idIndex;
            $AccessIndex->id_client = $idClient;
            $AccessIndex->save();
            return $AccessIndex;
        } catch (Exception $th) {
            return null;
        }
    }

    /**
     * @inheritDoc
     */
    public function createIndexCatalogConfig($idCatalogIndex, $status = false)
    {
        try {
            $newIndexCatalog = new IndexConfiguration();
            $newIndexCatalog->id_index_catalog = $idCatalogIndex;
            $newIndexCatalog->limit_product_feed = 6;
            $newIndexCatalog->page_limit = 20;
            $newIndexCatalog->limit_pagination = 200;
            $newIndexCatalog->api_key = $this->generateToken();
            $newIndexCatalog->status = $status;
            $newIndexCatalog->created_at = date("Y-m-d H:i:s");
            $newIndexCatalog->updated_at = null;
            $newIndexCatalog->save();
            return $newIndexCatalog->id;
        } catch (Exception $th) {
            return null;
        }
    }

    /**
     * @inheritDoc
     */
    public function generateToken()
    {
        $token = "";

        do {
            $token = Str::random(32);
        } while ($this->isApiKeyValid($token));

        return $token;
    }

    /**
     * @return bool
     */
    public function isApiKeyValid($code)
    {
        return IndexConfiguration::where('api_key', $code)->exists();
    }

    /**
     * @inheritDoc
     */
    public function getTypeAttribute($type)
    {
        $typeAttribute = TypeAttribute::where("type", $type)->first();

        if (!$typeAttribute) {
            return null;
        }

        return $typeAttribute;
    }

    /**
     * @return bool
     */
    public function existAttribute($code, $idClient)
    {
        return Attributes::where('code', $code)->where('id_client', $idClient)->exists();
    }

    /**
     * @inheritDoc
     */
    public function createAttribute($name, $code, $label, $idType, $idClient)
    {
        try {
            $newAttributes = new Attributes();
            $newAttributes->name = $name;
            $newAttributes->code = $code;
            $newAttributes->label = $label;
            $newAttributes->id_type = $idType;
            $newAttributes->id_client = $idClient;
            $newAttributes->status = 1;
            $newAttributes->created_at = date("Y-m-d H:i:s");
            $newAttributes->updated_at = null;
            $newAttributes->save();
            return $newAttributes->id;
        } catch (Exception $th) {
            return null;
        }
    }

    /**
     * @inheritDoc
     */
    public function updateAttribute($name, $code, $label, $idType, $idClient)
    {
        $attribute = Attributes::where('code', $code)->where('id_client', $idClient)->first();

        if ($attribute) {
            $attribute->name = $name;
            $attribute->code = $code;
            $attribute->label = $label;
            $attribute->id_type = $idType;
            $attribute->id_client = $idClient;
            $attribute->status = 1;
            $attribute->updated_at = date("Y-m-d H:i:s");
            $attribute->save();
            return $attribute->id;
        }

        return null;
    }

    /**
     * @inheritDoc
     */
    public function importProducts($productsArray, $currentClient, $idIndex)
    {
        foreach ($productsArray as $product) {
            $this->importProduct($product, $currentClient, $idIndex);
        }
    }

    /**
     * @inheritDoc
     */
    public function importProduct($product, $currentClient, $idIndex)
    {
        if (
            (isset($product["name"]) && isset($product["sku"]) && isset($product["image"])) &&
            (is_string($product["name"]) && is_string($product["sku"]) && is_string($product["image"]))
        ) {
            if ($this->existProduct($product["sku"], $currentClient->id)) {
                $updateProduct = $this->updateProduct(
                    $product["name"],
                    $product["sku"],
                    $currentClient->id,
                    $idIndex
                );

                if ($updateProduct != null) {
                    $this->productProccess[] = $updateProduct->id;
                    $this->setProductMedia($updateProduct->id, $idIndex, $product["image"]);

                    if (isset($product["attributes"]) && is_array($product["attributes"])) {
                        $this->updateAttributes($product["attributes"], $updateProduct, $idIndex);
                    }
                }
            } else {
                $newProduct = $this->onlyCreateProduct(
                    $product["name"],
                    $product["sku"],
                    $currentClient->id
                );

                if ($newProduct != null) {
                    $this->productProccess[] = $newProduct->id;
                    $this->setProductMedia($newProduct->id, $idIndex, $product["image"]);
                    $this->createProductIndex($newProduct, $idIndex);

                    if (isset($product["attributes"]) && is_array($product["attributes"])) {
                        $this->updateAttributes($product["attributes"], $newProduct, $idIndex);
                    }

                    $this->incrementIndexProductCount();
                }
            }
        }
    }

    /**
     * @inheritDoc
     */
    public function setProductMedia($idProduct, $idIndex, $url)
    {
        $idMedia = $this->createMedia($url);

        if ($idMedia != null) {
            $this->deleteProductMedia($idProduct, $idIndex);
            $this->registerProductMedia($idProduct, $idIndex, $idMedia);
        }
    }

    /**
     * @inheritDoc
     */
    public function deleteProductMedia($idProduct, $idIndex)
    {
        return ProductMedia::where('id_product', $idProduct)->where('id_index', $idIndex)->first();
    }

    /**
     * @inheritDoc
     */
    public function registerProductMedia($idProduct, $idIndex, $idMedia)
    {
        $newItem = new ProductMedia();
        $newItem->id_product = $idProduct;
        $newItem->id_index = $idIndex;
        $newItem->id_media = $idMedia;
        $newItem->save();
        return $newItem->id;
    }

    /**
     * @inheritDoc
     */
    public function getMedia($url)
    {
        return Media::where('url', $url)->first();
    }

    /**
     * @inheritDoc
     */
    public function createMedia($url)
    {
        try {
            $media = $this->getMedia($url);

            if (!$media) {
                $newItem = new Media();
                $newItem->url = $url;
                $newItem->created_at = date("Y-m-d H:i:s");
                $newItem->updated_at = null;
                $newItem->save();
                return $newItem->id;
            } else {
                $media->url = $url;
                $media->updated_at = date("Y-m-d H:i:s");
                $media->save();
                return $media->id;
            }
        } catch (Exception $th) {
            return null;
        }
    }

    /**
     * @inheritDoc
     */
    public function getAttributeByCliente($code, $idClient)
    {
        return Attributes::where('code', $code)->where('id_client', $idClient)->where('status', 1)->first();
    }

    /**
     * @inheritDoc
     */
    public function getAttributeSortingByIndex($idAttribute, $idIndex)
    {
        return RankingSorting::where('id_attribute', $idAttribute)->where('id_index', $idIndex)->first();
    }

    /**
     * @inheritDoc
     */
    public function getAttributeSearchByIndex($idAttribute, $idIndex)
    {
        return AttributeSearch::where('id_attribute', $idAttribute)->where('id_index', $idIndex)->first();
    }

    /**
     * @inheritDoc
     */
    public function getAttributeRulesExcludeByClient($idAttribute, $idClient)
    {
        return AttributesRulesExclude::where('id_client', $idClient)->where('id_attribute', $idAttribute)->first();
    }

    /**
     * @inheritDoc
     */
    public function getSortingType($name)
    {
        return SortingType::where('name', $name)->first();
    }

    /**
     * @inheritDoc
     */
    public function getConditionExclude($code)
    {
        return ConditionsExcludes::where('code', $code)->first();
    }

    /**
     * @inheritDoc
     */
    public function importAttributesRulesExclude($attributes, $currentClient)
    {
        $attributesProccess = [];

        foreach ($attributes as $key => $attribute) {
            if (
                (isset($attribute["code"]) && isset($attribute["condition"]) && isset($attribute["value"])) &&
                (is_string($attribute["code"]) && is_string($attribute["condition"]) && is_string($attribute["value"]))
            ) {
                $attributeItem = $this->getAttributeByCliente($attribute["code"], $currentClient->id);

                if ($attributeItem != null) {

                    $attributeRuleExclude = $this->getAttributeRulesExcludeByClient($attributeItem->id, $currentClient->id);

                    if ($attributeRuleExclude == null) {
                        $condition = $this->getConditionExclude($attribute["condition"]);

                        if ($condition != null) {
                            $this->createRulesExclude(
                                $attributeItem->id,
                                $currentClient->id,
                                $condition->id,
                                $attribute["value"]
                            );
                        }
                    } else {
                        $condition = $this->getConditionExclude($attribute["condition"]);

                        if ($condition != null) {
                            $attributeRuleExclude->id_condition = $condition->id;
                            $attributeRuleExclude->value = $attribute["value"];
                            $attributeRuleExclude->save();
                        }
                    }

                    $attributesProccess[] = $attributeItem->id;
                }
            }
        }

        $this->disabledRulesExcludeNoProccess($attributesProccess, $currentClient->id);
    }

    /**
     * @inheritDoc
     */
    public function disabledRulesExcludeNoProccess($attributesProccess, $idClient)
    {
        AttributesRulesExclude::where('id_client', $idClient)->whereNotIn('id_attribute', $attributesProccess)->delete();
    }

    /**
     * @inheritDoc
     */
    public function importAttributesSearch($attributes, $currentClient)
    {
        $attributesProccess = [];

        foreach ($attributes as $key => $attribute) {
            if (
                (isset($attribute["code"]) && isset($attribute["sort"])) &&
                (is_string($attribute["code"]) && is_string($attribute["sort"]))
            ) {
                $attributeItem = $this->getAttributeByCliente($attribute["code"], $currentClient->id);

                if ($attributeItem != null) {

                    foreach ($currentClient->indexes as $key => $index) {
                        $sttributeSearch = $this->getAttributeSearchByIndex($attributeItem->id, $index->id);

                        if ($sttributeSearch == null) {
                            $this->createAttributeSearch(
                                $attributeItem->id,
                                $index->id,
                                $attribute["sort"]
                            );
                        } else {
                            $sttributeSearch->order = $attribute["sort"];
                            $sttributeSearch->save();
                        }
                    }

                    $attributesProccess[] = $attributeItem->id;
                }
            }
        }

        foreach ($currentClient->indexes as $key => $index) {
            $this->disabledSearchNoProccess($attributesProccess, $index->id);
        }
    }

    /**
     * @inheritDoc
     */
    public function disabledSearchNoProccess($attributesProccess, $idIndex)
    {
        AttributeSearch::where('id_index', $idIndex)->whereNotIn('id_attribute', $attributesProccess)->delete();
    }

    /**
     * @inheritDoc
     */
    public function importAttributesOrders($attributes, $currentClient)
    {
        $attributesProccess = [];

        foreach ($attributes as $key => $attribute) {
            if (
                (isset($attribute["code"]) && isset($attribute["sort"]) && isset($attribute["sorting_type"])) &&
                (is_string($attribute["code"]) && is_string($attribute["sort"]) && is_string($attribute["sorting_type"]))
            ) {
                $attributeItem = $this->getAttributeByCliente($attribute["code"], $currentClient->id);

                if ($attributeItem != null) {

                    foreach ($currentClient->indexes as $key => $index) {
                        $attributeSorting = $this->getAttributeSortingByIndex($attributeItem->id, $index->id);

                        if (!$attributeSorting) {
                            $sortingType = $this->getSortingType($attribute["sorting_type"]);

                            if ($sortingType != null) {
                                $this->createAttributeSorting(
                                    $attributeItem->id,
                                    $index->id,
                                    $sortingType->id, 
                                    $attribute["sort"]
                                );
                            }
                        } else {
                            $sortingType = $this->getSortingType($attribute["sorting_type"]);

                            if ($sortingType != null) {
                                $attributeSorting->order = $attribute["sort"];
                                $attributeSorting->id_sort_type = $sortingType->id;
                                $attributeSorting->save();
                            }
                        }
                    }

                    $attributesProccess[] = $attributeItem->id;
                }
            }
        }

        foreach ($currentClient->indexes as $key => $index) {
            $this->disabledOrderNoProccess($attributesProccess, $index->id);
        }
    }

    /**
     * @inheritDoc
     */
    public function disabledOrderNoProccess($attributesProccess, $idIndex)
    {
        RankingSorting::where('id_index', $idIndex)->whereNotIn('id_attribute', $attributesProccess)->delete();
    }

    /**
     * @inheritDoc
     */
    public function createRulesExclude($idAttribute, $idClient, $idCondition, $value)
    {
        try {
            $newItem = new AttributesRulesExclude();
            $newItem->id_client = $idClient;
            $newItem->id_attribute = $idAttribute;
            $newItem->id_condition = $idCondition;
            $newItem->value = $value;
            $newItem->created_at = date("Y-m-d H:i:s");
            $newItem->updated_at = null;
            $newItem->save();
            return $newItem->id;
        } catch (Exception $th) {
            return null;
        }
    }

    /**
     * @inheritDoc
     */
    public function createAttributeSearch($idAttribute, $idIndex, $order)
    {
        try {
            $newItem = new AttributeSearch();
            $newItem->id_attribute = $idAttribute;
            $newItem->id_index = $idIndex;
            $newItem->order = $order;
            $newItem->save();
            return $newItem->id;
        } catch (Exception $th) {
            return null;
        }
    }

    /**
     * @inheritDoc
     */
    public function createAttributeSorting($idAttribute, $idIndex, $sortId, $order)
    {
        try {
            $newItem = new RankingSorting();
            $newItem->id_attribute = $idAttribute;
            $newItem->id_index = $idIndex;
            $newItem->id_sort_type = $sortId;
            $newItem->order = $order;
            $newItem->save();
            return $newItem->id;
        } catch (Exception $th) {
            return null;
        }
    }

    /**
     * @inheritDoc
     */
    public function importAttributesFilters($attributes, $currentClient)
    {
        $attributesProccess = [];

        foreach ($attributes as $key => $attribute) {
            if (
                (isset($attribute["code"]) && isset($attribute["sort"])) &&
                (is_string($attribute["code"]) && is_string($attribute["sort"]))
            ) {
                $attribute = $this->getAttributeByCliente($attribute["code"], $currentClient->id);

                if ($attribute != null) {

                    $filterAttribute = $this->getAttributeFilter($currentClient->id, $attribute->id);

                    if ($filterAttribute == null) {
                        $this->createFilterAttribute($currentClient->id, $attribute->id, $attribute["sort"]);
                    } else {
                        $filterAttribute->status = true;
                        $filterAttribute->save();
                    }

                    $attributesProccess[] = $attribute->id;
                }
            }
        }

        $this->disabledFiltersNoProccess($attributesProccess, $currentClient->id);
    }

    /**
     * @inheritDoc
     */
    public function disabledFiltersNoProccess($attributesProccess, $idClient)
    {
        FiltersAttributes::where('id_client', $idClient)->whereNotIn('id_attribute', $attributesProccess)->update(['status' => 0]);
    }

    /**
     * @inheritDoc
     */
    public function createFilterAttribute($idClient, $idAttribute, $sort)
    {
        try {
            $newItem = new FiltersAttributes();
            $newItem->id_client = $idClient;
            $newItem->id_attribute = $idAttribute;
            $newItem->sort = $sort;
            $newItem->status = 1;
            $newItem->created_at = date("Y-m-d H:i:s");
            $newItem->updated_at = null;
            $newItem->save();
            return $newItem->id;
        } catch (Exception $th) {
            return null;
        }
    }

    /**
     * @inheritDoc
     */
    public function getAttributeFilter($idClient, $idAttribute)
    {
        return FiltersAttributes::where('id_client', $idClient)->where('id_attribute', $idAttribute)->first();
    }

    /**
     * @inheritDoc
     */
    public function importAttributes($attributes, $currentClient)
    {
        $attributesProccess = [];

        foreach ($attributes as $key => $attribute) {
            if (
                (isset($attribute["name"]) && isset($attribute["code"]) && isset($attribute["label"]) && array_key_exists("type", $attribute)) &&
                (is_string($attribute["name"]) && is_string($attribute["code"]) && is_string($attribute["label"]))
            ) {
                if ($attribute["type"] == "" || $attribute["type"] == null || empty($attribute["type"])) {
                    $attribute["type"] = "string";
                }

                $type = $this->getTypeAttribute($attribute["type"]);
                $idAttribute = null;

                if ($type != null) {
                    if ($this->existAttribute($attribute["code"], $currentClient->id)) {
                        $idAttribute = $this->updateAttribute(
                            $attribute["name"],
                            $attribute["code"],
                            $attribute["label"],
                            $type->id,
                            $currentClient->id
                        );
                    } else {
                        $idAttribute = $this->createAttribute(
                            $attribute["name"],
                            $attribute["code"],
                            $attribute["label"],
                            $type->id,
                            $currentClient->id
                        );
                    }
                }

                if ($idAttribute != null) {
                    $attributesProccess[] = $idAttribute;
                }
            }
        }

        $this->disabledAttributtesNoProccess($attributesProccess, $currentClient->id);
    }

    /**
     * @inheritDoc
     */
    public function disabledAttributtesNoProccess($attributesProccess, $idClient)
    {
        Attributes::where('id_client', $idClient)->whereNotIn('id', $attributesProccess)->update(['status' => 0]);
    }

    /**
     * @inheritDoc
     */
    public function importIndexCatalog($indexArray, $currentClient)
    {
        foreach ($indexArray as $key => $index) {
            if (
                (isset($index["code"]) && isset($index["name"])) &&
                (is_string($index["code"]) && is_string($index["name"]))
            ) {
                if ($this->existIndex($index["code"], $currentClient->id)) {
                    $this->updateIndexCatalog(
                        $index["code"],
                        $index["name"],
                        $index["status"] ?? false,
                        $currentClient
                    );
                } else {
                    $this->createIndexCatalog(
                        $index["code"],
                        $index["name"],
                        $index["status"] ?? false,
                        $currentClient
                    );
                }
            }
        }
    }
}
