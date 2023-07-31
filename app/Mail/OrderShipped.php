<?php

namespace App\Mail;

use App\Http\Controllers\OrderController;
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
        
        // Falta especificar tipo de comprobante

        return $this->from($auth->email)
            ->subject('FACTURA ' . $this->order->serie . ' de ' . $company->company)
            ->view('mail')
            ->attachFromStorage(
                str_replace('.xml', '.pdf', $this->order->xml),
                'FAC-' . $this->order->serie . '.pdf',
                [
                    'mime' => 'application/pdf'
                ]
            )
            ->attachFromStorage(
                $this->order->xml,
                'FAC-' . $this->order->serie . '.xml',
                [
                    'mime' => 'application/xml'
                ]
            );
    }
}
