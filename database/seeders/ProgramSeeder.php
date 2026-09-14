<?php

namespace Database\Seeders;

use App\Enums\BeneficiaryType;
use App\Models\AssistanceProgram;
use App\Models\ProgramCategory;
use App\Support\OcrFields;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class ProgramSeeder extends Seeder
{
    public function run(): void
    {
        $catalog = [
            [
                'group' => 'student',
                'category' => 'Educational Assistance',
                'description' => 'Educational and financial assistance for qualified students.',
                'program' => [
                    'name' => 'Educational Assistance',
                    'code' => 'EDU-001',
                    'amount_type' => 'fixed',
                    'amount' => 5000,
                    'beneficiary_type' => BeneficiaryType::Student,
                    'description' => 'Cash assistance for qualified students to support educational expenses including school-related needs for the current academic period.',
                    'eligibility' => 'Applicant must be a bona fide resident of Nabua, currently enrolled in a recognized educational institution, and able to present a valid Certificate of Enrollment and school identification.',
                    'requirements' => [
                        ['Valid ID', 'Government-issued identification of the applicant or parent/guardian.'],
                        ['School ID', 'Current school identification card.'],
                        ['Certificate of Enrollment', 'Official enrollment certificate for the current term.'],
                        ['Proof of Residency', 'Barangay certificate or other proof of residency in Nabua.'],
                    ],
                    'rules' => [
                        ['label' => 'Bona fide resident of the Municipality of Nabua', 'field' => 'address', 'operator' => 'contains', 'value' => 'Nabua'],
                        ['label' => 'Currently enrolled student', 'field' => 'document_text', 'operator' => 'contains', 'value' => 'enrolled'],
                        ['label' => 'Has not received the same program for the current academic year', 'check_mode' => 'manual'],
                    ],
                    'fields' => [
                        ['school_name', 'School / University', 'text', true, null, 'Complete name of the school.'],
                        ['education_level', 'Education Level', 'select', true, ['Elementary', 'Junior High School', 'Senior High School', 'College', 'TESDA / Vocational'], null],
                        ['course_or_program', 'Course / Track / Program', 'text', true, null, null],
                        ['year_level', 'Year / Grade Level', 'text', true, null, null],
                        ['student_id_no', 'Student ID Number', 'text', true, null, null],
                        ['purpose', 'Purpose of Assistance', 'textarea', true, null, 'Briefly describe how the assistance will be used.'],
                    ],
                ],
            ],
            [
                'group' => 'student',
                'category' => 'Tuition Assistance',
                'description' => 'Support for tuition and related school fees of qualified students.',
                'program' => [
                    'name' => 'Tuition Assistance',
                    'code' => 'TUI-001',
                    'amount_type' => 'up_to',
                    'amount' => 8000,
                    'amount_max' => 8000,
                    'beneficiary_type' => BeneficiaryType::Student,
                    'description' => 'Financial assistance intended to offset tuition and miscellaneous school fees of qualified student-beneficiaries.',
                    'eligibility' => 'Enrolled students who are residents of Nabua with an outstanding or recently paid tuition assessment from a recognized institution.',
                    'requirements' => [
                        ['Valid ID', null],
                        ['School ID', null],
                        ['Certificate of Enrollment', null],
                        ['Statement of Account / Tuition Assessment', 'Official assessment or billing from the school.'],
                        ['Proof of Residency', null],
                    ],
                    'rules' => [
                        ['label' => 'Resident of Nabua', 'field' => 'address', 'operator' => 'contains', 'value' => 'Nabua'],
                        ['label' => 'Currently enrolled', 'field' => 'document_text', 'operator' => 'contains', 'value' => 'enrolled'],
                        ['label' => 'Has a tuition assessment for the current term', 'field' => 'document_text', 'operator' => 'contains', 'value' => 'tuition'],
                    ],
                    'fields' => [
                        ['school_name', 'School / University', 'text', true, null, null],
                        ['course_or_program', 'Course / Track', 'text', true, null, null],
                        ['year_level', 'Year Level', 'text', true, null, null],
                        ['tuition_amount', 'Assessed Tuition Amount (₱)', 'number', true, null, null],
                        ['term', 'Academic Term', 'text', true, null, 'e.g. 1st Semester SY 2026-2027'],
                    ],
                ],
            ],
            [
                'group' => 'student',
                'category' => 'School Supplies Assistance',
                'description' => 'Assistance for school supplies of qualified learners.',
                'program' => [
                    'name' => 'School Supplies Assistance',
                    'code' => 'SUP-001',
                    'amount_type' => 'fixed',
                    'amount' => 2000,
                    'beneficiary_type' => BeneficiaryType::Student,
                    'description' => 'Cash support for the purchase of basic school supplies for qualified elementary, secondary, and college students.',
                    'eligibility' => 'Students residing in Nabua who are enrolled for the current school year.',
                    'requirements' => [
                        ['Valid ID', null],
                        ['School ID', null],
                        ['Certificate of Enrollment', null],
                        ['Proof of Residency', null],
                    ],
                    'rules' => [
                        ['label' => 'Resident of Nabua', 'field' => 'address', 'operator' => 'contains', 'value' => 'Nabua'],
                        ['label' => 'Currently enrolled student', 'field' => 'document_text', 'operator' => 'contains', 'value' => 'enrolled'],
                    ],
                    'fields' => [
                        ['school_name', 'School', 'text', true, null, null],
                        ['grade_level', 'Grade / Year Level', 'text', true, null, null],
                        ['supplies_needed', 'Supplies Needed', 'textarea', true, null, null],
                    ],
                ],
            ],
            [
                'group' => 'student',
                'category' => 'Transportation Assistance',
                'description' => 'Support for school-related transportation of qualified students.',
                'program' => [
                    'name' => 'Transportation Assistance',
                    'code' => 'TRN-001',
                    'amount_type' => 'fixed',
                    'amount' => 1500,
                    'beneficiary_type' => BeneficiaryType::Student,
                    'description' => 'Assistance intended to defray daily or periodic transportation costs of qualified students traveling to and from school.',
                    'eligibility' => 'Enrolled students who reside in Nabua and study outside their barangay or in another municipality/city.',
                    'requirements' => [
                        ['Valid ID', null],
                        ['School ID', null],
                        ['Certificate of Enrollment', null],
                        ['Proof of Residency', null],
                    ],
                    'rules' => [
                        ['label' => 'Resident of Nabua', 'field' => 'address', 'operator' => 'contains', 'value' => 'Nabua'],
                        ['label' => 'Currently enrolled', 'field' => 'document_text', 'operator' => 'contains', 'value' => 'enrolled'],
                        ['label' => 'School is located outside the applicant’s barangay', 'check_mode' => 'manual'],
                    ],
                    'fields' => [
                        ['school_name', 'School', 'text', true, null, null],
                        ['school_location', 'School Location', 'text', true, null, null],
                        ['usual_transport', 'Usual Mode of Transport', 'select', true, ['Jeepney', 'Bus', 'Tricycle', 'Motorcycle', 'Other'], null],
                        ['estimated_daily_fare', 'Estimated Daily Fare (₱)', 'number', true, null, null],
                    ],
                ],
            ],
            [
                'group' => 'general',
                'category' => 'Medical Assistance',
                'description' => 'Financial support for eligible medical and healthcare-related expenses.',
                'program' => [
                    'name' => 'Medical Assistance',
                    'code' => 'MED-001',
                    'amount_type' => 'up_to',
                    'amount' => 10000,
                    'amount_max' => 10000,
                    'beneficiary_type' => BeneficiaryType::Both,
                    'description' => 'Financial assistance for qualified individuals with eligible hospital, laboratory, medicine, or related medical expenses.',
                    'eligibility' => 'Residents of Nabua with documented medical needs supported by a medical certificate and hospital billing or prescription.',
                    'requirements' => [
                        ['Valid ID', null],
                        ['Medical Certificate', 'Issued by an attending physician.'],
                        ['Hospital Bill / Statement of Account', 'Or official prescription and quotation for medicines.'],
                        ['Proof of Residency', null],
                    ],
                    'rules' => [
                        ['label' => 'Resident of Nabua', 'field' => 'address', 'operator' => 'contains', 'value' => 'Nabua'],
                        ['label' => 'Has a current medical need supported by documents', 'field' => 'document_text', 'operator' => 'contains', 'value' => 'diagnosis'],
                    ],
                    'fields' => [
                        ['hospital_name', 'Hospital / Clinic', 'text', true, null, null],
                        ['diagnosis', 'Diagnosis / Medical Condition', 'textarea', true, null, null],
                        ['confinement_date', 'Date of Confinement / Consultation', 'date', true, null, null],
                        ['estimated_expense', 'Estimated Medical Expense (₱)', 'number', true, null, null],
                        ['patient_relationship', 'Relationship to Patient', 'select', true, ['Self', 'Spouse', 'Child', 'Parent', 'Sibling', 'Other'], null],
                    ],
                ],
            ],
            [
                'group' => 'general',
                'category' => 'Burial Assistance',
                'description' => 'Assistance for qualified families with funeral and burial expenses.',
                'program' => [
                    'name' => 'Burial Assistance',
                    'code' => 'BUR-001',
                    'amount_type' => 'fixed',
                    'amount' => 5000,
                    'beneficiary_type' => BeneficiaryType::Both,
                    'description' => 'Cash assistance for qualified families to help cover funeral and burial expenses of a deceased immediate family member.',
                    'eligibility' => 'Next of kin who are residents of Nabua, with a registered death and supporting civil registry documents.',
                    'requirements' => [
                        ['Valid ID of Claimant', null],
                        ['Death Certificate', null],
                        ['Funeral Contract / Official Receipt', null],
                        ['Proof of Relationship', 'Birth certificate or other proof of kinship.'],
                        ['Proof of Residency', null],
                    ],
                    'rules' => [
                        ['label' => 'Claimant is a resident of Nabua', 'field' => 'address', 'operator' => 'contains', 'value' => 'Nabua'],
                        ['label' => 'Deceased is an immediate family member', 'check_mode' => 'manual'],
                    ],
                    'fields' => [
                        ['deceased_name', 'Name of Deceased', 'text', true, null, null],
                        ['relationship', 'Relationship to Deceased', 'select', true, ['Spouse', 'Child', 'Parent', 'Sibling', 'Other'], null],
                        ['date_of_death', 'Date of Death', 'date', true, null, null],
                        ['funeral_parlor', 'Funeral Parlor', 'text', true, null, null],
                    ],
                ],
            ],
            [
                'group' => 'general',
                'category' => 'Emergency Assistance',
                'description' => 'Support for qualified individuals experiencing urgent financial difficulties.',
                'program' => [
                    'name' => 'Emergency Assistance',
                    'code' => 'EMG-001',
                    'amount_type' => 'up_to',
                    'amount' => 5000,
                    'amount_max' => 5000,
                    'beneficiary_type' => BeneficiaryType::Both,
                    'description' => 'Short-term cash assistance for qualified individuals and families experiencing urgent financial difficulties arising from crisis situations.',
                    'eligibility' => 'Residents of Nabua who can demonstrate an urgent need such as fire, calamity, displacement, or other crisis as assessed by MSWDO.',
                    'requirements' => [
                        ['Valid ID', null],
                        ['Barangay Certificate', 'Certifying residency and the incident, if applicable.'],
                        ['Incident Report / Supporting Document', 'Police blotter, disaster certification, or equivalent.'],
                        ['Proof of Residency', null],
                    ],
                    'rules' => [
                        ['label' => 'Resident of Nabua', 'field' => 'address', 'operator' => 'contains', 'value' => 'Nabua'],
                        ['label' => 'Currently experiencing an urgent crisis or emergency', 'field' => 'document_text', 'operator' => 'contains', 'value' => 'emergency'],
                    ],
                    'fields' => [
                        ['emergency_type', 'Nature of Emergency', 'select', true, ['Fire', 'Flood / Calamity', 'Displacement', 'Loss of Income', 'Other'], null],
                        ['incident_date', 'Date of Incident', 'date', true, null, null],
                        ['description', 'Description of Situation', 'textarea', true, null, null],
                        ['requested_amount', 'Requested Amount (₱)', 'number', true, null, null],
                    ],
                ],
            ],
            [
                'group' => 'general',
                'category' => 'Food Assistance',
                'description' => 'Food and subsistence support for qualified households.',
                'program' => [
                    'name' => 'Food Assistance',
                    'code' => 'FOD-001',
                    'amount_type' => 'fixed',
                    'amount' => 2000,
                    'beneficiary_type' => BeneficiaryType::Both,
                    'description' => 'Cash or equivalent food assistance for qualified households experiencing food insecurity or related hardship.',
                    'eligibility' => 'Households residing in Nabua who are assessed as needing short-term food support.',
                    'requirements' => [
                        ['Valid ID', null],
                        ['Proof of Residency', null],
                        ['Barangay Certificate of Indigency', null],
                    ],
                    'rules' => [
                        ['label' => 'Resident of Nabua', 'field' => 'address', 'operator' => 'contains', 'value' => 'Nabua'],
                        ['label' => 'Household is experiencing food-related hardship', 'field' => 'document_text', 'operator' => 'contains', 'value' => 'indigency'],
                    ],
                    'fields' => [
                        ['household_size', 'Household Size', 'number', true, null, 'Number of household members.'],
                        ['reason', 'Reason for Request', 'textarea', true, null, null],
                    ],
                ],
            ],
            [
                'group' => 'general',
                'category' => 'Livelihood Assistance',
                'description' => 'Programs intended to help eligible beneficiaries establish or improve livelihood opportunities.',
                'program' => [
                    'name' => 'Livelihood Assistance',
                    'code' => 'LIV-001',
                    'amount_type' => 'up_to',
                    'amount' => 10000,
                    'amount_max' => 10000,
                    'beneficiary_type' => BeneficiaryType::NonStudent,
                    'description' => 'Assistance to help qualified residents start or improve a small livelihood or income-generating activity.',
                    'eligibility' => 'Non-student residents of Nabua with a viable livelihood proposal and no overlapping livelihood grant for the current year.',
                    'requirements' => [
                        ['Valid ID', null],
                        ['Proof of Residency', null],
                        ['Livelihood Proposal', 'Simple description of the proposed activity and needed materials.'],
                        ['Barangay Endorsement', null],
                    ],
                    'rules' => [
                        ['label' => 'Resident of Nabua', 'field' => 'address', 'operator' => 'contains', 'value' => 'Nabua'],
                        ['label' => 'Non-student beneficiary', 'check_mode' => 'manual'],
                        ['label' => 'Has a livelihood proposal', 'field' => 'document_text', 'operator' => 'contains', 'value' => 'livelihood'],
                    ],
                    'fields' => [
                        ['livelihood_type', 'Proposed Livelihood', 'text', true, null, null],
                        ['business_location', 'Location of Activity', 'text', true, null, null],
                        ['capital_needed', 'Estimated Capital Needed (₱)', 'number', true, null, null],
                        ['experience', 'Relevant Experience', 'textarea', false, null, null],
                    ],
                ],
            ],
        ];

        foreach ($catalog as $index => $item) {
            $category = ProgramCategory::query()->updateOrCreate(
                ['slug' => Str::slug($item['category'])],
                [
                    'name' => $item['category'],
                    'group' => $item['group'],
                    'description' => $item['description'],
                    'sort_order' => $index + 1,
                    'is_active' => true,
                ]
            );

            $programData = $item['program'];
            $program = AssistanceProgram::query()->updateOrCreate(
                ['code' => $programData['code']],
                [
                    'program_category_id' => $category->id,
                    'name' => $programData['name'],
                    'slug' => Str::slug($programData['name']),
                    'description' => $programData['description'],
                    'eligibility' => $programData['eligibility'],
                    'beneficiary_type' => $programData['beneficiary_type'],
                    'amount_type' => $programData['amount_type'],
                    'amount' => $programData['amount'],
                    'amount_max' => $programData['amount_max'] ?? $programData['amount'],
                    'is_open' => true,
                    'open_from' => now()->subMonth()->toDateString(),
                    'open_until' => now()->addMonths(6)->toDateString(),
                    'sort_order' => $index + 1,
                ]
            );

            $program->requirements()->delete();
            foreach ($programData['requirements'] as $rIndex => $requirement) {
                $program->requirements()->create([
                    'name' => $requirement[0],
                    'description' => $requirement[1],
                    'is_required' => true,
                    'ocr_fields' => OcrFields::defaultsForName($requirement[0]),
                    'sort_order' => $rIndex + 1,
                ]);
            }

            $program->eligibilityRules()->delete();
            foreach ($programData['rules'] as $rIndex => $rule) {
                $payload = is_array($rule)
                    ? [
                        'label' => $rule['label'] ?? $rule[0],
                        'field' => $rule['field'] ?? null,
                        'operator' => $rule['operator'] ?? null,
                        'value' => $rule['value'] ?? null,
                        'check_mode' => $rule['check_mode'] ?? 'ocr',
                        'sort_order' => $rIndex + 1,
                    ]
                    : [
                        'label' => $rule,
                        'sort_order' => $rIndex + 1,
                    ];

                $program->eligibilityRules()->create($payload);
            }

            $program->formFields()->delete();
            foreach ($programData['fields'] as $fIndex => $field) {
                $program->formFields()->create([
                    'name' => $field[0],
                    'label' => $field[1],
                    'type' => $field[2],
                    'is_required' => $field[3],
                    'options' => $field[4],
                    'help_text' => $field[5],
                    'sort_order' => $fIndex + 1,
                ]);
            }
        }
    }
}
