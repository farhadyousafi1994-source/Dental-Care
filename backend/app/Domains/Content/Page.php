<?php
namespace App\Domains\Content;
use Illuminate\Database\Eloquent\Model;
class Page extends Model {protected $guarded=['id'];protected function casts():array{return ['version'=>'integer','blocks'=>'array','seo'=>'array','publish_at'=>'datetime'];}}
