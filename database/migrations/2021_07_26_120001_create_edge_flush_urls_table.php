g<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateEdgeFlushUrlsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::dropIfExists('edge_flush_urls');

        Schema::create('edge_flush_urls', function (Blueprint $table) {
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
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('edge_flush_urls');
    }
}
