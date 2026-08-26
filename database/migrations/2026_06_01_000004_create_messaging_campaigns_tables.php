<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('messaging_campaigns', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('tenant_id')->nullable()->index();
            $table->string('channel', 20); // whatsapp | sms
            $table->string('provider', 50); // z-api, evolution, twilio, meta, etc.
            $table->string('name', 255);
            $table->text('message_body');
            $table->json('filter_config')->nullable();
            $table->string('status', 20)->default('draft'); // draft, sending, sent, cancelled
            $table->unsignedInteger('total_recipients')->nullable();
            $table->unsignedInteger('sent_count')->default(0);
            $table->unsignedInteger('failed_count')->default(0);
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
            $table->index('status');
        });

        Schema::create('messaging_campaign_sends', function (Blueprint $table) {
            $table->id();
            $table->foreignId('messaging_campaign_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('phone', 30);
            $table->string('status', 20)->default('sent'); // sent | failed
            $table->text('error')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
            $table->unique(['messaging_campaign_id', 'phone']);
            $table->index('messaging_campaign_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('messaging_campaign_sends');
        Schema::dropIfExists('messaging_campaigns');
    }
};
