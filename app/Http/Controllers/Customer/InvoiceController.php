<?php
namespace App\Http\Controllers\Customer;
use App\Http\Controllers\Controller;
use App\Models\Reservation;
use App\Models\FoodOrder;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
class InvoiceController extends Controller
{
    public function reservation(Reservation $reservation)
    {
        Gate::authorize('view', $reservation);
        $reservation->load(['facility', 'user', 'payment']);
        
        return Inertia::render('Customer/Reservations/Print', [
            'reservation' => $reservation,
            'company' => [
                'name' => 'Wawi Kadio Resort',
                'address' => 'Desa Tonsewer, Kec. Tompaso Barat, Minahasa',
                'phone' => '+62 812-3456-7890',
                'email' => 'hello@wawikadio.com'
            ]
        ]);
    }
    
    public function foodOrder(FoodOrder $order)
    {
        if ($order->user_id !== auth()->id()) abort(403);
        $order->load(['user', 'items.menuItem', 'reservation.facility', 'payment']);
        
        return Inertia::render('Customer/FoodOrders/Print', [
            'order' => $order,
            'company' => [
                'name' => 'Wawi Kadio Resto',
                'address' => 'Desa Tonsewer, Kec. Tompaso Barat, Minahasa',
                'phone' => '+62 812-3456-7890',
                'email' => 'hello@wawikadio.com'
            ]
        ]);
    }
}
