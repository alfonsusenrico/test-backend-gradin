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
        Schema::create('couriers', function (Blueprint $table) {
            $table->id();
            $table->string('name')->index();
            $table->string('phone', 32)->nullable()->unique();
            $table->string('email')->nullable();
            $table->unsignedTinyInteger('level')->index();
            $table->enum('status', ['active', 'inactive'])->default('active')->index();
            $table->timestamp('registered_at')->useCurrent()->index();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('couriers');
    }
};
