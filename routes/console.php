<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

use App\Models\Reservation;
use App\Notifications\FeedbackRequestNotification;
use Illuminate\Support\Facades\Schedule;

// Send Feedback Request Email 1 Day after Check-out
Schedule::call(function () {
    $yesterday = now()->subDay()->toDateString();

    $reservations = Reservation::where('check_out_date', $yesterday)
        ->whereIn('status', ['confirmed', 'completed'])
        ->with('user')
        ->get();

    foreach ($reservations as $reservation) {
        if ($reservation->user) {
            $reservation->user->notify(new FeedbackRequestNotification($reservation));
        }
    }
})->dailyAt('09:00');

// Reset Menu Items Current Stock to Daily Stock at Midnight
Schedule::call(function () {
    \Illuminate\Support\Facades\DB::table('menu_items')
        ->whereNotNull('daily_stock')
        ->update(['current_stock' => \Illuminate\Support\Facades\DB::raw('daily_stock')]);
})->dailyAt('00:00');
