<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreTranslationRequest;
use App\Http\Requests\UpdateTranslationRequest;
use App\Models\Language;
use App\Models\Tag;
use App\Models\Translation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class TranslationController extends Controller
{
    /**
     * Create a translation.
     */
    public function store(StoreTranslationRequest $request): JsonResponse
    {
        $data = $request->only(['key', 'language_id', 'content', 'context']);
        $translation = Translation::create($data);

        // Attach tags (create if missing)
        $tags = $request->input('tags', []);
        $tagIds = $this->getOrCreateTagIds($tags);
        if (count($tagIds) > 0) {
            $translation->tags()->sync($tagIds);
        }

        // Invalidate export cache for the language
        $this->invalidateExportCache($translation->language_id);

        return response()->json(['data' => $translation], 201);
    }

    /**
     * Update a translation.
     */
    public function update(UpdateTranslationRequest $request, Translation $translation): JsonResponse
    {
        $translation->fill($request->only(['content', 'context']));
        $translation->save();

        if ($request->has('tags')) {
            $tags = $request->input('tags', []);
            $tagIds = $this->getOrCreateTagIds($tags);
            $translation->tags()->sync($tagIds);
        }

        $this->invalidateExportCache($translation->language_id);

        return response()->json(['data' => $translation]);
    }

    /**
     * Show a translation.
     */
    public function show(Translation $translation): JsonResponse
    {
        $translation->load('tags', 'language');

        return response()->json(['data' => $translation]);
    }

    /**
     * Delete a translation.
     */
    public function destroy(Translation $translation): JsonResponse
    {
        $languageId = $translation->language_id;
        $translation->delete();

        $this->invalidateExportCache($languageId);

        return response()->json(null, 204);
    }

    /**
     * Search / list translations by filters.
     *
     * Filters: key, language (code), tag (name), content, updated_since, per_page
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = (int) $request->input('per_page', 50);
        $query = Translation::query()->select(['id', 'key', 'content', 'language_id', 'context', 'updated_at'])
            ->with(['tags:id,name', 'language:id,code,name']);

        if ($key = $request->input('key')) {
            $query->where('key', 'like', $key . '%');
        }

        if ($content = $request->input('content')) {
            $query->where('content', 'like', '%' . $content . '%');
        }

        if ($updatedSince = $request->input('updated_since')) {
            $query->where('updated_at', '>=', $updatedSince);
        }

        if ($languageCode = $request->input('language')) {
            $lang = Language::where('code', $languageCode)->first();
            if ($lang) {
                $query->where('language_id', $lang->id);
            } else {
                return response()->json(['data' => []]);
            }
        }

        if ($tag = $request->input('tag')) {
            $query->whereHas('tags', function ($q) use ($tag) {
                $q->where('name', $tag);
            });
        }

        $results = $query->orderBy('key')->paginate($perPage);

        return response()->json($results);
    }

    /**
     * Export translations for a language as streamed JSON.
     *
     * Example: GET /api/v1/export/en.json
     */
    public function export(string $languageCode)
    {
        $language = Language::where('code', $languageCode)->firstOrFail();

        $lastUpdated = Translation::where('language_id', $language->id)->max('updated_at') ?? '0';
        $cacheKey = "translations_export:{$language->code}:{$lastUpdated}";

        // If cache exists, return it (small payloads), otherwise stream and cache result.
        if (Cache::has($cacheKey)) {
            $payload = Cache::get($cacheKey);
            return response($payload, 200, [
                'Content-Type' => 'application/json',
                'X-Cache' => 'HIT',
            ]);
        }

        $stream = function () use ($language) {
            // Start JSON object with translations
            echo '{"translations":{';
            $first = true;

            // Use cursor to stream rows without loading them into memory
            Translation::where('language_id', $language->id)
                ->select(['key', 'content'])
                ->orderBy('key')
                ->cursor()
                ->each(function ($row) use (&$first) {
                    if (!$first) {
                        echo ',';
                    }
                    $first = false;
                    // key should be a JSON string, content too
                    echo json_encode($row->key) . ':' . json_encode($row->content);
                });

            echo '}}';
        };

        // Capture stream output to cache string and send to client
        // Small languages are cached to speed repeated calls; large exports may skip caching.
        // We'll capture output with output buffering (careful with very large payloads).
        // If you expect extremely large payloads, pre-generate artifacts (S3) instead of caching here.
        ob_start();
        $stream();
        $payload = (string) ob_get_clean();

        // Cache for short time (e.g., 60 seconds). Invalidate on updates (done elsewhere).
        Cache::put($cacheKey, $payload, now()->addSeconds(60));

        return response($payload, 200, [
            'Content-Type' => 'application/json',
            'X-Cache' => 'MISS',
        ]);
    }

    /**
     * Helper to get or create tag ids from an array of names.
     *
     * @param array<int,string> $tags
     * @return int[]
     */
    protected function getOrCreateTagIds(array $tags): array
    {
        $tags = array_map('trim', array_filter($tags, function ($t) {
            return is_string($t) && $t !== '';
        }));

        if (count($tags) === 0) {
            return [];
        }

        $existing = Tag::whereIn('name', $tags)->get()->keyBy('name');
        $ids = [];

        foreach ($tags as $name) {
            if (isset($existing[$name])) {
                $ids[] = $existing[$name]->id;
            } else {
                $tag = Tag::create(['name' => $name]);
                $ids[] = $tag->id;
            }
        }

        return $ids;
    }

    /**
     * Invalidate export cache for a language.
     */
    protected function invalidateExportCache(int $languageId): void
    {
        $language = Language::find($languageId);
        if (! $language) {
            return;
        }

        // A conservative invalidation approach: flush all keys that match prefix.
        // If using Redis, you can run a pattern delete - here we iterate keys (only for small deployments).
        // For robust production-ready invalidation, store last_updated fingerprint per language and include it in cache key.
        $prefix = "translations_export:{$language->code}:";
        // Using Cache::getStore()->getRedis() is driver-specific. Attempt best-effort pattern delete for redis.
        try {
            $store = Cache::getStore();
            if (method_exists($store, 'getRedis')) {
                $redis = $store->getRedis();
                $keys = $redis->keys($prefix . '*');
                if (count($keys) > 0) {
                    $redis->del($keys);
                }
            }
        } catch (\Throwable $e) {
            // swallow: fallback - do nothing (cache TTL will expire)
        }
    }
}
