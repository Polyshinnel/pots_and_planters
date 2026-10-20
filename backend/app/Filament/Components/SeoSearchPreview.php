<?php

namespace App\Filament\Components;

use App\Support\StaticPageSeo;
use Closure;
use Filament\Forms\Components\ViewField;
use Filament\Schemas\Components\Utilities\Get;

/** Reusable search result preview. State paths are provided by each consuming form. */
final class SeoSearchPreview
{
    public static function make(string $titlePath, string $descriptionPath, string $urlPath, ?Closure $urlBuilder = null): ViewField
    {
        return ViewField::make('seo_search_preview')
            ->label('Google Search Preview')
            ->view('filament.forms.components.seo-search-preview')
            ->viewData(fn (Get $get): array => [
                'title' => (string) ($get($titlePath) ?? ''),
                'description' => (string) ($get($descriptionPath) ?? ''),
                'displayUrl' => $urlBuilder
                    ? $urlBuilder((string) ($get($urlPath) ?? ''))
                    : (StaticPageSeo::url((string) $get($urlPath)) ?? 'https://potsandplanters.ae/'),
            ])
            ->dehydrated(false);
    }
}
