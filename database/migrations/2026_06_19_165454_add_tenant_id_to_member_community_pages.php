<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('member_community_pages', function (Blueprint $table) {
            $table->unsignedBigInteger('tenant_id')->nullable()->after('id')->index();
            $table->dropForeign(['product_id']);
            $table->unsignedBigInteger('product_id')->nullable()->change();
        });

        // Backfill tenant_id a partir do produto. Query builder em vez de
        // UPDATE ... JOIN para funcionar tambem em SQLite (usado nos testes).
        DB::table('member_community_pages')
            ->whereNull('tenant_id')
            ->whereNotNull('product_id')
            ->orderBy('id')
            ->chunkById(500, function ($rows) {
                $tenantPorProduto = DB::table('products')
                    ->whereIn('id', $rows->pluck('product_id')->unique()->filter()->all())
                    ->pluck('tenant_id', 'id');

                foreach ($rows as $row) {
                    $tenantId = $tenantPorProduto[$row->product_id] ?? null;
                    if ($tenantId !== null) {
                        DB::table('member_community_pages')->where('id', $row->id)->update(['tenant_id' => $tenantId]);
                    }
                }
            });
    }

    public function down(): void
    {
        Schema::table('member_community_pages', function (Blueprint $table) {
            $table->dropIndex(['tenant_id']);
            $table->dropColumn('tenant_id');
            $table->unsignedBigInteger('product_id')->nullable(false)->change();
            $table->foreign('product_id')->references('id')->on('products')->cascadeOnDelete();
        });
    }
};
