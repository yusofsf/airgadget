<?php
use App\Http\Controllers\Admin\ProductController;
use App\Http\Controllers\StoreController;
use Illuminate\Support\Facades\Route;
Route::get('/',[StoreController::class,'home'])->name('home');
Route::get('/shop',[StoreController::class,'shop'])->name('shop');
Route::get('/products/{product:slug}',[StoreController::class,'product'])->name('products.show');
Route::get('/articles',[StoreController::class,'articles'])->name('articles.index');
Route::get('/about-us',[StoreController::class,'page'])->defaults('page','about')->name('about');
Route::get('/contact-us',[StoreController::class,'page'])->defaults('page','contact')->name('contact');
Route::get('/terms',[StoreController::class,'page'])->defaults('page','terms')->name('terms');
Route::middleware('auth')->group(function(){Route::get('/account',[StoreController::class,'account'])->name('account');Route::get('/checkout',[StoreController::class,'checkout'])->name('checkout');Route::get('/admin',[StoreController::class,'admin'])->middleware('can:manage-store')->name('admin');Route::post('/admin/products',[ProductController::class,'store'])->middleware('can:manage-store')->name('admin.products.store');});
require __DIR__.'/auth.php';
