<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class Catalogue extends Model
{
    protected $fillable = ['slug', 'title', 'description', 'pdf_path', 'cover_path', 'cover_alt', 'status', 'sort_order'];

    protected $attributes = ['status' => 'draft', 'sort_order' => 0];

    protected function casts(): array
    {
        return ['sort_order' => 'integer'];
    }

    protected static function booted(): void
    {
        static::creating(function (Catalogue $catalogue): void {
            $catalogue->public_id ??= (string) Str::uuid();
        });

        static::updating(function (Catalogue $catalogue): void {
            if ($catalogue->isDirty('public_id')) {
                throw ValidationException::withMessages(['public_id' => 'The public identifier cannot be changed.']);
            }
        });

        static::saving(function (Catalogue $catalogue): void {
            if (! in_array($catalogue->status, ['draft', 'published', 'archived'], true)) {
                throw ValidationException::withMessages(['status' => 'The selected status is invalid.']);
            }

            if ($catalogue->status === 'published') {
                if (! $catalogue->exists) {
                    throw ValidationException::withMessages(['categories' => 'Create published catalogues through CatalogueManager so their category is assigned atomically.']);
                }
                $validator = Validator::make($catalogue->getAttributes(), [
                    'slug' => ['required', 'string', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/'],
                    'title' => ['required', 'string', 'min:1'],
                    'pdf_path' => ['required', 'string', 'regex:/^(?!\/)(?![A-Za-z]:)(?!.*(?:^|\/)\.\.(?:\/|$))[A-Za-z0-9._\/-]+$/'],
                    'cover_path' => ['required', 'string', 'regex:/^(?!\/)(?![A-Za-z]:)(?!.*(?:^|\/)\.\.(?:\/|$))[A-Za-z0-9._\/-]+$/'],
                ]);
                if ($validator->fails()) {
                    throw new ValidationException($validator);
                }

                if ($catalogue->exists && ! $catalogue->categories()->exists()) {
                    throw ValidationException::withMessages(['categories' => 'A published catalogue must have at least one category.']);
                }
                if (! Storage::disk('local')->exists($catalogue->pdf_path)) {
                    throw ValidationException::withMessages(['pdf_path' => 'The PDF file must exist on the private local disk before publication.']);
                }
                if (! Storage::disk('public')->exists($catalogue->cover_path)) {
                    throw ValidationException::withMessages(['cover_path' => 'The cover file must exist on the public disk before publication.']);
                }
            }
        });
    }

    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class, 'catalogue_category');
    }
}
