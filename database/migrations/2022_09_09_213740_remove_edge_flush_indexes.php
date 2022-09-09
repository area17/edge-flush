<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class RemoveEdgeFlushIndexes extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('edge_flush_tags', function (Blueprint $table) {
            $table->dropIndex('edge_flush_tags_model_index');
            $table->dropIndex('edge_flush_tags_obsolete_index');
            $table->dropIndex('edge_flush_tags_response_cache_hash_index');
            $table->dropIndex('edge_flush_tags_tag_index');
            $table->dropIndex('edge_flush_tags_url_id_index');
        });

        Schema::table('edge_flush_urls', function (Blueprint $table) {
            $table->dropIndex('edge_flush_urls_hits_index');
            $table->dropIndex('edge_flush_urls_invalidation_id_index');
            $table->dropIndex('edge_flush_urls_url_hash_index');
            $table->dropIndex('edge_flush_urls_url_index');
            $table->dropIndex('edge_flush_urls_was_purged_at_index');
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
