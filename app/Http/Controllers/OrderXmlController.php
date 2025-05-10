<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Auth;
use App\Models\Company;
use App\Models\Lot;
use App\Models\Order;
use App\Models\OrderAditional;
use App\Models\OrderItem;
use App\StaticClasses\VoucherStates;
use stdClass;

class OrderXmlController extends Controller
{
    public function download($id)
    {
        $order = Order::findOrFail($id);

        return response()->json([
            'xml' => base64_encode(Storage::get($order->xml))
        ]);
    }

    public function xml($id)
    {
        $auth = Auth::user();
        $level = $auth->companyusers->first();
        $company = Company::find($level->level_id);

        if (!$company->active_voucher)
            return;

        $order = Order::join('customers AS c', 'c.id', 'customer_id')
            ->select('c.identication', 'c.name', 'c.address', 'c.type_identification', 'orders.*')
            ->where('orders.id', $id)
            ->first();

        if ($order->state === 'DEVUELTA' && $order->extra_detail === 'CLAVE ACCESO REGISTRADA.') {
            (new WSSriOrderController())->authorize($id);
            return;
        }

        $order_items = OrderItem::join('products AS p', 'p.id', 'product_id')
            ->join('iva_taxes AS it', 'it.code', 'order_items.iva')
            ->selectRaw('quantity,price,discount,order_items.ice AS valice,p.code AS codeproduct,aux_cod,name,it.code AS iva,it.percentage,p.ice AS codice')
            ->where('order_id', $id)
            ->get();

        $str_xml_voucher = null;

        switch ($order->voucher_type) {
            case 1:
                $str_xml_voucher = $this->invoice($order, $company, $order_items);
                break;
            case 4:
                $str_xml_voucher = $this->creditNote($order, $company, $order_items);
                break;
        }

        $this->sign($company, $order, $str_xml_voucher);
    }

    private function sign($company, $order, $str_xml_voucher)
    {
        $order->authorization = substr($str_xml_voucher, strpos($str_xml_voucher, '<claveAcceso>') + 13, 49);
        $file = $order->authorization . '.xml';
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

            // Si no existe la carpeta FIRMADO entonces Crear
            if (!file_exists(Storage::path($rootfile . DIRECTORY_SEPARATOR . VoucherStates::SIGNED))) {
                Storage::makeDirectory($rootfile . DIRECTORY_SEPARATOR . VoucherStates::SIGNED);
            }

            $newrootfile = Storage::path($rootfile);

            // $java_firma = "java -jar public\Firma\dist\Firma.jar $cert $company->pass_cert $rootfile\\CREADO\\$file $rootfile\\FIRMADO $file";
            $java_firma = "java -jar $public_path/public/Firma/dist/Firma.jar $cert " . $company->pass_cert . " $newrootfile/CREADO/$file $newrootfile/FIRMADO $file";

            system($java_firma);

            // Si se creo el archivo FIRMADO entonces guardar estado FIRMADO Y el nuevo path XML
            if (file_exists(Storage::path($rootfile . DIRECTORY_SEPARATOR . VoucherStates::SIGNED . DIRECTORY_SEPARATOR . $file))) {
                $order->xml = $rootfile . DIRECTORY_SEPARATOR . VoucherStates::SIGNED . DIRECTORY_SEPARATOR . $file;
                $order->state = VoucherStates::SIGNED;
                // Elimina el archivo CREADO
                Storage::delete($rootfile . DIRECTORY_SEPARATOR . VoucherStates::SAVED . DIRECTORY_SEPARATOR . $file);
                $order->save();

                (new WSSriOrderController())->send($order->id);
            }
        }
    }

    private function creditNote($order, $company, $order_items)
    {
        $buyer_id = $order->identication;

        $typeId = '';
        switch ($order->type_identification) {
            case 'ruc':
                $typeId = '04';
                break;
            case 'cédula':
                $typeId = '05';
                break;
            case 'pasaporte':
                $typeId = '06';
                break;
        }

        $nombrecliente = str_replace("&", "Y", $order->name);

        $string = '';
        $string .= '<?xml version="1.0" encoding="UTF-8"?>';
        $string .= '<notaCredito id="comprobante" version="1.' . ($company->decimal > 2 ? 1 : 0) . '.0">';

        $string .= $this->infoTributaria($company, $order);

        $string .= '<infoNotaCredito>';

        $date = new \DateTime($order->date);
        $string .= '<fechaEmision>' . $date->format('d/m/Y') . '</fechaEmision>';
        $string .= "<tipoIdentificacionComprador>$typeId</tipoIdentificacionComprador>";
        $string .= "<razonSocialComprador>$nombrecliente</razonSocialComprador>";
        $string .= "<identificacionComprador>$buyer_id</identificacionComprador>";
        $string .= '<obligadoContabilidad>' . ($company->accounting ? 'SI' : 'NO') . '</obligadoContabilidad>';
        // $string .= '<direccionComprador>' . $order->address . '</direccionComprador>';

        // Only Credit Note Start ................................

        $string .= '<codDocModificado>01</codDocModificado>';
        $string .= "<numDocModificado>$order->serie_order</numDocModificado>";

        $date_order = new \DateTime($order->date_order);
        $string .= '<fechaEmisionDocSustento>' . $date_order->format('d/m/Y') . '</fechaEmisionDocSustento>';

        $string .= "<totalSinImpuestos>$order->sub_total</totalSinImpuestos>";
        $string .= "<valorModificacion>$order->total</valorModificacion>";

        // Only Credit Note End ..................................

        $string .= '<moneda>DOLAR</moneda>';
        // Aplied only tax to IVA, NOT aplied to IRBPNR % Imp. al Cons Esp, require add
        $string .= '<totalConImpuestos>';

        foreach ($this->grupingTaxes($order_items) as $tax) {
            $string .= "<totalImpuesto>";
            $string .= "<codigo>$tax->code</codigo>";    // 2 IVA - 3 ICE
            $string .= "<codigoPorcentaje>$tax->percentageCode</codigoPorcentaje>";
            $string .= "<baseImponible>" . number_format($tax->base, 2, '.', '') . "</baseImponible>";
            // Solo en caso del impuesto al IVA poner la <tarifa>
            // $string .= "<tarifa>$tax->percentage</tarifa>";
            // El valor varia en dependecia del impuesto
            $string .= "<valor>" . ($tax->code === 2 ? round($tax->base * $tax->percentage / 100, 2) : $order->ice) . "</valor>";
            // $string .= "<valor>" . ($tax->code === 2 ? ($tax->percentage === 12 ? $order->iva : 0) : $order->ice) . "</valor>";
            $string .= "</totalImpuesto>";
        }

        $string .= '</totalConImpuestos>';

        $string .= "<motivo>$order->reason</motivo>";

        $string .= '</infoNotaCredito>';

        $string .= '<detalles>';
        foreach ($order_items as $detail) {
            $sub_total = $detail->quantity * $detail->price;
            // $discount = round($sub_total * $detail->discount * .01, 2);
            $total = round($sub_total + $detail->valice - $detail->discount, 2);
            // $percentage = $detail->iva === 2 ? 12 : 0;

            $string .= "<detalle>";

            $string .= "<codigoInterno>$detail->codeproduct</codigoInterno>";
            $string .= "<descripcion>$detail->name</descripcion>";
            $string .= "<cantidad>" . round($detail->quantity, $company->decimal) . "</cantidad>";
            $string .= "<precioUnitario>" . round($detail->price, $company->decimal) . "</precioUnitario>";
            $string .= "<descuento>$detail->discount</descuento>";
            $string .= "<precioTotalSinImpuesto>" . round($total, 2) . "</precioTotalSinImpuesto>";

            $string .= "<impuestos>";

            $string .= "<impuesto>";
            $string .= "<codigo>2</codigo>";
            $string .= "<codigoPorcentaje>$detail->iva</codigoPorcentaje>";
            $string .= "<tarifa>$detail->percentage</tarifa>";
            $string .= "<baseImponible>" . round($total, 2) . "</baseImponible>";
            $string .= "<valor>" . round($detail->percentage * $total * .01, 2) . "</valor>";
            $string .= "</impuesto>";

            // Impuesto del Monto ICE opcional
            if ($detail->codice) {
                $string .= "<impuesto>";
                $string .= "<codigo>3</codigo>";    // Aplica solo para impuesto del Monto ICE
                $string .= "<codigoPorcentaje>$detail->codice</codigoPorcentaje>";
                $string .= "<tarifa>0</tarifa>";
                $string .= "<baseImponible>" . number_format($sub_total, 2, '.', '') . "</baseImponible>";
                $string .= "<valor>$detail->valice</valor>";
                $string .= "</impuesto>";
            }

            $string .= "</impuestos>";

            $string .= "</detalle>";
        }

        $string .= '</detalles>';

        $string .= '</notaCredito>';

        return $string;
    }

    private function invoice($order, $company, $order_items)
    {
        $buyer_id = $order->identication;

        $typeId = '';
        switch ($order->type_identification) {
            case 'ruc':
                $typeId = '04';
                break;
            case 'cédula':
                $typeId = '05';
                break;
            case 'pasaporte':
                $typeId = '06';
                break;
            case 'cf':
                $typeId = '07';
                break;
        }

        $string = '';
        $string .= '<?xml version="1.0" encoding="UTF-8"?>';
        $string .= '<factura id="comprobante" version="1.' . ($company->decimal > 2 ? 1 : 0) . '.0">';

        $string .= $this->infoTributaria($company, $order);
        $string .= '<infoFactura>';

        $date = new \DateTime($order->date);
        $string .= '<fechaEmision>' . $date->format('d/m/Y') . '</fechaEmision>';
        $string .= '<obligadoContabilidad>' . ($company->accounting ? 'SI' : 'NO') . '</obligadoContabilidad>';
        $string .= "<tipoIdentificacionComprador>$typeId</tipoIdentificacionComprador>";
        $string .= $order->guia !== null ? '<guiaRemision>' . $order->guia . '</guiaRemision>' : null;
        $string .= '<razonSocialComprador>' . str_replace("&", "Y", $order->name) . '</razonSocialComprador>';
        $string .= '<identificacionComprador>' . $buyer_id . '</identificacionComprador>';
        $string .= $order->address !== null ? '<direccionComprador>' . $order->address . '</direccionComprador>' : null;
        $string .= '<totalSinImpuestos>' . $order->sub_total . '</totalSinImpuestos>';
        $string .= '<totalDescuento>' . $order->discount . '</totalDescuento>';

        // Aplied only tax to IVA, NOT aplied to IRBPNR % Imp. al Cons Esp, require add
        $string .= '<totalConImpuestos>';

        $aditionalDiscount = $order->discount;
        foreach ($order_items as $oi) {
            $aditionalDiscount -= $oi->discount;
        }

        foreach ($this->grupingTaxes($order_items) as $tax) {
            $string .= "<totalImpuesto>";
            $string .= "<codigo>$tax->code</codigo>";    // 2 IVA - 3 ICE
            $string .= "<codigoPorcentaje>$tax->percentageCode</codigoPorcentaje>";
            $string .= $tax->code === 2 && $aditionalDiscount ? "<descuentoAdicional>" . round($aditionalDiscount, 2) . "</descuentoAdicional>" : null;
            $string .= "<baseImponible>" . number_format($tax->base, 2, '.', '') . "</baseImponible>";
            // Solo en caso del impuesto al IVA poner la <tarifa>
            $string .= $tax->code === 2 ? "<tarifa>$tax->percentage</tarifa>" : null;
            // Impuesto adicional
            // El valor varia en dependecia del impuesto
            $string .= "<valor>" . ($tax->code === 2 ? round($tax->base * $tax->percentage / 100, 2) : $order->ice) . "</valor>";
            // $string .= "<valor>" . ($tax->code === 2 ? ($tax->percentageCode === 0 || $tax->percentageCode === 6 ? 0 : $order->iva) : $order->ice) . "</valor>";
            $string .= "</totalImpuesto>";
        }

        $string .= '</totalConImpuestos>';

        $string .= '<propina>0</propina>';
        $string .= '<importeTotal>' . round($order->total, 2) . '</importeTotal>';
        $string .= '<moneda>DOLAR</moneda>';

        $string .= '<pagos>';
        $string .= '<pago>';
        $string .= '<formaPago>' . str_pad($order->pay_method, 2, '0', STR_PAD_LEFT) . '</formaPago>';
        $string .= '<total>' . $order->total . '</total>';
        $string .= '</pago>';
        $string .= '</pagos>';

        $string .= '</infoFactura>';

        $string .= '<detalles>';
        foreach ($order_items as $detail) {
            $sub_total = $detail->quantity * $detail->price;
            $total = round($sub_total + $detail->valice - $detail->discount, 2);

            $string .= "<detalle>";

            $string .= "<codigoPrincipal>" . $detail->codeproduct . "</codigoPrincipal>";
            // El codigo aux obligatorio por el IVA 5%
            $string .= $detail->iva === 5 ? "<codigoAuxiliar>" . $detail->aux_cod . "</codigoAuxiliar>" : null;
            $string .= "<descripcion>" . $detail->name . "</descripcion>";
            $string .= "<cantidad>" . round($detail->quantity, $company->decimal) . "</cantidad>";
            $string .= "<precioUnitario>" . round($detail->price, $company->decimal) . "</precioUnitario>";
            $string .= "<descuento>" . $detail->discount . "</descuento>";
            $string .= "<precioTotalSinImpuesto>" . round($total, 2) . "</precioTotalSinImpuesto>";

            $string .= "<impuestos>";

            // Impuesto obligatorio
            $string .= "<impuesto>";
            $string .= "<codigo>2</codigo>";
            $string .= "<codigoPorcentaje>" . $detail->iva . "</codigoPorcentaje>";
            $string .= "<tarifa>$detail->percentage</tarifa>";
            $string .= "<baseImponible>" . round($total, 2) . "</baseImponible>";
            $string .= "<valor>" . round($detail->percentage * $total * .01, 2) . "</valor>";
            $string .= "</impuesto>";

            // Impuesto del Monto ICE opcional
            if ($detail->codice) {
                $string .= "<impuesto>";
                $string .= "<codigo>3</codigo>";    // Aplica solo para impuesto del Monto ICE
                $string .= "<codigoPorcentaje>$detail->codice</codigoPorcentaje>";
                $string .= "<tarifa>0</tarifa>";
                $string .= "<baseImponible>" . number_format($sub_total, 2, '.', '') . "</baseImponible>";
                $string .= "<valor>$detail->valice</valor>";
                $string .= "</impuesto>";
            }

            $string .= "</impuestos>";

            $string .= "</detalle>";
        }
        $string .= '</detalles>';

        $orderaditionals = OrderAditional::where('order_id', $order->id)->get();

        if (count($orderaditionals)) {
            $string .= '<infoAdicional>';

            foreach ($orderaditionals as $orderaditional) {
                $string .= '<campoAdicional nombre="' . $orderaditional->name . '">' . $orderaditional->description . '</campoAdicional>';
            }

            $string .= '</infoAdicional>';
        }

        $string .= '</factura>';

        return $string;
    }

    public function grupingTaxes($order_items)
    {
        $taxes = array();
        foreach ($order_items as $tax) {
            $sub_total = number_format($tax->quantity * $tax->price, 2, '.', '');
            $total = $sub_total + $tax->valice - $tax->discount;
            // $percentage = $tax->iva === 2 ? 12 : 0;

            $tax->code = 2;
            $tax->percentageCode = $tax->iva;

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

    public function createLot($idLote)
    {
        $dom = new \DOMDocument('1.0', 'UTF-8');

        $autorizacion = $dom->createElement('lote');
        $autorizacion->setAttribute('version', '1.0.0');
        $dom->appendChild($autorizacion);

        $lot = Lot::find($idLote);
        $estado = $dom->createElement('claveAcceso', $lot->authorization);
        $autorizacion->appendChild($estado);
        $orders = Order::where('lot_id', $idLote)->get();

        $ruc = substr($lot->authorization, 10, 13);
        $auth = $dom->createElement('ruc', $ruc);
        $autorizacion->appendChild($auth);

        $elementocomprobantes = $dom->createElement('comprobantes');
        $autorizacion->appendChild($elementocomprobantes);

        // Formar el xml por lote
        foreach ($orders as $order) {

            $elementocomprobante = $dom->createElement('comprobante');
            $elementocomprobantes->appendChild($elementocomprobante);

            // Use createCDATASection() function to create a new cdata node
            $domElement = $dom->createCDATASection(Storage::get($order->xml));

            // Append element in the document 
            $elementocomprobante->appendChild($domElement);
        }

        $path = 'xmls' . DIRECTORY_SEPARATOR . $ruc . DIRECTORY_SEPARATOR . $lot->authorization . '.xml';
        Storage::put($path, $dom->saveXML());
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
