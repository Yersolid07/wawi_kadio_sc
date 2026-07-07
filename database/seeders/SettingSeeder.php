<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Setting;

class SettingSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $settings = [
            // General
            ['key' => 'site_name', 'value' => 'Resor & Alam Wawi Kadio', 'type' => 'text', 'group' => 'general'],
            ['key' => 'site_description', 'value' => 'Wawi Kadio Resort. Desa Tonsewer, Minahasa.', 'type' => 'text', 'group' => 'general'],
            
            // Hero
            ['key' => 'hero_title', 'value' => 'Resor & Alam Wawi Kadio', 'type' => 'text', 'group' => 'hero'],
            ['key' => 'hero_subtitle', 'value' => 'Nikmati Ketenangan Alam di Setiap Sudut', 'type' => 'text', 'group' => 'hero'],
            ['key' => 'hero_description', 'value' => 'Pesan kamar atau gazebo di Wawi Kadio sekarang dan nikmati liburan yang tenang.', 'type' => 'text', 'group' => 'hero'],
            ['key' => 'hero_image', 'value' => '/storage/facilities/Wawi-Kadio-Photo-1560840653.jpeg', 'type' => 'image', 'group' => 'hero'],
            
            // About
            ['key' => 'about_title', 'value' => 'Tentang Wawi Kadio', 'type' => 'text', 'group' => 'about'],
            ['key' => 'about_description', 'value' => 'Wawi Kadio adalah destinasi liburan yang menawarkan keindahan alam Tonsewer, Minahasa. Dengan berbagai fasilitas menarik, Anda dapat menikmati liburan yang tenang dan berkesan.', 'type' => 'text', 'group' => 'about'],
            
            // Contact
            ['key' => 'contact_whatsapp', 'value' => '081234567890', 'type' => 'text', 'group' => 'contact'],
            ['key' => 'contact_email', 'value' => 'info@wawikadio.com', 'type' => 'text', 'group' => 'contact'],
            ['key' => 'contact_address', 'value' => 'Desa Tonsewer, Kecamatan Tompaso Barat, Kabupaten Minahasa, Sulawesi Utara', 'type' => 'text', 'group' => 'contact'],
            ['key' => 'contact_map_embed', 'value' => 'https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d3988.756187768532!2d124.7863158145129!3d1.1578330991559817!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x32876b25ba5c4f2d%3A0x8f2d592a83350cf5!2sDesa%20Tonsewer!5e0!3m2!1sen!2sid!4v1684305012345!5m2!1sen!2sid', 'type' => 'text', 'group' => 'contact'],
            
            // Footer
            ['key' => 'footer_text', 'value' => '© ' . date('Y') . ' Wawi Kadio Resort. Desa Tonsewer, Minahasa.', 'type' => 'text', 'group' => 'general'],
        ];

        foreach ($settings as $setting) {
            Setting::updateOrCreate(['key' => $setting['key']], $setting);
        }
    }
}
