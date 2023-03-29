<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddIndexToModelTags extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('edge_flush_tags', function (Blueprint $table) {
            $table->index('model', 'edge_flush_tags_model_index');
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
            $table->dropIndex('edge_flush_tags_model_index');
        });
    }
}
