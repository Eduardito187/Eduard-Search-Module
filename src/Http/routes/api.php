<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Eduard\Search\Http\Controllers\Api\Import\Process;
use Eduard\Search\Http\Controllers\Api\Search\Product;
use Eduard\Account\Http\Middleware\CustomValidateToken;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::middleware([CustomValidateToken::class])->group(function () {
    Route::controller(Product::class)->group(function() {
        Route::post('search/productFeed', 'searchProductFeed');
        Route::post('search/productResult', 'searchProductResult');
        Route::post('search/getFiltersPage', 'getFiltersPage');
    });

    Route::controller(Process::class)->group(function() {
        Route::post('import/importSingle', 'importSingle');
        Route::post('import/importCollection', 'importCollection');
        Route::post('import/importIndexSearch', 'importIndexSearch');
        Route::post('import/updateStatusIndexSearch', 'updateStatusIndexSearch');
        Route::post('import/importAttributesFilter', 'importAttributesFilter');
        Route::post('import/importAttributesOrder', 'importAttributesOrder');
        Route::post('import/importAttributesSearch', 'importAttributesSearch');
        Route::post('import/importAttributesRulesExclude', 'importAttributesRulesExclude');
        Route::post('import/importAttributes', 'importAttributes');
        Route::post('import/disableSingleProduct', 'disableSingleProduct');
        Route::post('import/disableCollectionProduct', 'disableCollectionProduct');
        Route::post('import/desactivateSingleProduct', 'desactivateSingleProduct');
        Route::post('import/desactivateCollectionProduct', 'desactivateCollectionProduct');
        Route::post('import/deleteSingleProduct', 'deleteSingleProduct');
        Route::post('import/deleteCollectionProduct', 'deleteCollectionProduct');
    });
});