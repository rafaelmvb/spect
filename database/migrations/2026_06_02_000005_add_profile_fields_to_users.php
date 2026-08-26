<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('location', 100)->nullable()->after('bio');
            $table->string('gender', 20)->nullable()->after('location');   // masculino | feminino | outro | prefiro_nao_dizer
            $table->date('birth_date')->nullable()->after('gender');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['location', 'gender', 'birth_date']);
        });
    }
};
