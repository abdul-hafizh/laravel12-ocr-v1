<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('reminder_notifications', function (Blueprint $table) {
            $table->id();

            $table->string('module'); 

            $table->unsignedBigInteger('reference_id');

            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();

            $table->date('due_date');
            $table->integer('reminder_days')->default(7);
            $table->date('reminder_date');

            $table->string('title');
            $table->text('message');

            $table->boolean('is_sent')->default(false);
            $table->timestamp('sent_at')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reminder_notifications');
    }
};
