<?php

use App\Http\Controllers\CatalogAdminController;
use App\Http\Controllers\PlatformController as P;
use Illuminate\Support\Facades\Route;

Route::get('/', [P::class, 'home'])->name('home');
Route::get('/koffies', [P::class, 'catalog'])->name('catalog');
Route::get('/koffies/{coffee:slug}', [P::class, 'coffee'])->name('coffee');
Route::get('/roasters', [P::class, 'roasters'])->name('roasters');
Route::get('/roasters/{roaster:slug}', [P::class, 'roaster'])->name('roaster');
Route::get('/recepten', [P::class, 'recipes'])->name('recipes');
Route::get('/mand', [P::class, 'cart'])->name('cart');
Route::post('/mand', [P::class, 'add'])->name('cart.add');
Route::patch('/mand', [P::class, 'updateCart'])->name('cart.update');
Route::middleware('auth')->group(function () {
    Route::get('/recepten/nieuw', [P::class, 'recipeForm'])->name('recipe.create');
    Route::post('/recepten', [P::class, 'saveRecipe'])->name('recipe.store');
    Route::get('/recepten/{recipe}/bewerken', [P::class, 'recipeForm'])->name('recipe.edit');
    Route::put('/recepten/{recipe}', [P::class, 'saveRecipe'])->name('recipe.update');
    Route::delete('/recepten/{recipe}', [P::class, 'deleteRecipe'])->name('recipe.delete');
    Route::post('/recepten/{recipe}/behulpzaam', [P::class, 'vote'])->name('recipe.vote');
    Route::post('/koffies/{coffee}/reviews', [P::class, 'review'])->name('review.store');
    Route::delete('/reviews/{review}', [P::class, 'deleteReview'])->name('review.delete');
    Route::get('/bewaard', [P::class, 'saved'])->name('saved');
    Route::post('/bewaard', [P::class, 'favorite'])->name('favorite');
    Route::get('/afrekenen', [P::class, 'checkout'])->name('checkout');
    Route::post('/afrekenen', [P::class, 'place'])->name('checkout.place');
    Route::get('/bestellingen', [P::class, 'history'])->name('dashboard');
    Route::get('/bestellingen/{order}', [P::class, 'order'])->name('order');
    Route::post('/bestellingen/{order}/betalen', [P::class, 'pay'])->name('order.pay');
    Route::post('/melden', [P::class, 'report'])->name('report');
    Route::get('/beheer', [P::class, 'admin'])->name('admin');
    Route::get('/beheer/koffies/nieuw', [CatalogAdminController::class, 'coffeeForm'])->name('admin.coffee.create');
    Route::get('/beheer/koffies/{coffee}', [CatalogAdminController::class, 'coffeeForm'])->name('admin.coffee.edit');
    Route::post('/beheer/koffies', [CatalogAdminController::class, 'coffeeSave'])->name('admin.coffee.store');
    Route::put('/beheer/koffies/{coffee}', [CatalogAdminController::class, 'coffeeSave'])->name('admin.coffee.update');
    Route::get('/beheer/roasters/nieuw', [CatalogAdminController::class, 'roasterForm'])->name('admin.roaster.create');
    Route::get('/beheer/roasters/{roaster}', [CatalogAdminController::class, 'roasterForm'])->name('admin.roaster.edit');
    Route::post('/beheer/roasters', [CatalogAdminController::class, 'roasterSave'])->name('admin.roaster.store');
    Route::put('/beheer/roasters/{roaster}', [CatalogAdminController::class, 'roasterSave'])->name('admin.roaster.update');
    Route::post('/beheer/bestellingen/{order}/artikelen/{item}/batch', [CatalogAdminController::class, 'assign'])->name('admin.assign');

    Route::post('/beheer/bestellingen/{order}', [P::class, 'fulfill'])->name('admin.fulfill');
    Route::post('/beheer/moderatie', [P::class, 'moderate'])->name('admin.moderate');
    Route::patch('/beheer/varianten/{variant}', [P::class, 'inventory'])->name('admin.inventory');
    Route::post('/beheer/batches', [P::class, 'batch'])->name('admin.batch');
    Route::patch('/beheer/roasters/{roaster}', [P::class, 'roasterPolicy'])->name('admin.roaster');
});
Route::get('/recepten/{recipe}', [P::class, 'recipe'])->name('recipe');
Route::post('/webhooks/mollie',fn () => response()->json(['message' => 'Live betalingen zijn uitgeschakeld.'],501))->name('webhooks.mollie');
require __DIR__.'/auth.php';
