<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateEdgeFlushTagsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::dropIfExists('edge_flush_tags');

        Schema::create('edge_flush_tags', function (Blueprint $table) {
            $table->id();

            $table->string('model')->index();

            $table->string('tag')->index();

            $table->bigInteger('url_id')->index();

            $table
                ->boolean('obsolete')
                ->default(false)
                ->index();

            $table
                ->string('response_cache_hash')
                ->nullable()
                ->index();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('edge_flush_tags');
    }
}
