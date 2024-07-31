<?php

namespace App\Http\Controllers;

use App\Models\Lot;
use App\StaticClasses\VoucherStates;
use Illuminate\Support\Facades\Storage;
use App\Models\Order;

class WSSriOrderController
{
    public function send($id)
    {
        $order = Order::find($id);
        $environment = substr($order->xml, -30, 1);

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
        $paramenters->xml = Storage::get($order->xml);

        try {
            $result = new \stdClass();
            $result = $soapClientReceipt->validarComprobante($paramenters);

            // Verificar si la peticion llego al SRI sino abandonar el proceso
            if (!property_exists($result, 'RespuestaRecepcionComprobante')) {
                return;
            }

            $order->state = VoucherStates::SENDED;
            $order->save();

            switch ($result->RespuestaRecepcionComprobante->estado) {
                case VoucherStates::RECEIVED:
                    $order->state = VoucherStates::RECEIVED;
                    $order->save();
                    $this->authorize($id);
                    break;
                case VoucherStates::RETURNED:
                    $mensajes = $result->RespuestaRecepcionComprobante->comprobantes->comprobante->mensajes;
                    $mensajes = json_decode(json_encode($mensajes), true);

                    $message = $mensajes['mensaje']['mensaje'] . '.';
                    if (array_key_exists('informacionAdicional', $mensajes['mensaje'])) {
                        $message .= ' informacionAdicional : ' . $mensajes['mensaje']['informacionAdicional'];
                    }

                    $order->extra_detail = substr($message, 0, 255);
                    $order->state = VoucherStates::RETURNED;
                    $order->save();
                    break;
            }
        } catch (\Exception $e) {
            info(' CODE: ' . $e->getCode());
        }
    }

    public function sendLote($idLot)
    {
        $lot = Lot::find($idLot);
        $environment = substr($lot->authorization, 23, 1);
        $ruc = substr($lot->authorization, 10, 13);

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
        $paramenters->xml = Storage::get('xmls' . DIRECTORY_SEPARATOR . $ruc . DIRECTORY_SEPARATOR . $lot->authorization . '.xml');

        try {
            $result = new \stdClass();
            $result = $soapClientReceipt->validarComprobante($paramenters);

            // Verificar si la peticion llego al SRI sino abandonar el proceso
            if (!property_exists($result, 'RespuestaRecepcionComprobante')) {
                return;
            }

            Order::where('lot_id', $lot->id)->update(['state' => VoucherStates::SENDED]);
            $lot->state = VoucherStates::SENDED;
            $lot->save();

            switch ($result->RespuestaRecepcionComprobante->estado) {
                case VoucherStates::RECEIVED:
                    Order::where('lot_id', $lot->id)->update(['state' => VoucherStates::RECEIVED]);
                    $lot->state = VoucherStates::RECEIVED;
                    $lot->save();
                    $this->authorizedLot($idLot);
                    break;
                case VoucherStates::RETURNED:
                    $mensajes = $result->RespuestaRecepcionComprobante->comprobantes->comprobante->mensajes;
                    $mensajes = json_decode(json_encode($mensajes), true);

                    $message = $mensajes['mensaje']['mensaje'] . '.';
                    if (array_key_exists('informacionAdicional', $mensajes['mensaje'])) {
                        $message .= ' informacionAdicional : ' . $mensajes['mensaje']['informacionAdicional'];
                    }

                    $lot->extra_detail = substr($message, 0, 255);
                    $lot->state = VoucherStates::RETURNED;
                    $lot->save();
                    break;
            }
        } catch (\Exception $e) {
            info(' CODE: ' . $e->getCode());
        }
    }

    public function authorizedLot($idLot)
    {
        $lot = Lot::find($idLot);

        if ($lot->state === VoucherStates::AUTHORIZED || $lot->state === VoucherStates::CANCELED) return;

        $environment = substr($lot->authorization, 23, 1);

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
            'exceptions' => 1
        );

        $soapClientValidation = new \SoapClient($wsdlAuthorization, $options);

        // Parameters SOAP
        $user_param = array(
            'claveAccesoLote' => $lot->authorization
        );

        try {
            $response = $soapClientValidation->autorizacionComprobanteLote($user_param);

            // Verificar si la peticion llego al SRI sino abandonar el proceso
            if (!property_exists($response, 'RespuestaAutorizacionLote')) {
                return;
            }

            foreach ($response->RespuestaAutorizacionLote->autorizaciones->autorizacion as $autorizacion) {
                $order = Order::where('authorization', $autorizacion->numeroAutorizacion)->first();
                $this->saveResultAutorization($order, $autorizacion);
            }
        } catch (\Exception $e) {
            info(' CODE: ' . $e->getCode());
        }
    }

    public function authorize($id)
    {
        $order = Order::find($id);
        $environment = substr($order->xml, -30, 1);

        if ($order->state === VoucherStates::AUTHORIZED || $order->state === VoucherStates::CANCELED) return;

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
            'claveAccesoComprobante' => substr(substr($order->xml, -53), 0, 49)
        );

        try {
            $response = $soapClientValidation->autorizacionComprobante($user_param);

            // Verificar si la peticion llego al SRI sino abandonar el proceso
            if (!property_exists($response, 'RespuestaAutorizacionComprobante')) {
                return;
            }

            $autorizacion = $response->RespuestaAutorizacionComprobante->autorizaciones->autorizacion;
            $this->saveResultAutorization($order, $autorizacion);
        } catch (\Exception $e) {
            info(' CODE: ' . $e->getCode());
        }
    }

    private function saveResultAutorization($order, $autorizacion)
    {
        switch ($autorizacion->estado) {
            case VoucherStates::AUTHORIZED:
                $toPath = str_replace(VoucherStates::SIGNED, VoucherStates::AUTHORIZED, $order->xml);
                $folder = substr($toPath, 0, strpos($toPath, VoucherStates::AUTHORIZED)) . VoucherStates::AUTHORIZED;

                if (!file_exists(Storage::path($folder))) {
                    Storage::makeDirectory($folder);
                }

                Storage::put($toPath, $this->xmlautorized($autorizacion));
                // Elimina el archivo FIRMADO
                Storage::delete($order->xml);
                $order->xml = $toPath;
                $order->state = VoucherStates::AUTHORIZED;
                $authorizationDate = \DateTime::createFromFormat('Y-m-d\TH:i:sP', $autorizacion->fechaAutorizacion);
                $order->autorized = $authorizationDate->format('Y-m-d H:i:s');
                break;
            case VoucherStates::REJECTED:
                $mensajes = json_decode(json_encode($autorizacion->mensajes), true);

                $message = $mensajes['mensaje']['mensaje'] . '. ';
                if (array_key_exists('informacionAdicional', $mensajes['mensaje'])) {
                    $message .= $mensajes['mensaje']['informacionAdicional'];
                }

                $order->state = VoucherStates::REJECTED;
                $order->extra_detail = substr($message, 0, 255);
                break;
            default:
                $order->state = VoucherStates::IN_PROCESS;
                break;
        }
        $order->save();
    }

    private function xmlautorized($comprobante)
    {
        $dom = new \DOMDocument('1.0', 'ISO-8859-1');

        $autorizacion = $dom->createElement('autorizacion');
        $dom->appendChild($autorizacion);

        $estado = $dom->createElement('estado', $comprobante->estado);
        $autorizacion->appendChild($estado);

        $auth = $dom->createElement('numeroAutorizacion', $comprobante->numeroAutorizacion);
        $autorizacion->appendChild($auth);

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
        $order = Order::find($id);
        $environment = substr($order->xml, -30, 1);

        if ($order->state !== VoucherStates::AUTHORIZED) {
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
            'claveAccesoComprobante' => substr(substr($order->xml, -53), 0, 49)
        );

        $response = $soapClientValidation->autorizacionComprobante($user_param);

        // Verificar si la peticion llego al SRI sino abandonar el proceso
        if (!property_exists($response, 'RespuestaAutorizacionComprobante')) {
            return;
        }

        if ((int)$response->RespuestaAutorizacionComprobante->numeroComprobantes === 0) {
            $order->state = VoucherStates::CANCELED;
            $order->save();
            return response()->json(['state' => 'OK']);
        } else {
            return response()->json(['state' => 'KO']);
        }
    }
}
