<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddInvalidColumnToUrls extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('edge_flush_tags', function (Blueprint $table) {
            $table->boolean('is_valid')->default(true);
        });

        Schema::table('edge_flush_urls', function (Blueprint $table) {
            $table->boolean('is_valid')->default(true);
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
            $table->dropColumn('is_valid');
        });

        Schema::table('edge_flush_urls', function (Blueprint $table) {
            $table->dropColumn('is_valid');
        });
    }
}
