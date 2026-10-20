<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PageSeo extends Model
{
    protected $fillable = ['page_key', 'title', 'description', 'og_image_path'];
}
