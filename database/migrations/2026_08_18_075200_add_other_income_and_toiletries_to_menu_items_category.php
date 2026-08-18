<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // For MySQL, alter the enum column to include new categories
        DB::statement("ALTER TABLE menu_items MODIFY COLUMN category ENUM('makanan', 'minuman', 'snack', 'dessert', 'tiket', 'other_income', 'toiletries') NOT NULL");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE menu_items MODIFY COLUMN category ENUM('makanan', 'minuman', 'snack', 'dessert', 'tiket') NOT NULL");
    }
};
