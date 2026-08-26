<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Disponibilidade semanal recorrente do profissional
        Schema::create('professional_availabilities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('professional_id')->constrained('professionals')->cascadeOnDelete();
            // 0=Dom, 1=Seg, 2=Ter, 3=Qua, 4=Qui, 5=Sex, 6=Sáb
            $table->tinyInteger('day_of_week');
            $table->time('start_time');
            $table->time('end_time');
            $table->smallInteger('slot_duration')->default(60); // minutos
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['professional_id', 'day_of_week']);
        });

        Schema::create('appointments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->nullable()->index();
            $table->foreignId('professional_id')->constrained('professionals')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('client_name');
            $table->string('client_email')->nullable();
            $table->string('client_phone', 30)->nullable();
            $table->date('scheduled_date');
            $table->time('scheduled_time');
            $table->smallInteger('duration_minutes')->default(60);
            // pending | confirmed | completed | cancelled | no_show
            $table->string('status', 20)->default('pending');
            $table->text('notes')->nullable();        // notas do cliente/admin
            $table->text('admin_notes')->nullable();  // notas internas
            $table->text('cancelled_reason')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('appointments');
        Schema::dropIfExists('professional_availabilities');
    }
};
