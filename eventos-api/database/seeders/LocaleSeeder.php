<?php

namespace Database\Seeders;

use App\Models\Locale;
use Illuminate\Database\Seeder;

class LocaleSeeder extends Seeder
{
    public function run(): void
    {
        $locales = [
            ['code' => 'en', 'name' => 'English',    'native_name' => 'English',  'direction' => 'ltr'],
            ['code' => 'bn', 'name' => 'Bengali',    'native_name' => 'বাংলা',     'direction' => 'ltr'],
            ['code' => 'es', 'name' => 'Spanish',    'native_name' => 'Español',  'direction' => 'ltr'],
            ['code' => 'fr', 'name' => 'French',     'native_name' => 'Français', 'direction' => 'ltr'],
            ['code' => 'ar', 'name' => 'Arabic',     'native_name' => 'العربية',   'direction' => 'rtl'],
        ];

        foreach ($locales as $l) {
            Locale::updateOrCreate(['code' => $l['code']], $l + ['is_active' => true]);
        }
    }
}
