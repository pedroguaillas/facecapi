<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use App\Models\Provider;
use App\Models\Company;

class ShopImportController extends Controller
{
    public function import(Request $request)
    {
        $clave_accs = $request->get('clave_accs');

        $auth = Auth::user();
        $level = $auth->companyusers->first();
        $company = Company::find($level->level_id);
        $branch = $company->branches->first();

        $newkeys = [];

        foreach ($clave_accs as $clave) {
            $enc = false;
            $i = 0;
            while (!$enc && $i < count($branch->shops)) {
                if ($clave === $branch->shops[$i]->authorization) {
                    $enc = true;
                }
                $i++;
            }

            if (!$enc) {
                $newkeys[] = $clave;
            }
        }

        $clave_accs = $newkeys;

        $url = "https://cel.sri.gob.ec/comprobantes-electronicos-ws/AutorizacionComprobantesOffline?wsdl";

        ini_set("default_socket_timeout", 120);
        $client = new \SoapClient(
            $url,
            array(
                "soap_version" => SOAP_1_1,
                'connection_timeout' => 3,
                // exceptions used for detect error in SOAP is_soap_fault
                'exceptions' => 0
            )
        );

        foreach ($clave_accs as $clave_acc) {
            // Parameters SOAP
            $user_param = array(
                'claveAccesoComprobante' => $clave_acc
            );
            //Request to server SRI
            $response = $client->autorizacionComprobante($user_param);

            if (!is_soap_fault($response) && $response->RespuestaAutorizacionComprobante->autorizaciones->autorizacion->estado === 'AUTORIZADO') {
                $this->rowcompra($response->RespuestaAutorizacionComprobante->autorizaciones->autorizacion);
            } else {
                $response = $client->autorizacionComprobante($user_param);
                $this->rowcompra($response->RespuestaAutorizacionComprobante->autorizaciones->autorizacion);
            }
        }
    }

    private function rowcompra($autorizacion)
    {
        $noIvaR = 0;
        $b0R = 0;
        $b12R = 0;

        $dom = new \DOMDocument();
        $dom->loadXML($autorizacion->comprobante);

        $impuestos = $dom->getElementsByTagName('totalImpuesto');

        foreach ($impuestos as $impuesto) {
            switch ((int) $impuesto->getElementsByTagName('codigoPorcentaje')->item(0)->textContent) {
                case 0:
                    $b0R += round($impuesto->getElementsByTagName('baseImponible')->item(0)->textContent, 2);
                    break;
                case 2:
                    $b12R += round($impuesto->getElementsByTagName('baseImponible')->item(0)->textContent, 2);
                    break;
                case 3:
                    $b12R += round($impuesto->getElementsByTagName('baseImponible')->item(0)->textContent, 2);
                    break;
                case 6:
                    $noIvaR += round($impuesto->getElementsByTagName('baseImponible')->item(0)->textContent, 2);
                    break;
                default:
                    if ((int)($impuesto->getElementsByTagName('codigo')->item(0)->textContent) === 3) {
                        $ice = round($impuesto->getElementsByTagName('valor')->item(0)->textContent, 2);
                    }
                    break;
            }
        }

        $auth = Auth::user();
        $level = $auth->companyusers->first();
        $company = Company::find($level->level_id);
        $branch = $company->branches->first();

        $providers = Provider::where([
            'branch_id' => $branch->id,
            'identication' => $dom->getElementsByTagName('ruc')->item(0)->textContent
        ])->get();

        if (count($providers)) {
            $provider = $providers->first();
        } else {
            $provider = $branch->providers()->create([
                'identication' => $dom->getElementsByTagName('ruc')->item(0)->textContent,
                'type_identification' => 'ruc',
                'name' => $dom->getElementsByTagName('razonSocial')->item(0)->textContent,
                'address' => $dom->getElementsByTagName('dirMatriz')->item(0)->textContent,
                'accounting' => $dom->getElementsByTagName('obligadoContabilidad') && $dom->getElementsByTagName('obligadoContabilidad')->item(0)->textContent === 'SI' ? 1 : 0
            ]);
        }

        $fecha = date_create_from_format('d/m/Y', $dom->getElementsByTagName('fechaEmision')->item(0)->textContent);

        $shop = [
            'date' => date_format($fecha, 'Y-m-d'),
            'totalSinImpuestos' => $dom->getElementsByTagName('totalDescuento')->item(0)->textContent,
            'serie' => $dom->getElementsByTagName('estab')->item(0)->textContent . '-' . $dom->getElementsByTagName('ptoEmi')->item(0)->textContent . '-' . $dom->getElementsByTagName('secuencial')->item(0)->textContent,
            'provider_id' => $provider->id,
            'no_iva' => $noIvaR,
            'base0' => $b0R,
            'base12' => $b12R,
            'iva' => $b12R * .12,
            'discount' => $dom->getElementsByTagName('totalDescuento')->item(0)->textContent,
            'ice' => $ice,
            'total' => $dom->getElementsByTagName('importeTotal')->item(0)->textContent,
            'voucher_type' => (int)$dom->getElementsByTagName('codDoc')->item(0)->textContent,
            'authorization' => $autorizacion->numeroAutorizacion,
        ];

        $branch->shops()->create($shop);
    }
}
