<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('program_eligibility_rules', function (Blueprint $table) {
            $table->string('check_mode', 20)->default('ocr')->after('value');
        });

        Schema::table('application_evaluations', function (Blueprint $table) {
            $table->json('eligibility_checks')->nullable()->after('eligibility_passed');
        });
    }

    public function down(): void
    {
        Schema::table('program_eligibility_rules', function (Blueprint $table) {
            $table->dropColumn('check_mode');
        });

        Schema::table('application_evaluations', function (Blueprint $table) {
            $table->dropColumn('eligibility_checks');
        });
    }
};
