<?php

namespace Database\Seeders;

use App\Enums\TrackDifficulty;
use App\Models\Track;
use Illuminate\Database\Seeder;

class TrackSeeder extends Seeder
{
    public function run(): void
    {
        $rows = [
            ['A', 'Transit Hunter', 'صياد العبور', TrackDifficulty::Beginner, 'Transit signals, light curves, interpretation', 'إشارات العبور ومنحنيات الضوء والتفسير'],
            ['B', 'Analyst', 'المحلل', TrackDifficulty::Intermediate, 'Cleaning, analysis, parameter extraction', 'التنظيف والتحليل واستخراج المعاملات'],
            ['C', 'ML Classifier', 'مصنف التعلم الآلي', TrackDifficulty::IntermediateAdvanced, 'ML, candidate classification, features', 'التعلم الآلي وتصنيف المرشحين والسمات'],
            ['D', 'Advanced / FITS', 'متقدم / FITS', TrackDifficulty::Advanced, 'FITS, images, metadata, photometry', 'ملفات FITS والصور والبيانات الوصفية والقياس الضوئي'],
            ['E', 'Discovery Tool', 'أداة الاكتشاف', TrackDifficulty::Advanced, 'Dashboards, analysis, visualization, reporting', 'لوحات المعلومات والتحليل والتصور والتقارير'],
            ['F', 'AI Challenge', 'تحدي الذكاء الاصطناعي', TrackDifficulty::Advanced, 'Signal detection, AI-assisted analysis, false-positive reduction', 'كشف الإشارة والتحليل بمساعدة الذكاء الاصطناعي وتقليل الإيجابيات الكاذبة'],
        ];

        foreach ($rows as $i => [$code, $en, $ar, $diff, $focusEn, $focusAr]) {
            Track::query()->updateOrCreate(
                ['code' => $code],
                [
                    'name_en' => $en,
                    'name_ar' => $ar,
                    'description_en' => $focusEn,
                    'description_ar' => $focusAr,
                    'difficulty' => $diff,
                    'focus_en' => $focusEn,
                    'focus_ar' => $focusAr,
                    'is_active' => true,
                    'sort_order' => $i + 1,
                ],
            );
        }
    }
}
