<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{Schema,DB};
return new class extends Migration {
 public function up():void {
  Schema::create('submissions',function(Blueprint $t){$t->id();$t->foreignId('website_id')->constrained()->cascadeOnDelete();$t->string('name',120);$t->string('email');$t->text('message');$t->string('language',5);$t->string('status',20)->default('new');$t->timestamps();$t->index(['website_id','status']);});
  Schema::create('subscribers',function(Blueprint $t){$t->id();$t->foreignId('website_id')->constrained()->cascadeOnDelete();$t->string('email');$t->string('language',5);$t->string('status',20)->default('pending');$t->string('token_hash',64)->nullable();$t->timestamp('token_expires_at')->nullable();$t->timestamp('consented_at');$t->timestamp('confirmed_at')->nullable();$t->timestamps();$t->unique(['website_id','email']);});
  Schema::create('products',function(Blueprint $t){$t->id();$t->foreignId('website_id')->constrained()->cascadeOnDelete();$t->string('name',190);$t->string('sku',80);$t->text('description')->nullable();$t->text('image')->nullable();$t->string('currency',3);$t->unsignedInteger('price_minor');$t->unsignedInteger('stock')->default(0);$t->boolean('active')->default(false);$t->unsignedInteger('version')->default(1);$t->timestamps();$t->unique(['website_id','sku']);$t->index(['website_id','active']);});
  Schema::create('orders',function(Blueprint $t){$t->id();$t->foreignId('website_id')->constrained()->cascadeOnDelete();$t->uuid('reference')->unique();$t->uuid('idempotency_key');$t->string('request_hash',64);$t->string('name',120);$t->string('email');$t->string('phone',40);$t->text('address');$t->string('currency',3);$t->unsignedBigInteger('total_minor');$t->string('status',20)->default('pending');$t->string('payment_status',20)->default('unpaid');$t->string('payment_method',20)->default('cod');$t->json('items');$t->timestamps();$t->unique(['website_id','idempotency_key']);$t->index(['website_id','status']);});
  foreach(['commerce','submissions']as$p)DB::table('permissions')->insertOrIgnore(['name'=>$p]);
 }
 public function down():void {foreach(['orders','products','subscribers','submissions']as$t)Schema::dropIfExists($t);}
};
