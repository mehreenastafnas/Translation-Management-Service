<?php

namespace Database\Seeders;

use App\Models\Language;
use App\Models\Tag;
use Illuminate\Database\Seeder;

class LanguageAndTagSeeder extends Seeder
{
    public function run(): void
    {
        $languages = [
            ['code' => 'en', 'name' => 'English'],
            ['code' => 'fr', 'name' => 'French'],
            ['code' => 'es', 'name' => 'Spanish'],
            ['code' => 'de', 'name' => 'German'],
        ];

        foreach ($languages as $lang) {
            Language::updateOrCreate(['code' => $lang['code']], $lang);
        }

        $tags = ['mobile', 'web', 'desktop', 'onboarding', 'auth'];
        foreach ($tags as $name) {
            Tag::updateOrCreate(['name' => $name], ['name' => $name]);
        }
    }
}
