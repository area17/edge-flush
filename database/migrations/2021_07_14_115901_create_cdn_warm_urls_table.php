<?php

use A17\CDN\Models\Tag;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateCdnWarmUrlsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Tag::truncate();

        Schema::create('cdn_cache_urls', function (Blueprint $table) {
            $table->id();

            $table->string('url', 2500)->index();

            $table->string('url_hash')->index();

            $table
                ->bigInteger('hits')
                ->index()
                ->default(0);

            $table
                ->time('was_purged_at')
                ->nullable()
                ->index();

            $table->timestamps();
        });

        Schema::table('cdn_cache_tags', function (Blueprint $table) {
            $table->dropColumn('url');

            $table->dropColumn('url_hash');

            $table->bigInteger('url_id')->index();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('cdn_cache_urls');

        Schema::table('cdn_cache_tags', function (Blueprint $table) {
            $table->dropColumn('url_id');

            $table->string('url')->index();

            $table->string('url_hash')->index();
        });
    }
}
