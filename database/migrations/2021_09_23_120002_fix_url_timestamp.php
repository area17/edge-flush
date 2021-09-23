<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class FixUrlTimestamp extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('edge_flush_urls', function (Blueprint $table) {
            $table->dropColumn('was_purged_at');
        });

        Schema::table('edge_flush_urls', function (Blueprint $table) {
            $table
                ->timestamp('was_purged_at')
                ->after('hits')
                ->nullable()
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
    }
}
