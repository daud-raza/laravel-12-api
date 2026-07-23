<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('conversation_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamp('joined_at')->nullable();
            $table->timestamp('last_read_at')->nullable();
            $table->unsignedBigInteger('last_read_message_id')->nullable();
            $table->timestamp('muted_at')->nullable();       // future
            $table->enum('role', ['member', 'admin'])->default('member'); // future (group)
            $table->timestamps();

            $table->unique(['conversation_id', 'user_id']);
            $table->index('user_id');
            $table->index(['user_id', 'conversation_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('conversation_user');
    }
};
