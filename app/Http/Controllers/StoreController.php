<?php
namespace App\Http\Controllers;
use App\Models\Article;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\Order;
use App\Models\StoreSetting;
use App\Models\Tag;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;
class StoreController extends Controller {
 public function home(){return Inertia::render('Storefront',['view'=>'home','products'=>Product::with(['brand','images'])->where('is_active',true)->latest()->take(8)->get(),'categoryCounts'=>Category::whereHas('products',fn($query)=>$query->where('is_active',true))->withCount(['products'=>fn($query)=>$query->where('is_active',true)])->get(['id','name','slug'])]);}
 public function shop(){return Inertia::render('Storefront',['view'=>'shop','products'=>Product::with(['brand','images'])->where('is_active',true)->paginate(12)]);}
 public function product(Product $product){abort_unless($product->is_active,404);return Inertia::render('Storefront',['view'=>'product','product'=>$product->load(['brand','category','images','specifications'])]);}
 public function articles(){return Inertia::render('Storefront',['view'=>'articles','articles'=>Article::with('tags')->where('is_published',true)->latest('published_at')->paginate(12),'topics'=>Article::where('is_published',true)->whereNotNull('topic')->select('topic')->distinct()->orderBy('topic')->pluck('topic'),'tags'=>Tag::has('articles')->orderBy('name')->get(['id','name','slug'])]);}
 public function article(Article $article){abort_unless($article->is_published,404);return Inertia::render('Storefront',['view'=>'article','article'=>$article->load('tags')]);}
 public function page(string $page){return Inertia::render('Storefront',['view'=>$page]);}
 public function account(){
  $user=request()->user();
  $orders=$user && Schema::hasTable('orders') && Schema::hasTable('order_items')
   ? $user->orders()->with('items')->latest()->get()
   : collect();
  return Inertia::render('Storefront',['view'=>'account','orders'=>$orders]);
 }
 public function checkout(){return Inertia::render('Storefront',['view'=>'checkout','shippingMethods'=>collect(StoreSetting::shippingMethods())->where('is_active',true)->values()]);}
 public function admin(){
  $hasOrders=Schema::hasTable('orders') && Schema::hasTable('order_items');
  $accounting=['received'=>0,'product_revenue'=>0,'shipping_revenue'=>0,'sold_items'=>0,'paid_orders'=>0,'pending_orders'=>0];
  $orders=collect();
  if($hasOrders){
   $paid=Order::where(fn($query)=>$query->whereNotNull('paid_at')->orWhereIn('status',['processing','completed']));
   $accounting=['received'=>(clone $paid)->sum('total'),'product_revenue'=>(clone $paid)->selectRaw('COALESCE(SUM(subtotal - discount), 0) total')->value('total'),'shipping_revenue'=>(clone $paid)->sum('shipping_cost'),'sold_items'=>(int) (clone $paid)->withSum('items','quantity')->get()->sum('items_sum_quantity'),'paid_orders'=>(clone $paid)->count(),'pending_orders'=>Order::where('status','pending_payment')->count()];
   $orders=Order::with(['items','user'])->latest()->take(30)->get();
  }
  return Inertia::render('Storefront',['view'=>'admin','products'=>Product::with(['brand','category','images'])->latest()->get(),'articles'=>Article::with('tags')->latest()->get(),'categories'=>Category::orderBy('name')->get(['id','name']),'brands'=>Brand::orderBy('name')->get(['id','name']),'accounting'=>$accounting,'orders'=>$orders,'shippingMethods'=>StoreSetting::shippingMethods()]);
 }
}
