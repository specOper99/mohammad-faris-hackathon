<?php

namespace Database\Seeders;

use App\Models\ScoringCriterion;
use Illuminate\Database\Seeder;

class ScoringCriterionSeeder extends Seeder
{
    public function run(): void
    {
        $rows = [
            ['scientific_understanding', 'Scientific problem understanding', 'فهم المشكلة العلمية', '0.1000', 1],
            ['data_processing', 'Data processing quality', 'جودة معالجة البيانات', '0.1500', 2],
            ['analysis_model', 'Analysis / model', 'التحليل / النموذج', '0.2000', 3],
            ['accuracy_validation', 'Accuracy / validation', 'الدقة / التحقق', '0.1500', 4],
            ['scientific_interpretation', 'Scientific interpretation', 'التفسير العلمي', '0.1500', 5],
            ['innovation', 'Innovation', 'الابتكار', '0.1000', 6],
            ['usability', 'Usability', 'سهولة الاستخدام', '0.0500', 7],
            ['presentation', 'Presentation / communication', 'العرض / التواصل', '0.1000', 8],
        ];

        foreach ($rows as [$code, $en, $ar, $weight, $order]) {
            ScoringCriterion::query()->updateOrCreate(
                ['code' => $code],
                [
                    'name_en' => $en,
                    'name_ar' => $ar,
                    'weight' => $weight,
                    'min_score' => 0,
                    'max_score' => 100,
                    'sort_order' => $order,
                    'is_active' => true,
                ],
            );
        }
    }
}
