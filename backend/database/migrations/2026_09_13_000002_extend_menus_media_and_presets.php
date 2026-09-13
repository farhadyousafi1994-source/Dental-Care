<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{Schema,DB};
return new class extends Migration {
    public function up(): void {
        Schema::table('menus', function (Blueprint $t) { $t->unsignedInteger('version')->default(1); $t->index(['website_id', 'location', 'language']); });
        Schema::table('menu_items', function (Blueprint $t) { $t->string('key', 80)->nullable(); $t->boolean('new_tab')->default(false); });
        Schema::table('media_folders', fn (Blueprint $t) => $t->foreignId('parent_id')->nullable()->constrained('media_folders')->nullOnDelete());
        Schema::table('media', function (Blueprint $t) { $t->unsignedInteger('width')->nullable(); $t->unsignedInteger('height')->nullable(); });
        // Preserve legacy folder labels when enabling hierarchical folders.
        DB::table('media')->whereNull('folder_id')->where('folder','!=','All files')->orderBy('id')->chunkById(200,function($rows){foreach($rows as $row){$name=trim($row->folder);if(!$name)continue;DB::table('media_folders')->insertOrIgnore(['website_id'=>$row->website_id,'name'=>$name]);$folder=DB::table('media_folders')->where('website_id',$row->website_id)->where('name',$name)->value('id');DB::table('media')->where('id',$row->id)->update(['folder_id'=>$folder]);}});
        Schema::create('theme_presets', function (Blueprint $t) { $t->id(); $t->foreignId('website_id')->constrained()->cascadeOnDelete(); $t->string('name', 120); $t->string('theme', 80); $t->json('tokens'); $t->timestamps(); $t->unique(['website_id', 'name']); });
    }
    public function down(): void {
        Schema::dropIfExists('theme_presets');
        Schema::table('media', fn (Blueprint $t) => $t->dropColumn(['width', 'height']));
        Schema::table('media_folders', fn (Blueprint $t) => $t->dropConstrainedForeignId('parent_id'));
        Schema::table('menu_items', fn (Blueprint $t) => $t->dropColumn(['key', 'new_tab']));
        Schema::table('menus', function (Blueprint $t) { $t->dropIndex(['website_id', 'location', 'language']); $t->dropColumn('version'); });
    }
};
