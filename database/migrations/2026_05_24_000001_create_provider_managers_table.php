<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('provider_managers', function (Blueprint $table) {
            $table->id();
            $table->string('provider');
            $table->string('external_id');
            $table->unsignedBigInteger('profile_id');
            $table->enum('role', ['owner', 'moderator'])->default('moderator');
            $table->string('added_by')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('profile_id')->references('id')->on('users')->cascadeOnDelete();
            $table->unique(['provider', 'external_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('provider_managers');
    }
};
