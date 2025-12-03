<?php

namespace App\Mail;

use App\Http\Controllers\OrderController;
use App\Models\Customer;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Auth;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use App\Models\Company;
use App\Models\Order;

class OrderShipped extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * The order instance.
     *
     * @var Order
     */
    protected $order;

    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct(Order $order)
    {
        $this->order = $order;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        $auth = Auth::user();
        $level = $auth->companyusers->first();
        $company = Company::find($level->level_id);

        (new OrderController())->generatePdf($this->order->id);

        return $this->from($auth->email)
            ->subject(($this->order->voucher_type == 1 ? 'FACTURA ' : 'NOTA DE CRÉDITO ') . $this->order->serie . ' de ' . $company->company)
            ->view('mail', ['title' => 'FACTURA ' . $this->order->serie, 'customer' => Customer::find($this->order->customer_id)->name])
            ->attachFromStorage(
                str_replace('.xml', '.pdf', $this->order->xml),
                ($this->order->voucher_type == 1 ? 'FAC-' : 'NC-') . $this->order->serie . '.pdf',
                [
                    'mime' => 'application/pdf'
                ]
            )
            ->attachFromStorage(
                $this->order->xml,
                ($this->order->voucher_type == 1 ? 'FAC-' : 'NC-') . $this->order->serie . '.xml',
                [
                    'mime' => 'application/xml'
                ]
            );
    }
}
