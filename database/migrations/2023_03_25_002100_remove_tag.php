<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class RemoveTag extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('edge_flush_tags', function (Blueprint $table) {
            $table->dropColumn('tag');
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
            $table->string('tag')->index();
        });
    }
}
