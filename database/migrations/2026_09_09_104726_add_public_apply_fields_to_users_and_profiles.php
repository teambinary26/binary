<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('pending_account')->default(false)->after('is_active');
        });

        Schema::table('applicant_profiles', function (Blueprint $table) {
            $table->string('mother_name')->nullable()->after('year_level');
            $table->string('mother_occupation')->nullable()->after('mother_name');
            $table->string('father_name')->nullable()->after('mother_occupation');
            $table->string('father_occupation')->nullable()->after('father_name');
            $table->boolean('is_pwd')->default(false)->after('father_occupation');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('pending_account');
        });

        Schema::table('applicant_profiles', function (Blueprint $table) {
            $table->dropColumn([
                'mother_name',
                'mother_occupation',
                'father_name',
                'father_occupation',
                'is_pwd',
            ]);
        });
    }
};
