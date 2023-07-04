<?php

namespace App\Mail;

use App\Company;
use App\Http\Controllers\ShopController;
use App\Shop;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Auth;

class RetentionShipped extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * The shop instance.
     *
     * @var Shop
     */
    protected $shop;

    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct(Shop $shop)
    {
        $this->shop = $shop;
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

        (new ShopController())->generatePdfRetention($this->shop->id);

        return $this->from($auth->email)
            ->subject('RETENCION ' . $this->shop->serie_retencion . ' de ' . $company->company)
            ->view('mail')
            ->attachFromStorage(
                str_replace('.xml', '.pdf', $this->shop->xml_retention),
                'RETENCION-' . $this->shop->serie_retencion . '.pdf',
                [
                    'mime' => 'application/pdf'
                ]
            )
            ->attachFromStorage(
                $this->shop->xml_retention,
                'RETENCION-' . $this->shop->serie_retencion . '.xml',
                [
                    'mime' => 'application/xml'
                ]
            );
    }
}
