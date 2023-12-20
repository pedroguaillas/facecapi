<?php

namespace App\Http\Controllers;

use App\StaticClasses\VoucherStates;
use Illuminate\Support\Facades\Storage;
use App\Models\ReferralGuide;

class WSSriReferralGuide
{
    public function send($id)
    {
        $referral_guide = ReferralGuide::find($id);
        $environment = substr($referral_guide->xml, -30, 1);

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
        $paramenters->xml = Storage::get($referral_guide->xml);

        try {
            $result = new \stdClass();
            $result = $soapClientReceipt->validarComprobante($paramenters);

            // Verificar si la peticion llego al SRI sino abandonar el proceso
            if (!property_exists($result, 'RespuestaRecepcionComprobante')) {
                return;
            }

            $this->moveXmlFile($referral_guide, VoucherStates::SENDED);

            switch ($result->RespuestaRecepcionComprobante->estado) {
                case VoucherStates::RECEIVED:
                    $this->moveXmlFile($referral_guide, VoucherStates::RECEIVED);
                    $this->authorize($id);
                    break;
                case VoucherStates::RETURNED:
                    $mensajes = $result->RespuestaRecepcionComprobante->comprobantes->comprobante->mensajes;
                    $mensajes = json_decode(json_encode($mensajes), true);

                    $message = $mensajes['mensaje']['mensaje'] . '.';
                    if (array_key_exists('informacionAdicional', $mensajes['mensaje'])) {
                        $message .= ' informacionAdicional : ' . $mensajes['mensaje']['informacionAdicional'];
                    }

                    $referral_guide->extra_detail = $message;
                    $this->moveXmlFile($referral_guide, VoucherStates::RETURNED);
                    break;
            }
        } catch (\Exception $e) {
            info(' CODE: ' . $e->getCode());
        }
    }

    public function authorize($id)
    {
        $referral_guide = ReferralGuide::find($id);
        $environment = substr($referral_guide->xml, -30, 1);

        if ($referral_guide->state === VoucherStates::AUTHORIZED || $referral_guide->state === VoucherStates::CANCELED) {
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
            'claveAccesoComprobante' => substr(substr($referral_guide->xml, -53), 0, 49)
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
                    $toPath = str_replace($referral_guide->state, VoucherStates::AUTHORIZED, $referral_guide->xml);
                    $folder = substr($toPath, 0, strpos($toPath, VoucherStates::AUTHORIZED)) . VoucherStates::AUTHORIZED;

                    if (!file_exists(Storage::path($folder))) {
                        Storage::makeDirectory($folder);
                    }

                    Storage::put($toPath, $this->xmlautorized($autorizacion));
                    $referral_guide->xml = $toPath;
                    $referral_guide->state = VoucherStates::AUTHORIZED;
                    $authorizationDate = \DateTime::createFromFormat('Y-m-d\TH:i:sP', $autorizacion->fechaAutorizacion);
                    $referral_guide->autorized = $authorizationDate->format('Y-m-d H:i:s');
                    $referral_guide->authorization = $autorizacion->numeroAutorizacion;
                    $referral_guide->save();
                    break;
                case VoucherStates::REJECTED:
                    $mensajes = json_decode(json_encode($autorizacion->mensajes), true);

                    $message = $mensajes['mensaje']['mensaje'];
                    if (array_key_exists('informacionAdicional', $mensajes['mensaje'])) {
                        $message .= '. informacionAdicional : ' . $mensajes['mensaje']['informacionAdicional'];
                    }

                    // $toPath = str_replace($referral_guide->state, VoucherStates::REJECTED, $referral_guide->xml);
                    // Storage::put($toPath, $autorizacion);
                    // $referral_guide->xml = $toPath;
                    $referral_guide->state = VoucherStates::REJECTED;
                    $referral_guide->extra_detail = $message;
                    $authorizationDate = \DateTime::createFromFormat('Y-m-d\TH:i:sP', $autorizacion->fechaAutorizacion);
                    $referral_guide->autorized = $authorizationDate->format('Y-m-d H:i:s');
                    $referral_guide->save();
                    break;
                default:
                    $referral_guide->state = VoucherStates::IN_PROCESS;
                    $referral_guide->save();
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

    private function moveXmlFile($referral_guide, $newState)
    {
        $xml = str_replace($referral_guide->state, $newState, $referral_guide->xml);
        $folder = substr($xml, 0, strpos($xml, $newState)) . $newState;

        if (!file_exists(Storage::path($folder))) {
            Storage::makeDirectory($folder);
        }

        Storage::move($referral_guide->xml, $xml);
        $referral_guide->state = $newState;
        $referral_guide->xml = $xml;
        $referral_guide->save();
    }
}
