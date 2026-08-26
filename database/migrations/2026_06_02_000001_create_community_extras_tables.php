<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Eventos da comunidade
        Schema::create('member_community_events', function (Blueprint $table) {
            $table->id();
            $table->char('product_id', 36)->index();
            $table->foreign('product_id')->references('id')->on('products')->cascadeOnDelete();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
            $table->string('title', 255);
            $table->text('description')->nullable();
            $table->timestamp('starts_at');
            $table->timestamp('ends_at')->nullable();
            $table->string('location', 500)->nullable();
            $table->string('link', 500)->nullable();
            $table->string('image', 500)->nullable();
            $table->boolean('is_online')->default(false);
            $table->unsignedInteger('rsvp_count')->default(0);
            $table->timestamps();
        });

        // RSVP (quem confirmou presença)
        Schema::create('member_community_event_rsvps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained('member_community_events')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['event_id', 'user_id']);
        });

        // Grupos da comunidade
        Schema::create('member_community_groups', function (Blueprint $table) {
            $table->id();
            $table->char('product_id', 36)->index();
            $table->foreign('product_id')->references('id')->on('products')->cascadeOnDelete();
            $table->string('name', 255);
            $table->text('description')->nullable();
            $table->string('image', 500)->nullable();
            $table->boolean('is_private')->default(false);
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();
        });

        // Membros dos grupos
        Schema::create('member_community_group_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('group_id')->constrained('member_community_groups')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('role', 20)->default('member'); // member | admin
            $table->timestamps();
            $table->unique(['group_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('member_community_group_members');
        Schema::dropIfExists('member_community_groups');
        Schema::dropIfExists('member_community_event_rsvps');
        Schema::dropIfExists('member_community_events');
    }
};
