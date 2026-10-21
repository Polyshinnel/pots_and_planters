<?php

namespace App\Services;

use App\Models\Catalogue;
use App\Models\Category;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * Supported transactional write path for catalogue content and category links.
 * File bytes must be provisioned before calling; cleanup belongs after commit in BE-04.2.
 */
class CatalogueManager
{
    /** @param array<string, mixed> $attributes @param list<int> $categoryIds */
    public function save(array $attributes, array $categoryIds, ?Catalogue $catalogue = null): Catalogue
    {
        $validator = Validator::make(['category_ids' => $categoryIds], [
            'category_ids' => ['array'],
            'category_ids.*' => ['integer', 'distinct', 'exists:categories,id'],
        ]);
        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        return DB::transaction(function () use ($attributes, $categoryIds, $catalogue): Catalogue {
            // Lock category rows in a stable order so publication/link changes serialize
            // with category deletion, which takes the same locks before checking usage.
            $categoryIds = array_values(array_unique($categoryIds));
            sort($categoryIds);
            if ($categoryIds !== []) {
                Category::query()->whereKey($categoryIds)->orderBy('id')->lockForUpdate()->get();
            }
            $record = $catalogue
                ? Catalogue::query()->lockForUpdate()->findOrFail($catalogue->getKey())
                : new Catalogue;
            $attributes['sort_order'] ??= $record->sort_order ?? 0;
            $attributes['status'] ??= $record->status ?: 'draft';
            $record->fill($attributes);

            $pathValidator = Validator::make($record->getAttributes(), [
                'pdf_path' => ['nullable', 'string', 'regex:/^(?!\/)(?![A-Za-z]:)(?!.*(?:^|\/)\.\.(?:\/|$))[A-Za-z0-9._\/-]+$/'],
                'cover_path' => ['nullable', 'string', 'regex:/^(?!\/)(?![A-Za-z]:)(?!.*(?:^|\/)\.\.(?:\/|$))[A-Za-z0-9._\/-]+$/'],
            ]);
            if ($pathValidator->fails()) {
                throw new ValidationException($pathValidator);
            }

            if ($record->status === 'published') {
                $this->assertPublishable($record, $categoryIds);
            }

            $targetStatus = $record->status;
            if ($targetStatus === 'published') {
                $record->status = 'draft';
            }
            $record->save();
            $record->categories()->sync($categoryIds);
            if ($record->status !== $targetStatus) {
                $record->status = $targetStatus;
                $record->save();
            }

            return $record->load('categories');
        });
    }

    /** @param list<int> $categoryIds */
    private function assertPublishable(Catalogue $catalogue, array $categoryIds): void
    {
        $validator = Validator::make($catalogue->getAttributes(), [
            'slug' => ['required', 'string', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/'],
            'title' => ['required', 'string', 'min:1'],
            'pdf_path' => ['required', 'string', 'regex:/^(?!\/)(?![A-Za-z]:)(?!.*(?:^|\/)\.\.(?:\/|$))[A-Za-z0-9._\/-]+$/'],
            'cover_path' => ['required', 'string', 'regex:/^(?!\/)(?![A-Za-z]:)(?!.*(?:^|\/)\.\.(?:\/|$))[A-Za-z0-9._\/-]+$/'],
        ]);
        if ($validator->fails()) {
            throw new ValidationException($validator);
        }
        if ($categoryIds === []) {
            throw ValidationException::withMessages(['category_ids' => 'A published catalogue must have at least one category.']);
        }
        if (! Storage::disk('local')->exists($catalogue->pdf_path)) {
            throw ValidationException::withMessages(['pdf_path' => 'The PDF file must exist on the private local disk before publication.']);
        }
        if (! Storage::disk('public')->exists($catalogue->cover_path)) {
            throw ValidationException::withMessages(['cover_path' => 'The cover file must exist on the public disk before publication.']);
        }
    }
}
