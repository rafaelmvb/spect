<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Bio no perfil do usuário
        Schema::table('users', function (Blueprint $table) {
            $table->text('bio')->nullable()->after('avatar');
        });

        // Amizades entre alunos
        Schema::create('member_friendships', function (Blueprint $table) {
            $table->id();
            $table->foreignId('requester_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('receiver_id')->constrained('users')->cascadeOnDelete();
            $table->string('status', 20)->default('pending'); // pending | accepted | declined
            $table->timestamps();
            $table->unique(['requester_id', 'receiver_id']);
            $table->index(['receiver_id', 'status']);
        });

        // Playlists de músicas dos alunos
        Schema::create('member_playlists', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->char('product_id', 36)->index();
            $table->foreign('product_id')->references('id')->on('products')->cascadeOnDelete();
            $table->string('name', 255);
            $table->timestamps();
        });

        // Músicas dentro das playlists
        Schema::create('member_playlist_tracks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('playlist_id')->constrained('member_playlists')->cascadeOnDelete();
            $table->foreignId('music_track_id')->constrained('music_tracks')->cascadeOnDelete();
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();
            $table->unique(['playlist_id', 'music_track_id']);
        });

        // Solicitações para entrar em grupos privados
        Schema::create('member_group_join_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('group_id')->constrained('member_community_groups')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('status', 20)->default('pending'); // pending | approved | rejected
            $table->timestamps();
            $table->unique(['group_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('member_group_join_requests');
        Schema::dropIfExists('member_playlist_tracks');
        Schema::dropIfExists('member_playlists');
        Schema::dropIfExists('member_friendships');
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('bio');
        });
    }
};
