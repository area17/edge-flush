<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class MoveObsoleteToUrls extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('edge_flush_urls', function (Blueprint $table) {
            $table->boolean('obsolete')->default(false);
        });

        Schema::table('edge_flush_tags', function (Blueprint $table) {
            $table->dropColumn('obsolete');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('edge_flush_tags', function (Blueprint $table) {
            $table->boolean('obsolete')->default(false);
        });

        Schema::table('edge_flush_urls', function (Blueprint $table) {
            $table->dropColumn('obsolete');
        });
    }
}
