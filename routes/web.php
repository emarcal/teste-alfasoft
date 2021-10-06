<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::get('/', 'App\Http\Controllers\HomeController@welcome');
Auth::routes();
Route::group(['middleware' => 'auth'], function () {
    Route::get('admin/contact/recovery', 'App\Http\Controllers\Admin\ContactController@trash')->name('trash');
    Route::post('admin/contact/recover/{id}', 'App\Http\Controllers\Admin\ContactController@recovery');
    Route::resource('admin/contact', 'App\Http\Controllers\Admin\ContactController');

});