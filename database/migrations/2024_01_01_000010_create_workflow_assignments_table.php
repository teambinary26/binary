<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workflow_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('application_id')->constrained()->cascadeOnDelete();
            $table->string('workflow_step', 40);
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['application_id', 'workflow_step']);
            $table->index(['user_id', 'workflow_step']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workflow_assignments');
    }
};
