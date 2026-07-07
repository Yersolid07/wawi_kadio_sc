<?php

namespace Database\Seeders;

use App\Models\MenuItem;
use Illuminate\Database\Seeder;

class MenuItemSeeder extends Seeder
{
    public function run(): void
    {
        $items = [
            // Makanan
            ['name' => 'Nasi Gudeg', 'description' => 'Nasi gudeg khas Yogya dengan ayam dan telur', 'category' => 'makanan', 'price' => 25000.00],
            ['name' => 'Sate Ayam', 'description' => 'Sate ayam dengan bumbu kacang spesial', 'category' => 'makanan', 'price' => 20000.00],
            ['name' => 'Gado-gado', 'description' => 'Salad sayuran dengan bumbu kacang', 'category' => 'makanan', 'price' => 18000.00],
            ['name' => 'Nasi Kuning', 'description' => 'Nasi kuning khas Manado dengan lauk lengkap', 'category' => 'makanan', 'price' => 22000.00],
            ['name' => 'Ikan Bakar Rica', 'description' => 'Ikan bakar bumbu rica-rica khas Minahasa', 'category' => 'makanan', 'price' => 35000.00],
            ['name' => 'Mie Cakalang', 'description' => 'Mie dengan ikan cakalang asap khas Manado', 'category' => 'makanan', 'price' => 25000.00],

            // Minuman
            ['name' => 'Es Teh Manis', 'description' => 'Es teh manis segar', 'category' => 'minuman', 'price' => 8000.00],
            ['name' => 'Es Jeruk', 'description' => 'Es jeruk peras segar', 'category' => 'minuman', 'price' => 10000.00],
            ['name' => 'Kopi Tubruk', 'description' => 'Kopi tubruk tradisional', 'category' => 'minuman', 'price' => 12000.00],
            ['name' => 'Kopi Manado', 'description' => 'Kopi arabika khas Sulawesi Utara', 'category' => 'minuman', 'price' => 15000.00],
            ['name' => 'Jus Alpukat', 'description' => 'Jus alpukat segar dengan susu', 'category' => 'minuman', 'price' => 15000.00],
            ['name' => 'Air Mineral', 'description' => 'Air mineral botol', 'category' => 'minuman', 'price' => 5000.00],

            // Snack
            ['name' => 'Pisang Goreng', 'description' => 'Pisang goreng crispy', 'category' => 'snack', 'price' => 15000.00],
            ['name' => 'Bakwan Jagung', 'description' => 'Bakwan jagung manis crispy', 'category' => 'snack', 'price' => 12000.00],
            ['name' => 'Gorengan Mix', 'description' => 'Campur gorengan: tahu, tempe, bakwan', 'category' => 'snack', 'price' => 18000.00],

            // Dessert
            ['name' => 'Es Krim Kelapa', 'description' => 'Es krim kelapa muda segar', 'category' => 'dessert', 'price' => 18000.00],
            ['name' => 'Bubur Manado', 'description' => 'Bubur tinutuan khas Manado', 'category' => 'dessert', 'price' => 20000.00],
            ['name' => 'Kue Lalampa', 'description' => 'Kue lalampa isi ikan cakalang khas Minahasa', 'category' => 'dessert', 'price' => 10000.00],
        ];

        foreach ($items as $item) {
            MenuItem::firstOrCreate(['name' => $item['name']], array_merge($item, ['is_available' => true]));
        }
    }
}
