<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Auth;
use App\Models\Company;
use App\Models\Shop;
use App\Models\ShopItem;
use App\StaticClasses\VoucherStates;
use stdClass;

class SettlementOnPurchaseXmlController extends Controller
{
    public function download($id)
    {
        $shop = Shop::findOrFail($id);

        return response()->json([
            'xml' => base64_encode(Storage::get($shop->xml))
        ]);
    }

    public function xml($id)
    {
        $auth = Auth::user();
        $level = $auth->companyusers->first();
        $company = Company::find($level->level_id);

        if (!$company->active_voucher) {
            return;
        }

        $shop = Shop::join('providers AS p', 'p.id', 'shops.provider_id')
            ->select('p.identication', 'p.name', 'p.address', 'p.type_identification', 'shops.*')
            ->where('shops.id', $id)
            ->first();

        $shop_items = ShopItem::select('p.id', 'p.code', 'p.name', 'shop_items.quantity', 'shop_items.price', 'shop_items.iva')
            ->join('products AS p', 'p.id', 'product_id')
            ->where('shop_id', $id)
            ->get();

        $str_xml_voucher = null;

        if ((int) $shop->voucher_type === 3) {
            $str_xml_voucher = $this->purchasesettlement($shop, $company, $shop_items);
            $this->sign($company, $shop, $str_xml_voucher);
        }
    }

    private function sign($company, $order, $str_xml_voucher)
    {
        $file = substr($str_xml_voucher, strpos($str_xml_voucher, '<claveAcceso>') + 13, 49) . '.xml';
        $date = new \DateTime($order->date);

        $rootfile = 'xmls' . DIRECTORY_SEPARATOR . $company->ruc . DIRECTORY_SEPARATOR .
            $date->format('Y') . DIRECTORY_SEPARATOR .
            $date->format('m');

        $folder = $rootfile . DIRECTORY_SEPARATOR . VoucherStates::SAVED . DIRECTORY_SEPARATOR;

        Storage::put($folder . $file, $str_xml_voucher);

        if (file_exists(Storage::path($folder . $file))) {
            $order->xml = $folder . $file;
            $order->extra_detail = null;
            $order->save();
        }

        //Signner Start --------------------------
        // Si existe el certificado electronico y se ha creado Xml
        if ($company->cert_dir !== null && file_exists(Storage::path($folder . $file))) {

            $public_path = env('APP_URL');

            $cert = Storage::path('cert' . DIRECTORY_SEPARATOR . $company->cert_dir);

            // Si no existe la capeta FIRMADO entonces Crear
            if (!file_exists(Storage::path($rootfile . DIRECTORY_SEPARATOR . VoucherStates::SIGNED))) {
                Storage::makeDirectory($rootfile . DIRECTORY_SEPARATOR . VoucherStates::SIGNED);
            }

            $newrootfile = Storage::path($rootfile);
            $java_firma = "java -jar $public_path/public/Firma/dist/Firma.jar $cert $company->pass_cert $newrootfile/CREADO/$file $newrootfile/FIRMADO $file";
            $variable = system($java_firma);

            // Si se creo el archivo FIRMADO entonces guardar estado FIRMADO Y el nuevo path XML
            if (file_exists(Storage::path($rootfile . DIRECTORY_SEPARATOR . VoucherStates::SIGNED . DIRECTORY_SEPARATOR . $file))) {
                $order->xml = $rootfile . DIRECTORY_SEPARATOR . VoucherStates::SIGNED . DIRECTORY_SEPARATOR . $file;
                $order->state = VoucherStates::SIGNED;
                $order->save();
                (new WSSriSettlementOnPurchaseController())->send($order->id);
            }
        }
    }

    private function purchasesettlement($sale, $company, $sale_items)
    {
        $buyer_id = $sale->identication;

        $typeId = '';
        switch ($sale->type_identification) {
            case 'cédula':
                $typeId = '05';
                break;
            case 'pasaporte':
                $typeId = '06';
                break;
        }

        $string = '';
        $string .= '<?xml version="1.0" encoding="UTF-8"?>';
        $string .= '<liquidacionCompra id="comprobante" version="1.' . ($company->decimal > 2 ? 1 : 0) . '.0">';

        $string .= $this->infoTributaria($company, $sale);

        $string .= '<infoLiquidacionCompra>';

        $date = new \DateTime($sale->date);
        $string .= '<fechaEmision>' . $date->format('d/m/Y') . '</fechaEmision>';
        // $string .= '<dirEstablecimiento>null</dirEstablecimiento>';
        $string .= '<obligadoContabilidad>' . ($company->accounting ? 'SI' : 'NO') . '</obligadoContabilidad>';
        $string .= "<tipoIdentificacionProveedor>$typeId</tipoIdentificacionProveedor>";
        $string .= "<razonSocialProveedor>$sale->name</razonSocialProveedor>";
        $string .= "<identificacionProveedor>$buyer_id</identificacionProveedor>";
        // $string .= '<direccionProveedor>' . $sale->address . '</direccionProveedor>';
        $string .= "<totalSinImpuestos>$sale->sub_total</totalSinImpuestos>";
        $string .= "<totalDescuento>$sale->discount</totalDescuento>";

        // Aplied only tax to IVA, NOT aplied to IRBPNR % Imp. al Cons Esp, require add
        $string .= '<totalConImpuestos>';
        foreach ($this->grupingTaxes($sale_items) as $tax) {
            $string .= "<totalImpuesto>";
            $string .= "<codigo>2</codigo>";    // Aplied only tax to IVA
            $string .= "<codigoPorcentaje>$tax->percentageCode</codigoPorcentaje>";
            $string .= "<baseImponible>" . number_format($tax->base, 2, '.', '') . "</baseImponible>";
            $string .= "<tarifa>$tax->percentage</tarifa>";
            $string .= "<valor>" . round($tax->base * $tax->percentage / 100, 2) . "</valor>";
            $string .= "</totalImpuesto>";
        }

        $string .= '</totalConImpuestos>';

        $string .= '<importeTotal>' . round($sale->total, 2) . '</importeTotal>';
        $string .= '<moneda>DOLAR</moneda>';

        $string .= '<pagos>';
        $string .= '<pago>';
        $string .= '<formaPago>20</formaPago>';
        $string .= "<total>$sale->total</total>";
        $string .= "<plazo>$sale->total</plazo>";
        $string .= '</pago>';
        $string .= '</pagos>';

        $string .= '</infoLiquidacionCompra>';

        $string .= '<detalles>';
        foreach ($sale_items as $detail) {
            $total = round($detail->quantity * $detail->price, 2);
            $percentage = $detail->iva === 4 ? 15 : 0;

            $string .= "<detalle>";

            $string .= "<codigoPrincipal>" . $detail->code . "</codigoPrincipal>";
            $string .= "<descripcion>" . $detail->name . "</descripcion>";
            $string .= "<cantidad>" . round($detail->quantity, $company->decimal) . "</cantidad>";
            $string .= "<precioUnitario>" . round($detail->price, $company->decimal) . "</precioUnitario>";
            $string .= "<descuento>0.00</descuento> ";
            $string .= "<precioTotalSinImpuesto>" . $total . "</precioTotalSinImpuesto>";

            $string .= "<impuestos>";
            // foreach ($this->taxes as $tax) {
            $string .= "<impuesto>";
            $string .= "<codigo>2</codigo>";
            $string .= "<codigoPorcentaje>" . $detail->iva . "</codigoPorcentaje>";
            $string .= "<tarifa>$percentage</tarifa>";
            $string .= "<baseImponible>" . $total . "</baseImponible>";
            $string .= "<valor>" . round($percentage * $total * .01, 2) . "</valor>";
            $string .= "</impuesto>";
            // }
            $string .= "</impuestos>";

            $string .= "</detalle>";
        }

        $string .= '</detalles>';
        $string .= '</liquidacionCompra>';

        return $string;
    }

    public function grupingTaxes($order_items)
    {
        $taxes = array();
        foreach ($order_items as $tax) {
            $sub_total = number_format($tax->quantity * $tax->price, 2, '.', '');
            $total = $sub_total + $tax->valice - $tax->discount;
            $percentage = $tax->iva === 4 ? 15 : 0;

            $tax->code = 2;
            $tax->percentageCode = $tax->iva;
            $tax->percentage = $percentage;

            // Impuesto al IVA
            $gruping = $this->grupingExist($taxes, $tax);
            if ($gruping !== -1) {
                // Solo sumar el total a la base
                $taxes[$gruping]->base += $total;
            } else {
                $taxIva = new stdClass;
                $taxIva->code = 2;
                $taxIva->percentageCode = $tax->iva;
                $taxIva->percentage = $tax->percentage;
                $taxIva->base = $total;
                $taxes[] = $taxIva;
            }

            // Impuesto al ICE
            if ($tax->codice !== null && $tax->valice > 0) {
                $taxIce = new stdClass;
                $taxIce->code = 3;
                $taxIce->percentageCode = $tax->codice;

                $gruping = $this->grupingExist($taxes, $taxIce);
                if ($gruping !== -1) {
                    // Solo sumar el sub_total a la base
                    $taxes[$gruping]->base += $sub_total;
                } else {
                    $taxIce->base = $sub_total;
                    $taxes[] = $taxIce;
                }
            }
        }

        return $taxes;
    }

    private function grupingExist($taxes, $tax)
    {
        $result = -1;
        $i = 0;
        while ($i < count($taxes) && $result == -1) {
            if (
                // code: 2 IVA - 3 ICE
                $taxes[$i]->code == $tax->code &&
                // percentageCode: 0 - 2 - 6 IVA - 3092... ICE
                $taxes[$i]->percentageCode === $tax->percentageCode
            ) {
                $result = $i;
            }
            $i++;
        }
        return $result;
    }

    private function infoTributaria($company, $order)
    {
        $branch = Branch::where('company_id', $company->id)
            ->orderBy('created_at')->first();

        $voucher_type = str_pad($order->voucher_type, 2, '0', STR_PAD_LEFT);

        $serie = str_replace('-', '', $order->serie);

        $keyaccess = (new \DateTime($order->date))->format('dmY') . $voucher_type .
            $company->ruc . $company->enviroment_type . $serie
            . '123456781';

        $string = '';
        $string .= '<infoTributaria>';
        $string .= '<ambiente>' . $company->enviroment_type . '</ambiente>';
        $string .= '<tipoEmision>1</tipoEmision>';
        $string .= '<razonSocial>' . $company->company . '</razonSocial>';
        $string .= $branch->name !== null ? '<nombreComercial>' . $branch->name . '</nombreComercial>' : null;
        $string .= '<ruc>' . $company->ruc . '</ruc>';
        $string .= '<claveAcceso>' . $keyaccess . $this->generaDigitoModulo11($keyaccess) . '</claveAcceso>';
        $string .= '<codDoc>' . $voucher_type . '</codDoc>';
        $string .= '<estab>' . substr($serie, 0, 3) . '</estab>';
        $string .= '<ptoEmi>' . substr($serie, 3, 3) . '</ptoEmi>';
        $string .= '<secuencial>' . substr($serie, 6, 9) . '</secuencial>';
        $string .= '<dirMatriz>' . $branch->address . '</dirMatriz>';

        $string .= (int) $company->retention_agent === 1 ? '<agenteRetencion>1</agenteRetencion>' : null;
        $string .= (int) $company->rimpe === 1 ? '<contribuyenteRimpe>CONTRIBUYENTE RÉGIMEN RIMPE</contribuyenteRimpe>' : null;
        $string .= (int) $company->rimpe === 2 ? '<contribuyenteRimpe>CONTRIBUYENTE NEGOCIO POPULAR - RÉGIMEN RIMPE</contribuyenteRimpe>' : null;

        $string .= '</infoTributaria>';

        return $string;
    }

    public function generaDigitoModulo11($cadena)
    {
        $cadena = trim($cadena);
        $baseMultiplicador = 7;
        $aux = new \SplFixedArray(strlen($cadena));
        $aux = $aux->toArray();
        $multiplicador = 2;
        $total = 0;
        $verificador = 0;
        for ($i = count($aux) - 1; $i >= 0; --$i) {
            $aux[$i] = substr($cadena, $i, 1);
            $aux[$i] *= $multiplicador;
            ++$multiplicador;
            if ($multiplicador > $baseMultiplicador) {
                $multiplicador = 2;
            }
            $total += $aux[$i];
        }
        if (($total == 0) || ($total == 1))
            $verificador = 0;
        else {
            $verificador = (11 - ($total % 11) == 11) ? 0 : 11 - ($total % 11);
        }
        if ($verificador == 10) {
            $verificador = 1;
        }
        return $verificador;
    }
}
