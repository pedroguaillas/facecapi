<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Auth;
use App\Models\Company;
use App\Models\ReferralGuide;
use App\Models\ReferralGuideItem;
use App\StaticClasses\VoucherStates;

class ReferralGuideXmlController extends Controller
{
    public function download($id)
    {
        $referralguide = ReferralGuide::findOrFail($id);

        return response()->json([
            'xml' => base64_encode(Storage::get($referralguide->xml))
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

        $referralguide = ReferralGuide::join('customers AS c', 'c.id', 'customer_id')
            ->join('carriers AS ca', 'ca.id', 'carrier_id')
            ->select('ca.identication AS ca_identication', 'ca.name AS ca_name', 'ca.license_plate', 'c.identication', 'c.name', 'referral_guides.*')
            ->where('referral_guides.id', $id)
            ->first();

        $referralguideitems = ReferralGuideItem::join('products AS p', 'p.id', 'product_id')
            ->where('referral_guide_id', $id)
            ->get();

        $str_xml_voucher = $this->invoice($referralguide, $company, $referralguideitems);

        $this->sign($company, $referralguide, $str_xml_voucher);
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

            // Si no existe la carpeta FIRMADO entonces Crear
            if (!file_exists(Storage::path($rootfile . DIRECTORY_SEPARATOR . VoucherStates::SIGNED))) {
                Storage::makeDirectory($rootfile . DIRECTORY_SEPARATOR . VoucherStates::SIGNED);
            }

            // $rootfile = Storage::path($rootfile);
            $newrootfile = Storage::path($rootfile);

            // $java_firma = "java -jar public\Firma\dist\Firma.jar $cert $company->pass_cert $rootfile\\CREADO\\$file $rootfile\\FIRMADO $file";
            $java_firma = "java -jar $public_path/public/Firma/dist/Firma.jar $cert $company->pass_cert $newrootfile/CREADO/$file $newrootfile/FIRMADO $file";

            $variable = system($java_firma);

            // Si se creo el archivo FIRMADO entonces guardar estado FIRMADO Y el nuevo path XML
            if (file_exists(Storage::path($rootfile . DIRECTORY_SEPARATOR . VoucherStates::SIGNED . DIRECTORY_SEPARATOR . $file))) {
                $order->xml = $rootfile . DIRECTORY_SEPARATOR . VoucherStates::SIGNED . DIRECTORY_SEPARATOR . $file;
                $order->state = VoucherStates::SIGNED;
                $order->save();
                (new WSSriReferralGuide())->send($order->id);
            }
        }
    }

    private function invoice($order, $company, $order_items)
    {
        $string = '';
        $string .= '<?xml version="1.0" encoding="UTF-8"?>';
        $string .= '<guiaRemision id="comprobante" version="1.0.0">';

        $string .= $this->infoTributaria($company, $order);
        $string .= '<infoGuiaRemision>';

        $date_start = new \DateTime($order->date_start);
        $date_end = new \DateTime($order->date_end);

        $string .= "<dirPartida>$order->address_from</dirPartida>";
        $string .= "<razonSocialTransportista>" . str_replace("&", "Y", $order->ca_name) . "</razonSocialTransportista>";
        $string .= "<tipoIdentificacionTransportista>" . (strlen($order->ca_identication) === 13 ? '04' : '05') . "</tipoIdentificacionTransportista>";
        $string .= "<rucTransportista>$order->ca_identication</rucTransportista>";
        $string .= '<obligadoContabilidad>' . ($company->accounting ? 'SI' : 'NO') . '</obligadoContabilidad>';
        $string .= '<fechaIniTransporte>' . $date_start->format('d/m/Y') . '</fechaIniTransporte>';
        $string .= '<fechaFinTransporte>' . $date_end->format('d/m/Y') . '</fechaFinTransporte>';
        $string .= "<placa>$order->license_plate</placa>";

        $string .= '</infoGuiaRemision>';

        $string .= '<destinatarios>';
        $string .= '<destinatario>';
        $string .= "<identificacionDestinatario>$order->identication</identificacionDestinatario>";
        $string .= "<razonSocialDestinatario>" . str_replace("&", "Y", $order->name) . "</razonSocialDestinatario>";
        $string .= "<dirDestinatario>$order->address_to</dirDestinatario>";
        $string .= $order->reason_transfer ? "<motivoTraslado>$order->reason_transfer</motivoTraslado>" : null;
        $string .= $order->branch_destiny ? "<codEstabDestino>$order->branch_destiny</codEstabDestino>" : null;
        $string .= $order->route ? "<ruta>$order->route</ruta>" : null;
        $string .= $order->serie_invoice ? "<codDocSustento>01</codDocSustento>" : null;
        $string .= $order->serie_invoice ? "<numDocSustento>$order->serie_invoice</numDocSustento>" : null;
        $string .= $order->authorization_invoice ? "<numAutDocSustento>$order->authorization_invoice</numAutDocSustento>" : null;
        $string .= $order->date_invoice ? "<fechaEmisionDocSustento>" . (new \DateTime($order->date_invoice))->format('d/m/Y') . "</fechaEmisionDocSustento>" : null;

        $string .= '<detalles>';
        foreach ($order_items as $detail) {

            $string .= "<detalle>";
            $string .= "<codigoInterno>" . $detail->code . "</codigoInterno>";
            $string .= "<descripcion>" . $detail->name . "</descripcion>";
            $string .= "<cantidad>$detail->quantity</cantidad>";
            $string .= "</detalle>";
        }
        $string .= '</detalles>';

        $string .= '</destinatario>';
        $string .= '</destinatarios>';

        $string .= '</guiaRemision>';

        return $string;
    }

    private function infoTributaria($company, $order)
    {
        $branch = Branch::where('company_id', $company->id)
            ->orderBy('created_at')->first();

        $voucher_type = '06';

        $serie = str_replace('-', '', $order->serie);

        $keyaccess = (new \DateTime($order->date_start))->format('dmY') . $voucher_type .
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
