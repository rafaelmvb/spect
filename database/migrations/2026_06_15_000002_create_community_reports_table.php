<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('community_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('post_id')->constrained('member_community_posts')->cascadeOnDelete();
            $table->foreignId('reporter_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('reason', 60);
            $table->text('notes')->nullable();
            $table->string('status', 20)->default('pending'); // pending, resolved, dismissed
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->unique(['post_id', 'reporter_user_id']); // aluno só denuncia um post uma vez
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('community_reports');
    }
};
