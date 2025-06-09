<?php

$router->get('/', function () use ($router) {
    return $router->app->version();
});

$router->get('/key', function () {
    return \Illuminate\Support\Str::random(32);
});

// API route group
$router->group(['prefix' => 'api'], function () use ($router) {

    // Matches "/api/companies
    $router->post('companies', 'CompanyController@store');

    // Matches "/api/login
    $router->post('login', 'AuthController@login');
    // Matches "/api/refreshtoken
    $router->get('refreshtoken', 'AuthController@refreshToken');
});

$router->group(['middleware' => 'jwt.verify'], function ($router) {

    // Admin
    $router->get('admin/customers', 'Admin/CustomerController@index');

    // me
    $router->get('me', 'AuthController@me');

    //Inventarios
    $router->post('inventories', 'InventoryController@store');

    $router->get('dashboard', 'DashboardController@index');
    $router->get('downloadsign', 'CompanyController@downloadSign');

    // Logout
    $router->get('logout', 'AuthController@logout');

    //Companies
    $router->get('companies', 'CompanyController@index');
    $router->post('company_update', 'CompanyController@update');

    // Branches
    $router->get('branches', 'BranchController@index');
    $router->post('branches', 'BranchController@store');
    $router->put('branch/update/{id}', 'BranchController@update');

    // Points
    $router->get('branch/{branch_id}', 'EmisionPointController@index');
    $router->post('points/store', 'EmisionPointController@store');
    $router->put('points/update/{id}', 'EmisionPointController@update');

    //Products
    $router->post('productlist', 'ProductController@productlist');
    $router->get('product/create', 'ProductController@create');
    $router->post('product', 'ProductController@store');
    $router->get('product/{id}', 'ProductController@show');
    $router->put('product/{id}', 'ProductController@update');
    $router->post('products/import', 'ProductController@import');
    $router->get('products/export', 'ProductController@export');
    $router->post('products/getmasive', 'ProductController@getmasive');

    //orders
    $router->post('orderlist', 'OrderController@orderlist');
    $router->get('orders/create', 'OrderController@create');
    $router->post('orders', 'OrderController@store');
    $router->post('orders/lot', 'OrderLotController@store');
    $router->get('orders/{id}', 'OrderController@show');
    $router->get('orders/{search}/search', 'OrderController@search');
    $router->put('orders/{id}', 'OrderController@update');
    $router->get('orders/{id}/pdf', 'OrderController@showPdf');
    $router->get('orders/{id}/printf', 'OrderController@printfPdf');
    $router->get('orders/{id}/mail', 'MailController@orderMail');

    //Order Xml
    $router->get('orders/xml/{id}', 'OrderXmlController@xml');
    $router->get('orders/download/{id}', 'OrderXmlController@download');
    $router->get('orders/sendsri/{id}', 'WSSriOrderController@send');
    $router->get('orders/authorize/{id}', 'WSSriOrderController@authorize');
    $router->get('orders/cancel/{id}', 'WSSriOrderController@cancel');
    $router->get('orders/export/{month}', 'OrderController@export');

    //shops
    $router->post('shoplist', 'ShopController@shoplist');
    $router->get('shops/create', 'ShopController@create');
    $router->post('shops', 'ShopController@store');
    $router->get('shops/{id}', 'ShopController@show');
    $router->put('shops/{id}', 'ShopController@update');
    $router->get('shops/{month}/export', 'ShopController@export');

    //shops Import from txt
    $router->post('shops/import', 'ShopImportController@import');

    // Liquidacion compra
    $router->get('shops/{id}/xml', 'SettlementOnPurchaseXmlController@xml');
    $router->get('shops/{id}/download', 'SettlementOnPurchaseXmlController@download');
    $router->get('shops/{id}/sendsri', 'WSSriSettlementOnPurchaseController@send');
    $router->get('shops/{id}/authorize', 'WSSriSettlementOnPurchaseController@authorize');
    $router->get('shops/{id}/cancel', 'WSSriSettlementOnPurchaseController@cancel');
    $router->get('shops/{id}/pdf', 'ShopController@showPdf');

    //Retention Xml
    $router->get('retentions/xml/{id}', 'RetentionXmlController@xml');
    $router->get('retentions/download/{id}', 'RetentionXmlController@download');
    $router->get('retentions/sendsri/{id}', 'WSSriRetentionController@sendSri');
    $router->get('retentions/authorize/{id}', 'WSSriRetentionController@authorize');
    $router->get('retentions/cancel/{id}', 'WSSriRetentionController@cancel');
    $router->get('retentions/pdf/{id}', 'ShopController@showPdfRetention');
    $router->get('retentions/mail/{id}', 'MailController@retentionMail');

    // Guias de remision
    $router->get('referralguides', 'ReferralGuideController@index');
    $router->get('referralguides/create', 'ReferralGuideController@create');
    $router->post('referralguides', 'ReferralGuideController@store');
    $router->get('referralguides/{id}', 'ReferralGuideController@show');
    $router->put('referralguides/{id}', 'ReferralGuideController@update');
    $router->get('referralguides/{id}/pdf', 'ReferralGuideController@showPdf');

    //Guias de remision Xml
    $router->get('referralguides/xml/{id}', 'ReferralGuideXmlController@xml');
    $router->get('referralguides/download/{id}', 'ReferralGuideXmlController@download');
    $router->get('referralguides/sendsri/{id}', 'WSSriReferralGuide@send');
    $router->get('referralguides/authorize/{id}', 'WSSriReferralGuide@authorize');

    //Customers
    $router->post('customerlist', 'CustomerController@customerlist');
    $router->post('customers', 'CustomerController@store');
    $router->get('customers/{id}/edit', 'CustomerController@edit');
    $router->put('customers/{id}', 'CustomerController@update');
    $router->post('customers_import_csv', 'CustomerController@importCsv');
    $router->get('customers/export', 'CustomerController@export');
    $router->get('customers/searchByCedula/{identification}', 'CustomerController@searchByCedula');
    $router->get('customers/searchByRuc/{identification}', 'CustomerController@searchByRuc');

    // Proveedores
    $router->post('providerlist', 'ProviderController@providerlist');
    $router->post('providers', 'ProviderController@store');
    $router->get('providers/{id}/edit', 'ProviderController@edit');
    $router->put('providers/{id}', 'ProviderController@update');
    $router->post('providers_import_csv', 'ProviderController@importCsv');
    $router->get('providers/searchByRuc/{identification}', 'ProviderController@searchByRuc');

    // Transportistas
    $router->post('carrierlist', 'CarrierController@carrierlist');
    $router->post('carriers', 'CarrierController@store');
    $router->get('carriers/{id}/edit', 'CarrierController@edit');
    $router->put('carriers/{id}', 'CarrierController@update');

    // ATS
    $router->get('ats/{month}', 'AtsController@generate');
});
