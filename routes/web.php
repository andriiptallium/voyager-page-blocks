<?php

/**
 * Admin Route/s
 */
Route::group([
    'as' => 'voyager.page-blocks.',
    'prefix' => 'admin/page-blocks/',
    'middleware' => ['web', 'admin.user'],
    'namespace' => '\Pvtl\VoyagerPageBlocks\Http\Controllers'
], function () {
    Route::post('sort', ['uses' => "PageBlockController@sort", 'as' => 'sort']);
    Route::post('minimize', ['uses' => "PageBlockController@minimize", 'as' => 'minimize']);
    Route::post('delete-multiple-image', ['uses' => "PageBlockController@deleteMultipleImage", 'as' => 'delete-multiple-image']);
});
