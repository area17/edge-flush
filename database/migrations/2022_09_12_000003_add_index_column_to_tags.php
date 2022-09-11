<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddIndexColumnToTags extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('edge_flush_tags', function (Blueprint $table) {
            $table
                ->string('index')
                ->index()
                ->unique()
                ->nullable()
                ->position(1);
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
            $table->dropColumn('index');
        });
    }
}
