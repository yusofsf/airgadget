<?php
namespace App\Http\Controllers;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use Inertia\Inertia;
class StoreController extends Controller {
 public function home(){return Inertia::render('Storefront',['view'=>'home','products'=>Product::with(['brand','images'])->latest()->take(8)->get(),'categoryCounts'=>Category::withCount('products')->having('products_count','>',0)->get(['id','name','slug'])]);}
 public function shop(){return Inertia::render('Storefront',['view'=>'shop','products'=>Product::with(['brand','images'])->paginate(12)]);}
 public function product(Product $product){return Inertia::render('Storefront',['view'=>'product','product'=>$product->load(['brand','category','images','specifications'])]);}
 public function articles(){return Inertia::render('Storefront',['view'=>'articles']);}
 public function page(string $page){return Inertia::render('Storefront',['view'=>$page]);}
 public function account(){return Inertia::render('Storefront',['view'=>'account']);}
 public function checkout(){return Inertia::render('Storefront',['view'=>'checkout']);}
 public function admin(){return Inertia::render('Storefront',['view'=>'admin','products'=>Product::with(['brand','category','images'])->latest()->take(24)->get(),'categories'=>Category::orderBy('name')->get(['id','name']),'brands'=>Brand::orderBy('name')->get(['id','name'])]);}
}
