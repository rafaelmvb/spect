<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Stories: adicionar video arquivo + visibilidade por cursos
        Schema::table('member_community_stories', function (Blueprint $table) {
            $table->string('video_file', 500)->nullable()->after('video_url'); // arquivo enviado
            $table->string('visibility', 20)->default('all')->after('bg_color'); // all | specific
            $table->json('visible_product_ids')->nullable()->after('visibility');
        });

        // Likes nos stories
        Schema::create('member_community_story_likes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('story_id')->constrained('member_community_stories')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['story_id', 'user_id']);
        });

        // Views nos stories
        Schema::create('member_community_story_views', function (Blueprint $table) {
            $table->id();
            $table->foreignId('story_id')->constrained('member_community_stories')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['story_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('member_community_story_views');
        Schema::dropIfExists('member_community_story_likes');
        Schema::table('member_community_stories', function (Blueprint $table) {
            $table->dropColumn(['video_file', 'visibility', 'visible_product_ids']);
        });
    }
};
