<?php

namespace App\Filament\Components;

use App\Models\NewsCategory;
use App\Services\NewsHtmlSanitizer;
use Filament\Forms\Components\ViewField;
use Filament\Schemas\Components\Utilities\Get;
use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

final class NewsArticlePreview
{
    public static function make(): ViewField
    {
        return ViewField::make('news_article_preview')
            ->label('Article preview')
            ->view('filament.forms.components.news-article-preview')
            ->viewData(function (Get $get): array {
                $cover = $get('cover_path');
                $coverUrl = null;

                if ($cover instanceof TemporaryUploadedFile) {
                    try {
                        $coverUrl = $cover->temporaryUrl();
                    } catch (\Throwable) {
                        // Local temporary uploads may not support temporary URLs.
                    }
                } elseif (is_string($cover) && $cover !== '') {
                    $coverUrl = Storage::disk('public')->url($cover);
                }

                return [
                    'title' => (string) ($get('title') ?? ''),
                    'publishedAt' => $get('published_at'),
                    'category' => (string) (NewsCategory::query()->whereKey($get('category_id'))->value('name') ?? ''),
                    'coverUrl' => $coverUrl,
                    'excerpt' => (string) ($get('excerpt') ?? ''),
                    'body' => app(NewsHtmlSanitizer::class)->sanitize((string) ($get('body') ?? '')),
                    'url' => rtrim((string) config('app.url'), '/').'/news/'.((string) ($get('slug') ?: '{slug}')),
                ];
            })
            ->dehydrated(false);
    }
}
