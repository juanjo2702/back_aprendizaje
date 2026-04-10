<?php

namespace Database\Seeders;

use App\Models\CertificateTemplate;
use Illuminate\Database\Seeder;

class CertificateTemplateSeeder extends Seeder
{
    public function run(): void
    {
        $templates = [
            [
                'name' => 'Plantilla Clásica',
                'background_image' => 'https://images.unsplash.com/photo-1589998059171-988d887df646?ixlib=rb-4.0.3&auto=format&fit=crop&w=800&q=80',
                'template_config' => [
                    'title_font' => 'Times New Roman',
                    'title_size' => 36,
                    'title_color' => '#2c3e50',
                    'body_font' => 'Arial',
                    'body_size' => 18,
                    'body_color' => '#34495e',
                    'signature_position' => 'right',
                    'logo_position' => 'top-left',
                ],
                'is_default' => true,
            ],
            [
                'name' => 'Plantilla Moderna',
                'background_image' => 'https://images.unsplash.com/photo-1557683316-973673baf926?ixlib=rb-4.0.3&auto=format&fit=crop&w=800&q=80',
                'template_config' => [
                    'title_font' => 'Helvetica',
                    'title_size' => 40,
                    'title_color' => '#1a237e',
                    'body_font' => 'Roboto',
                    'body_size' => 16,
                    'body_color' => '#37474f',
                    'signature_position' => 'center',
                    'logo_position' => 'top-center',
                ],
                'is_default' => false,
            ],
            [
                'name' => 'Plantilla Minimalista',
                'background_image' => null,
                'template_config' => [
                    'title_font' => 'Montserrat',
                    'title_size' => 32,
                    'title_color' => '#000000',
                    'body_font' => 'Open Sans',
                    'body_size' => 14,
                    'body_color' => '#333333',
                    'signature_position' => 'left',
                    'logo_position' => 'none',
                ],
                'is_default' => false,
            ],
        ];

        foreach ($templates as $template) {
            CertificateTemplate::firstOrCreate(['name' => $template['name']], $template);
        }
    }
}
