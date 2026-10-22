<?php

namespace App\Console\Commands;

use App\Models\NewsArticle;
use App\Models\NewsCategory;
use App\Services\NewsHtmlSanitizer;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class ImportHistoricalNews extends Command
{
    protected $signature = 'news:import-historical {--apply : Write records and copy images. Without this option, only validate and report.}';

    protected $description = 'Validate or import the seven historical news articles from the checked-in frontend snapshot.';

    public function handle(NewsHtmlSanitizer $sanitizer): int
    {
        if ($this->option('apply') && app()->environment('production')) {
            $this->components->error('Import is disabled in the production application environment.');

            return self::FAILURE;
        }

        $fixturePath = base_path('database/fixtures/news/historical-news.json');
        $imageDirectory = base_path('database/fixtures/news/images');

        try {
            $articles = $this->readFixture($fixturePath, $imageDirectory, $sanitizer);
            $plan = $this->plan($articles);
        } catch (Throwable $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->line('Source articles: '.count($articles));
        $this->line('Categories in source: '.count(array_unique(array_column($articles, 'category'))));
        $this->line('Will create articles: '.count($plan['create']));
        $this->line('Will skip existing articles: '.count($plan['skip']));
        foreach ($plan['skip'] as $entry) {
            $this->line('  skipped '.$entry['source']['slug'].' (already imported as '.$entry['existing']->public_id.')');
        }
        $this->line('Image files to copy: '.count($plan['images']));
        foreach ($plan['images'] as $image) {
            $this->line('  '.$image['source'].' -> public/'.$image['destination']);
        }

        if (! $this->option('apply')) {
            $this->components->info('Dry run only. Pass --apply to import into the currently configured database.');

            return self::SUCCESS;
        }

        $copied = [];
        try {
            DB::transaction(function () use ($plan, &$copied): void {
                foreach ($plan['images'] as $image) {
                    $copied[] = $image['destination'];
                    $contents = file_get_contents($image['source']);
                    if ($contents === false || ! Storage::disk('public')->put($image['destination'], $contents)) {
                        throw new RuntimeException('Could not copy image to public storage: '.$image['destination']);
                    }
                }

                foreach ($plan['categories'] as $category) {
                    NewsCategory::query()->firstOrCreate(
                        ['slug' => $category['slug']],
                        ['name' => $category['name'], 'sort_order' => $category['sort_order'], 'is_active' => true],
                    );
                }

                foreach ($plan['create'] as $article) {
                    $category = NewsCategory::query()->where('slug', $article['category_slug'])->firstOrFail();
                    $record = new NewsArticle;
                    $record->fill([
                        'category_id' => $category->id,
                        'title' => $article['title'],
                        'slug' => $article['slug'],
                        'excerpt' => $article['excerpt'],
                        'body' => $article['html'],
                        'cover_path' => 'news/'.$article['image'],
                        'cover_alt' => $article['coverAlt'],
                        'published_at' => $article['publishedAt'],
                        'status' => 'published',
                        'seo_title' => $article['seoTitle'],
                        'seo_description' => $article['seoDescription'],
                    ]);
                    $record->public_id = $article['id'];
                    $record->save();
                }
            });
        } catch (Throwable $exception) {
            foreach ($copied as $path) {
                Storage::disk('public')->delete($path);
            }
            $this->components->error('Import failed; database changes were rolled back: '.$exception->getMessage());

            return self::FAILURE;
        }

        $this->components->info('Import complete: created '.count($plan['create']).' articles, skipped '.count($plan['skip']).', copied '.count($copied).' images.');

        return self::SUCCESS;
    }

    /** @return list<array<string, mixed>> */
    private function readFixture(string $fixturePath, string $imageDirectory, NewsHtmlSanitizer $sanitizer): array
    {
        if (! is_file($fixturePath)) {
            throw new RuntimeException('Historical news fixture is missing: '.$fixturePath);
        }
        $decoded = json_decode((string) file_get_contents($fixturePath), true, flags: JSON_THROW_ON_ERROR);
        if (! is_array($decoded) || count($decoded) !== 7) {
            throw new RuntimeException('Fixture must contain exactly seven historical articles.');
        }

        $articles = [];
        $seenSlugs = [];
        $seenIds = [];
        foreach ($decoded as $article) {
            foreach (['id', 'slug', 'title', 'excerpt', 'body', 'image', 'imageSha256', 'coverAlt', 'category', 'publishedAt', 'seoTitle', 'seoDescription'] as $field) {
                if (! array_key_exists($field, $article) || $article[$field] === '' || $article[$field] === null) {
                    throw new RuntimeException('Required field is missing for a source article: '.$field);
                }
            }
            if (! preg_match('/^news-[1-7]$/', $article['id']) || ! preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $article['slug'])) {
                throw new RuntimeException('Invalid stable source ID or slug: '.$article['slug']);
            }
            if (isset($seenSlugs[$article['slug']]) || isset($seenIds[$article['id']])) {
                throw new RuntimeException('Duplicate source ID or slug: '.$article['slug']);
            }
            $seenSlugs[$article['slug']] = true;
            $seenIds[$article['id']] = true;

            $imagePath = $imageDirectory.'/'.$article['image'];
            if (! is_file($imagePath) || hash_file('sha256', $imagePath) !== $article['imageSha256']) {
                throw new RuntimeException('Image is missing or differs from the source snapshot: '.$article['image']);
            }
            $html = $this->blocksToHtml($article['body']);
            $clean = $sanitizer->sanitize($html) ?? '';
            if (blank(strip_tags($clean)) || $this->normalizedText($html) !== $this->normalizedText($clean)) {
                throw new RuntimeException('Rich-text conversion is empty or changes source text in '.$article['slug']);
            }

            $articles[] = [...$article, 'html' => $clean, 'imageSource' => $imagePath];
        }

        return $articles;
    }

    /** @param list<array<string, mixed>> $articles @return array<string, array> */
    private function plan(array $articles): array
    {
        $create = [];
        $skip = [];
        $images = [];
        $categories = [];

        foreach ($articles as $article) {
            $slugRecord = NewsArticle::query()->where('slug', $article['slug'])->first();
            $idRecord = NewsArticle::query()->where('public_id', $article['id'])->first();
            if ($slugRecord && $idRecord && $slugRecord->isNot($idRecord)) {
                throw new RuntimeException('Source slug and stable ID point to different records: '.$article['slug']);
            }
            if ($idRecord) {
                $skip[] = ['source' => $article, 'existing' => $idRecord];

                continue;
            }
            if ($slugRecord) {
                throw new RuntimeException('Slug conflict with a different article ID: '.$article['slug']);
            }

            $categorySlug = Str::slug($article['category']);
            $article['category_slug'] = $categorySlug;
            $create[] = $article;
            $categories[$categorySlug] = [
                'name' => $article['category'],
                'slug' => $categorySlug,
                'sort_order' => count($categories),
            ];

            $destination = 'news/'.$article['image'];
            if (Storage::disk('public')->exists($destination)) {
                $existingImage = Storage::disk('public')->get($destination);
                if (! hash_equals($article['imageSha256'], hash('sha256', $existingImage))) {
                    throw new RuntimeException('Destination image exists with different contents: '.$destination);
                }

                continue;
            }
            $images[$destination] = ['source' => $article['imageSource'], 'destination' => $destination];
        }

        return ['create' => $create, 'skip' => $skip, 'images' => array_values($images), 'categories' => array_values($categories)];
    }

    /** @param list<array<string, mixed>> $blocks */
    private function blocksToHtml(array $blocks): string
    {
        $html = '';
        foreach ($blocks as $block) {
            if (($block['type'] ?? null) === 'paragraph') {
                $content = '';
                foreach ($block['children'] ?? [] as $child) {
                    $text = e((string) ($child['text'] ?? ''));
                    if (($child['type'] ?? null) === 'link') {
                        $href = (string) ($child['href'] ?? '');
                        if (! preg_match('/^https?:\/\//i', $href)) {
                            throw new RuntimeException('Unsupported inline link in source body.');
                        }
                        $content .= '<a href="'.e($href).'">'.$text.'</a>';
                    } elseif (($child['type'] ?? null) === 'text') {
                        $content .= $text;
                    } else {
                        throw new RuntimeException('Unsupported inline content type in source body.');
                    }
                }
                $html .= '<p>'.$content.'</p>';

                continue;
            }
            if (($block['type'] ?? null) === 'heading' && in_array($block['level'] ?? null, [2, 3], true)) {
                $html .= '<h'.(int) $block['level'].'>'.e((string) ($block['text'] ?? '')).'</h'.(int) $block['level'].'>';

                continue;
            }
            if (($block['type'] ?? null) === 'links' && is_array($block['links'] ?? null)) {
                $html .= '<ul>';
                foreach ($block['links'] as $link) {
                    $href = (string) ($link['href'] ?? '');
                    if (! preg_match('/^https?:\/\//i', $href)) {
                        throw new RuntimeException('Unsupported link in source body.');
                    }
                    $html .= '<li><a href="'.e($href).'">'.e((string) ($link['label'] ?? '')).'</a></li>';
                }
                $html .= '</ul>';

                continue;
            }
            throw new RuntimeException('Unsupported source block in historical news fixture.');
        }

        return $html;
    }

    private function normalizedText(string $html): string
    {
        return preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8')) ?? '';
    }
}
