<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Product extends Model { protected $guarded=[]; protected $casts=['gallery'=>'array','attributes'=>'array','is_active'=>'boolean']; public function category(){return $this->belongsTo(Category::class);} public function brand(){return $this->belongsTo(Brand::class);} public function images(){return $this->hasMany(ProductImage::class);} public function specifications(){return $this->hasMany(ProductSpecification::class);} public function tags(){return $this->belongsToMany(Tag::class);} }
