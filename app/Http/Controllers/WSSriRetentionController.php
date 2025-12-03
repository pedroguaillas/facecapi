<?php

namespace App\Http\Controllers;

use App\StaticClasses\VoucherStates;
use Illuminate\Support\Facades\Storage;
use App\Models\Shop;

class WSSriRetentionController
{
    public function sendSri($id)
    {
        $shop = Shop::find($id);

        if ($shop->state_retencion !== VoucherStates::SIGNED) {
            return;
        }

        $environment = substr($shop->xml_retention, -30, 1);

        switch ((int) $environment) {
            case 1:
                $wsdlReceipt = 'https://celcer.sri.gob.ec/comprobantes-electronicos-ws/RecepcionComprobantesOffline?wsdl';
                break;
            case 2:
                $wsdlReceipt = 'https://cel.sri.gob.ec/comprobantes-electronicos-ws/RecepcionComprobantesOffline?wsdl';
                break;
        }

        $options = array(
            'connection_timeout' => 3,
            // 'cache_wsdl' => WSDL_CACHE_NONE
        );

        $soapClientReceipt = new \SoapClient($wsdlReceipt, $options);
        $paramenters = new \stdClass();
        $paramenters->xml = Storage::get($shop->xml_retention);

        try {
            $result = new \stdClass();
            $result = $soapClientReceipt->validarComprobante($paramenters);

            if (!property_exists($result, 'RespuestaRecepcionComprobante')) {
                return;
            }

            $shop->state_retencion = VoucherStates::SENDED;
            $shop->save();

            switch ($result->RespuestaRecepcionComprobante->estado) {
                case VoucherStates::RECEIVED:
                    $shop->state_retencion = VoucherStates::RECEIVED;
                    $shop->save();
                    $this->authorize($id);
                    break;
                case VoucherStates::RETURNED:
                    $mensajes = json_decode(json_encode($result->RespuestaRecepcionComprobante->comprobantes->comprobante->mensajes), true);

                    $message = $mensajes['mensaje']['mensaje'];
                    if (array_key_exists('informacionAdicional', $mensajes['mensaje'])) {
                        $message .= '. informacionAdicional : ' . $mensajes['mensaje']['informacionAdicional'];
                    }

                    $shop->extra_detail_retention = substr($message, 0, 255);
                    $shop->state_retencion = VoucherStates::RETURNED;
                    $shop->save();
                    break;
            }
        } catch (\Exception $e) {
            info(' CODE: ' . $e->getCode());
        }
    }

    public function authorize($id)
    {
        $shop = Shop::find($id);
        $environment = substr($shop->xml_retention, -30, 1);

        if ($shop->state_retencion === VoucherStates::AUTHORIZED || $shop->state_retencion === VoucherStates::CANCELED) {
            return;
        }
        switch ((int) $environment) {
            case 1:
                $wsdlAuthorization = 'https://celcer.sri.gob.ec/comprobantes-electronicos-ws/AutorizacionComprobantesOffline?wsdl';
                break;
            case 2:
                $wsdlAuthorization = 'https://cel.sri.gob.ec/comprobantes-electronicos-ws/AutorizacionComprobantesOffline?wsdl';
                break;
        }

        $options = array(
            "soap_version" => SOAP_1_1,
            // trace used for __getLastResponse return result in XML
            "trace" => 1,
            'connection_timeout' => 3,
            // exceptions used for detect error in SOAP is_soap_fault
            'exceptions' => 0
        );

        $soapClientValidation = new \SoapClient($wsdlAuthorization, $options);

        // Parameters SOAP
        $user_param = array(
            'claveAccesoComprobante' => substr(substr($shop->xml_retention, -53), 0, 49)
        );

        try {
            $response = $soapClientValidation->autorizacionComprobante($user_param);

            // Verificar si la peticion llego al SRI sino abandonar el proceso
            if (!property_exists($response, 'RespuestaAutorizacionComprobante')) {
                return;
            }

            $autorizacion = $response->RespuestaAutorizacionComprobante->autorizaciones->autorizacion;

            switch ($autorizacion->estado) {
                case VoucherStates::AUTHORIZED:
                    $toPath = str_replace(VoucherStates::SIGNED, VoucherStates::AUTHORIZED, $shop->xml_retention);
                    $folder = substr($toPath, 0, strpos($toPath, VoucherStates::AUTHORIZED)) . VoucherStates::AUTHORIZED;

                    if (!file_exists(Storage::path($folder))) {
                        Storage::makeDirectory($folder);
                    }

                    Storage::put($toPath, $this->xmlautorized($autorizacion));
                    $shop->xml_retention = $toPath;
                    $shop->state_retencion = VoucherStates::AUTHORIZED;
                    $authorizationDate = \DateTime::createFromFormat('Y-m-d\TH:i:sP', $autorizacion->fechaAutorizacion);
                    $shop->autorized_retention = $authorizationDate->format('Y-m-d H:i:s');
                    $shop->authorization_retention = $autorizacion->numeroAutorizacion;
                    $shop->save();
                    
                    (new MailController())->retentionMail($id);
                    break;
                case VoucherStates::REJECTED:
                    $mensajes = json_decode(json_encode($autorizacion->mensajes), true);

                    $message = $mensajes['mensaje']['mensaje'];
                    if (array_key_exists('informacionAdicional', $mensajes['mensaje'])) {
                        $message .= '. informacionAdicional : ' . $mensajes['mensaje']['informacionAdicional'];
                    }

                    $shop->state_retencion = VoucherStates::REJECTED;
                    $shop->extra_detail_retention = substr($message, 0, 255);
                    $shop->save();
                    break;
                default:
                    $shop->state_retencion = VoucherStates::IN_PROCESS;
                    $shop->save();
                    break;
            }
        } catch (\Exception $e) {
            info(' CODE: ' . $e->getCode());
        }
    }

    private function xmlautorized($comprobante)
    {
        $dom = new \DOMDocument('1.0', 'ISO-8859-1');

        $autorizacion = $dom->createElement('autorizacion');
        $dom->appendChild($autorizacion);

        $estado = $dom->createElement('estado', $comprobante->estado);
        $autorizacion->appendChild($estado);

        if ($comprobante->estado === VoucherStates::AUTHORIZED) {
            $auth = $dom->createElement('numeroAutorizacion', $comprobante->numeroAutorizacion);
            $autorizacion->appendChild($auth);
        }

        $fechaAutorizacion = $dom->createElement('fechaAutorizacion', $comprobante->fechaAutorizacion);
        $autorizacion->appendChild($fechaAutorizacion);

        $ambiente = $dom->createElement('ambiente', $comprobante->ambiente);
        $autorizacion->appendChild($ambiente);

        $elementocomprobante = $dom->createElement('comprobante');
        $autorizacion->appendChild($elementocomprobante);

        // Use createCDATASection() function to create a new cdata node 
        $domElement = $dom->createCDATASection($comprobante->comprobante);

        // Append element in the document 
        $elementocomprobante->appendChild($domElement);

        return $dom->saveXML();
    }

    public function cancel(int $id)
    {
        $shop = Shop::find($id);
        $environment = substr($shop->xml_retention, -30, 1);

        // Obligar a que sea autorizado para anular
        if ($shop->state_retencion !== VoucherStates::AUTHORIZED) {
            return;
        }

        switch ((int) $environment) {
            case 1:
                $wsdlAuthorization = 'https://celcer.sri.gob.ec/comprobantes-electronicos-ws/AutorizacionComprobantesOffline?wsdl';
                break;
            case 2:
                $wsdlAuthorization = 'https://cel.sri.gob.ec/comprobantes-electronicos-ws/AutorizacionComprobantesOffline?wsdl';
                break;
        }

        $options = array(
            "soap_version" => SOAP_1_1,
            // trace used for __getLastResponse return result in XML
            "trace" => 1,
            'connection_timeout' => 3,
            // exceptions used for detect error in SOAP is_soap_fault
            'exceptions' => 0
        );

        $soapClientValidation = new \SoapClient($wsdlAuthorization, $options);

        // Parameters SOAP
        $user_param = array(
            'claveAccesoComprobante' => substr(substr($shop->xml_retention, -53), 0, 49)
        );

        $response = $soapClientValidation->autorizacionComprobante($user_param);

        if ((int) $response->RespuestaAutorizacionComprobante->numeroComprobantes === 0) {
            $shop->state_retencion = VoucherStates::CANCELED;
            $shop->save();
            return response()->json(['state' => 'OK']);
        } else {
            return response()->json(['state' => 'KO']);
        }
    }
}
