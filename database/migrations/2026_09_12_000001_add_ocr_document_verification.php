<?php

use App\Support\OcrFields;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('program_requirements', function (Blueprint $table) {
            $table->json('ocr_fields')->nullable()->after('is_required');
        });

        foreach (DB::table('program_requirements')->orderBy('id')->get() as $requirement) {
            DB::table('program_requirements')->where('id', $requirement->id)->update([
                'ocr_fields' => json_encode(OcrFields::defaultsForName($requirement->name)),
            ]);
        }

        Schema::create('ocr_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_submission_id')->constrained()->cascadeOnDelete();
            $table->string('status', 30)->default('pending');
            $table->longText('raw_text')->nullable();
            $table->string('expected_type', 80)->nullable();
            $table->string('detected_type', 80)->nullable();
            $table->boolean('type_matches')->nullable();
            $table->unsignedTinyInteger('overall_score')->nullable();
            $table->string('overall_status', 30)->nullable();
            $table->string('summary')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->unique('document_submission_id');
        });

        Schema::create('ocr_extracted_fields', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ocr_result_id')->constrained('ocr_results')->cascadeOnDelete();
            $table->string('field_key', 60);
            $table->string('field_label');
            $table->text('expected_value')->nullable();
            $table->text('extracted_value')->nullable();
            $table->text('corrected_value')->nullable();
            $table->string('match_status', 30)->default('missing');
            $table->unsignedTinyInteger('match_score')->nullable();
            $table->timestamps();

            $table->index(['ocr_result_id', 'field_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ocr_extracted_fields');
        Schema::dropIfExists('ocr_results');

        Schema::table('program_requirements', function (Blueprint $table) {
            $table->dropColumn('ocr_fields');
        });
    }
};
