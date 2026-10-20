<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        foreach (['outdoor-pots.webp', 'indoor-planters.webp', 'artificial-plants.webp'] as $image) {
            $path = 'categories/'.$image;
            if (! Storage::disk('public')->exists($path)) {
                Storage::disk('public')->put($path, file_get_contents(database_path('seeders/assets/categories/'.$image)));
            }
        }

        $categories = [
            ['public_id'=>'outdoor','slug'=>'outdoor-pots','name'=>'Outdoor Pots','heading'=>'Outdoor Planters for Projects Across the UAE','description'=>'Explore pots and planters for terraces, entrances and open-air spaces.','tile_description'=>'For terraces, entrances and open-air spaces.','intro_heading'=>'Outdoor Planters for Hotels, Villas and Commercial Projects','intro_paragraphs'=>['Explore outdoor pots and planters for terraces, entrances, landscaped areas and hospitality spaces across Dubai and the UAE. Our collection includes a variety of shapes, finishes and sizes suitable for different architectural concepts.','We work with designers, architects and project teams to help select planters that complement the surrounding space. Browse the collection or contact our team to discuss product specifications and project requirements.'],'image_path'=>'categories/outdoor-pots.webp','image_alt'=>'Three textured outdoor planters with greenery on a timber terrace','seo_title'=>'Outdoor Planters in Dubai & UAE | Pots & Planters','seo_description'=>'Explore pots and planters for terraces, entrances and open-air spaces.','sort_order'=>1],
            ['public_id'=>'indoor','slug'=>'indoor-pots','name'=>'Indoor Pots','heading'=>'Indoor Planters for Projects Across the UAE','description'=>'Explore decorative pots and sculptural forms for interiors.','tile_description'=>'Forms and finishes for considered interiors.','intro_heading'=>'Indoor Planters for Hotels, Offices and Residential Interiors','intro_paragraphs'=>['Explore our collection of indoor pots and decorative planters for hotels, restaurants, offices, private villas and residential interiors across Dubai and the UAE. Discover a variety of shapes, sizes and finishes designed to complement contemporary, minimalist and classic spaces.','We work with interior designers, architects and project teams to help select planters that suit their design concepts and spatial requirements. Whether you are furnishing a hotel lobby, creating greenery for an office or completing a residential project, our team can assist with product selection and specifications.'],'image_path'=>'categories/indoor-planters.webp','image_alt'=>'Decorative indoor planters with flowering plants in a bright interior','seo_title'=>'Indoor Planters Supplier in UAE | Pots & Planters','seo_description'=>'Explore decorative pots and sculptural forms for interiors.','sort_order'=>2],
            ['public_id'=>'artificial','slug'=>'artificial-plants','name'=>'Artificial Plants & Trees','heading'=>'Artificial Plants & Trees for UAE Projects','description'=>'Explore sample greenery for interior projects.','tile_description'=>'Explore lifelike plants and trees.','intro_heading'=>'Artificial Plants & Trees for Commercial and Residential Projects','intro_paragraphs'=>['Discover artificial plants and decorative trees for hotels, restaurants, offices, retail spaces and private residences across Dubai and the UAE. Our collection offers greenery solutions for interiors and other suitable spaces where natural plants may be difficult to maintain or where a consistent appearance is preferred.','We help architects, interior designers and project teams select artificial greenery that complements their spaces and design requirements. Explore decorative plants, trees and arrangements for hospitality, commercial and residential projects, or contact our team to discuss suitable options for your project.'],'image_path'=>'categories/artificial-plants.webp','image_alt'=>'Potted artificial trees arranged in a contemporary interior','seo_title'=>'Artificial Plants & Trees in Dubai, UAE | Pots & Planters','seo_description'=>'Explore sample greenery for interior projects.','sort_order'=>3],
        ];

        foreach ($categories as $category) {
            Category::firstOrCreate(['public_id' => $category['public_id']], [...$category, 'status' => 'published']);
        }
    }
}
