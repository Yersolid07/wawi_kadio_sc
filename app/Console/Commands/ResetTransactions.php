<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ResetTransactions extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:reset-transactions {--force : Force the operation to run without confirmation}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Reset all transaction data (reservations, orders, payments, financial transactions, reviews) for production restart.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        if (!$this->option('force')) {
            if (!$this->confirm('Are you sure you want to delete ALL transaction data? This action cannot be undone.')) {
                $this->info('Operation cancelled.');
                return;
            }
        }

        $this->info('Resetting transaction data...');

        DB::statement('SET FOREIGN_KEY_CHECKS=0;');

        // Truncate tables
        DB::table('payments')->truncate();
        DB::table('financial_transactions')->truncate();
        DB::table('food_order_items')->truncate();
        DB::table('food_orders')->truncate();
        DB::table('pos_closings')->truncate();
        
        // Remove related addons
        if (Schema::hasTable('reservation_addons')) {
            DB::table('reservation_addons')->truncate();
        }
        
        DB::table('reservations')->truncate();
        DB::table('reviews')->truncate();

        // Reset menu items stock if any to its initial state or leave as is?
        // Let's just leave menu items and facilities alone as they are master data.
        // Wait, daily_stock is reset automatically or can be manually updated, but let's reset current_stock
        DB::table('menu_items')->whereNotNull('daily_stock')->update([
            'current_stock' => DB::raw('daily_stock')
        ]);

        DB::statement('SET FOREIGN_KEY_CHECKS=1;');

        $this->info('All transaction data has been successfully deleted.');
        $this->info('Menu items current_stock has been reset to their daily_stock value.');
        $this->info('You are ready for production!');
    }
}
