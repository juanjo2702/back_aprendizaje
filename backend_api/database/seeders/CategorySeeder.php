<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            ['name' => 'Desarrollo', 'slug' => 'desarrollo', 'icon' => 'code'],
            ['name' => 'Diseño', 'slug' => 'diseno', 'icon' => 'palette'],
            ['name' => 'Negocios', 'slug' => 'negocios', 'icon' => 'business'],
            ['name' => 'Marketing', 'slug' => 'marketing', 'icon' => 'campaign'],
            ['name' => 'Data Science', 'slug' => 'data-science', 'icon' => 'analytics'],
            ['name' => 'Idiomas', 'slug' => 'idiomas', 'icon' => 'language'],
            ['name' => 'Música', 'slug' => 'musica', 'icon' => 'music_note'],
        ];

        foreach ($categories as $category) {
            $cat = Category::firstOrCreate(['slug' => $category['slug']], $category);

            // Subcategorías para Desarrollo
            if ($cat->slug === 'desarrollo') {
                $subcategories = [
                    ['name' => 'Frontend', 'slug' => 'frontend', 'icon' => 'web', 'parent_id' => $cat->id],
                    ['name' => 'Backend', 'slug' => 'backend', 'icon' => 'dns', 'parent_id' => $cat->id],
                    ['name' => 'Mobile', 'slug' => 'mobile', 'icon' => 'smartphone', 'parent_id' => $cat->id],
                    ['name' => 'DevOps', 'slug' => 'devops', 'icon' => 'settings', 'parent_id' => $cat->id],
                ];
                foreach ($subcategories as $sub) {
                    Category::firstOrCreate(['slug' => $sub['slug']], $sub);
                }
            }

            // Subcategorías para Diseño
            if ($cat->slug === 'diseno') {
                $subcategories = [
                    ['name' => 'UI Design', 'slug' => 'ui-design', 'icon' => 'brush', 'parent_id' => $cat->id],
                    ['name' => 'UX Research', 'slug' => 'ux-research', 'icon' => 'psychology', 'parent_id' => $cat->id],
                    ['name' => 'Diseño Gráfico', 'slug' => 'diseno-grafico', 'icon' => 'format_paint', 'parent_id' => $cat->id],
                ];
                foreach ($subcategories as $sub) {
                    Category::firstOrCreate(['slug' => $sub['slug']], $sub);
                }
            }
        }
    }
}
