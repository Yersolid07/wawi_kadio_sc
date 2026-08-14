<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::table('facilities')->insertOrIgnore([
            'name' => 'Tiket Masuk',
            'type' => 'ticket',
            'description' => 'Tiket masuk harian untuk pengunjung Wawi Kadio.',
            'price_per_day' => 10000,
            'capacity' => 1,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('facilities', function (Blueprint $table) {
            //
        });
    }
};
