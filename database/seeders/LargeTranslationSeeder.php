<?php

namespace Database\Seeders;

use App\Models\Language;
use App\Models\Tag;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class LargeTranslationSeeder extends Seeder
{
    /**
     * Run the seeder to create large number of translations.
     *
     * This uses batch inserts for performance. Adjust $total and $batchSize as needed.
     */
    public function run(): void
    {
        $languages = Language::all()->pluck('id')->toArray();
        if (empty($languages)) {
            $this->command->info('No languages found. Run LanguageAndTagSeeder first.');
            return;
        }

        $tags = Tag::all()->pluck('id', 'name')->toArray();

        $total = 120000; // total translations to create (adjust as needed)
        $batchSize = 5000;

        $now = now()->toDateTimeString();

        $this->command->getOutput()->write("Seeding {$total} translations in batches of {$batchSize}...");
        $contexts = ['mobile', 'web', 'desktop'];
        $i = 0;
        while ($i < $total) {
            $rows = [];
            $pivotRows = [];

            $limit = min($batchSize, $total - $i);
            for ($j = 0; $j < $limit; $j++) {
                $globalIndex = $i + $j + 1;
                $languageId = $languages[array_rand($languages)];

                $key = 'auto.key.' . intval($globalIndex / 10) . '.' . Str::random(6); // repeats some keys across languages
                $content = 'Generated content ' . Str::random(40);

                $rows[] = [
                    'key' => $key,
                    'language_id' => $languageId,
                    'content' => $content,
                    'context' => $contexts[array_rand($contexts)],
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            DB::table('translations')->insert($rows);

            // attach tags randomly for the last inserted batch
            // fetch inserted ids (MySQL: lastInsertId only gives first id - instead, fetch by created_at & language)
            // Simpler approach: match on keys used in $rows
            $keys = array_column($rows, 'key');
            $inserted = DB::table('translations')
                ->whereIn('key', $keys)
                ->where('created_at', $now)
                ->get(['id', 'key']);

            foreach ($inserted as $ins) {
                // randomly assign 0-2 tags
                $assign = (rand(0, 2) === 0) ? [] : array_rand($tags, rand(1, min(2, count($tags))));
                if (!is_array($assign) && $assign !== null) {
                    $assign = [$assign];
                }

                if (!empty($assign)) {
                    foreach ($assign as $tagName) {
                        $pivotRows[] = [
                            'tag_id' => $tags[$tagName],
                            'translation_id' => $ins->id,
                        ];
                    }
                }
            }

            if (!empty($pivotRows)) {
                DB::table('tag_translation')->insert($pivotRows);
            }

            $i += $limit;
            $this->command->getOutput()->write(".");
        }

        $this->command->getOutput()->writeln("\nSeeding complete.");
        $this->command->newLine();

    }
}
