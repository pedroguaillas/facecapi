<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Mail\OrderShipped;
use App\Mail\RetentionShipped;
use App\Models\Order;
use App\Models\Provider;
use App\Models\Shop;
use Illuminate\Support\Facades\Mail;

class MailController extends Controller
{
    // Enviar factura o nota de credito por correo
    public function orderMail(int $id)
    {
        $order = Order::find($id);
        $customer = Customer::find($order->customer_id);

        Mail::to($customer->email)->send(new OrderShipped($order));

        $order->update([
            'send_mail' => true
        ]);
    }

    // Enviar retencion por correo
    public function retentionMail(int $id)
    {
        $shop = Shop::find($id);
        $provider = Provider::find($shop->provider_id);

        Mail::to($provider->email)->send(new RetentionShipped($shop));

        $shop->update([
            'send_mail_retention' => true
        ]);
    }
}
