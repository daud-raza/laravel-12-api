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
        Schema::table('tasks', function (Blueprint $table) {
            $table->boolean('is_recurring')->default(false)->after('is_pinned');
            $table->enum('recurrence_type', ['daily', 'weekly', 'monthly'])->nullable()->after('is_recurring');
            $table->date('recurrence_ends_at')->nullable()->after('recurrence_type');
        });
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropColumn(['is_recurring', 'recurrence_type', 'recurrence_ends_at']);
        });
    }
};
