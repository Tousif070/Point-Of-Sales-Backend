<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

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

// Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
//     return $request->user();
// });


Route::group(['namespace' => 'App\Http\Controllers'], function() {

    Route::post('login', 'LoginController@login');

    Route::group(['middleware' => ['auth:sanctum']], function() {

        Route::post('logout', 'LoginController@logout');


        Route::group(['prefix' => 'user'], function() {

            Route::get('index-official', 'UserController@indexOfficial');

            Route::get('index-customer', 'UserController@indexCustomer');

            Route::get('index-supplier', 'UserController@indexSupplier');
            
            Route::post('register-official', 'UserController@registerOfficial');

            Route::post('register-customer', 'UserController@registerCustomer');

            Route::post('register-supplier', 'UserController@registerSupplier');

            Route::post('assign-role', 'UserController@assignRole');

            Route::get('has-permission', 'UserController@hasPermission');

            Route::get('get-permissions', 'UserController@getPermissions');

            Route::get('get-roles', 'UserController@getRoles');

        });


        Route::group(['prefix' => 'role'], function() {

            Route::get('index', 'RoleController@index');
            
            Route::post('store', 'RoleController@store');

            Route::post('assign-permission', 'RoleController@assignPermission');

            Route::get('get-permissions/{role_id}', 'RoleController@getPermissions');

        });


        Route::group(['prefix' => 'permission'], function() {

            Route::get('index', 'PermissionController@index');
            
            Route::post('store', 'PermissionController@store'); // THIS ROUTE WILL NOT BE USED BY ANY CLIENT. BUT IT'S STILL KEPT JUST IN CASE

        });


        Route::group(['prefix' => 'brand'], function() {

            Route::get('index', 'BrandController@index');
            
            Route::post('store', 'BrandController@store');

        });
        

        Route::group(['prefix' => 'product-category'], function() {

            Route::get('index', 'ProductCategoryController@index');
            
            Route::post('store', 'ProductCategoryController@store'); // THIS ROUTE WILL NOT BE USED BY ANY CLIENT. BUT IT'S STILL KEPT JUST IN CASE

        });


        Route::group(['prefix' => 'product-model'], function() {

            Route::get('index', 'ProductModelController@index');
            
            Route::post('store', 'ProductModelController@store');

        });


        Route::group(['prefix' => 'product'], function() {

            Route::get('index', 'ProductController@index');
            
            Route::post('store-phone', 'ProductController@storePhone');

            Route::post('store-charger', 'ProductController@storeCharger');

        });


        Route::group(['prefix' => 'purchase'], function() {

            Route::get('index', 'PurchaseTransactionController@index');
            
            Route::post('store', 'PurchaseTransactionController@store');

            Route::get('get-purchase-variations/{purchase_transaction_id}', 'PurchaseTransactionController@getPurchaseVariations');

        });


        Route::group(['prefix' => 'purchase-variation'], function() {

            Route::get('index', 'PurchaseVariationController@index');
            
            Route::post('store', 'PurchaseVariationController@store');

            Route::get('get-product-category-type/{product_id}', 'PurchaseVariationController@getProductCategoryType');

        });


        Route::group(['prefix' => 'sale'], function() {

            Route::get('index', 'SaleTransactionController@index');

            Route::post('store', 'SaleTransactionController@store');

            Route::get('get-sale-variations/{sale_transaction_id}', 'SaleTransactionController@getSaleVariations');

        });

    });

});