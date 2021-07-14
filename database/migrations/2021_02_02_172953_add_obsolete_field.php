<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddObsoleteField extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('cdn_cache_tags', function (Blueprint $table) {
            $table
                ->boolean('obsolete')
                ->default(false)
                ->index();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('cdn_cache_tags', function (Blueprint $table) {
            $table->dropColumn('obsolete');
        });
    }
}
