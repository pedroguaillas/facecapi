<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use Illuminate\Support\Facades\Auth;
use App\Models\Company;
use App\Models\Order;
use App\Models\Shop;
use Illuminate\Support\Carbon;

class AtsController extends Controller
{
    public function generate(string $month)
    {
        $auth = Auth::user();
        $level = $auth->companyusers->first();
        $company = Company::find($level->level_id);
        $branch = Branch::where('company_id', $company->id)
            ->orderBy('created_at')->first();

        $year = substr($month, 0, 4);
        $month = substr($month, 5, 2);

        //ATS/---------------------
        $domtree = new \DOMDocument('1.0', 'ISO-8859-1');

        /* create the root element of the xml tree */
        $xmlRoot = $domtree->createElement("iva");
        /* append it to the document created */
        $xmlRoot = $domtree->appendChild($xmlRoot);

        //Informante/---------------
        $this->_($domtree, $xmlRoot, 'TipoIDInformante', 'R');
        $this->_($domtree, $xmlRoot, 'IdInformante', $company->ruc);
        $this->_($domtree, $xmlRoot, 'razonSocial', $company->company);
        $this->_($domtree, $xmlRoot, 'Anio', $year);
        $this->_($domtree, $xmlRoot, 'Mes', $month);

        $ventasEstablecimiento = Order::selectRaw('SUBSTRING(serie, 1, 3) AS asserie,SUM(base0) AS b0, SUM(base12) AS b12')
            ->whereYear('date', $year)
            ->whereMonth('date', $month)
            ->where([
                'branch_id' => $branch->id,
                'state' => 'AUTORIZADO',
            ])
            ->groupBy('asserie')
            ->orderBy('asserie', 'DESC')
            ->get();

        $est = $ventasEstablecimiento->count() > 0 ? $ventasEstablecimiento->first()->asserie : '001';

        $this->_($domtree, $xmlRoot, 'numEstabRuc', $est);

        $orders = Order::join('customers AS c', 'c.id', 'customer_id')
            ->selectRaw('type_identification,identication,voucher_type,COUNT(*) as numeroComprobantes,SUM(base0) AS base0,SUM(base12) AS base12,SUM(iva) AS iva,SUM(ice) AS ice')
            ->whereYear('date', $year)
            ->whereMonth('date', $month)
            ->where([
                'orders.branch_id' => $branch->id,
                'orders.state' => 'AUTORIZADO',
            ])
            ->groupBy('type_identification', 'identication', 'voucher_type')
            ->get();

        $bases = 0;
        foreach ($orders as $order) {
            $bases += $order->base0 + $order->base12;
        }

        $this->_($domtree, $xmlRoot, 'totalVentas', $bases);
        $this->_($domtree, $xmlRoot, 'codigoOperativo', 'IVA');

        // Carga de compras
        $shops = Shop::join('providers AS p', 'provider_id', 'p.id')
            ->selectRaw('p.type_identification AS ti,p.identication,voucher_type,p.name,date,serie,authorization,no_iva,base0,base12,ice,iva')
            ->whereYear('date', $year)
            ->whereMonth('date', $month)
            ->get();

        if ($shops->count()) {
            $this->loadCompras($domtree, $xmlRoot, $shops);
        }

        // Carga de ventas
        if ($order->count()) {
            $this->loadOrders($domtree, $xmlRoot, $orders);
            $this->loadVentasEstablecimiento($domtree, $xmlRoot, $ventasEstablecimiento);
        }

        return $domtree->saveXML();
    }

    private function loadCompras($dom, $xmlRoot, $shops)
    {
        $compras = $xmlRoot->appendChild($dom->createElement("compras"));

        foreach ($shops as $shop) {
            $detalleCompras = $compras->appendChild($dom->createElement("detalleCompras"));

            $this->_($dom, $detalleCompras, 'codSustento', $shop->voucher_type === '2' ? '02' : '01');
            $this->_($dom, $detalleCompras, 'tpIdProv', $shop->type_identification === 'ruc' ? '01' : ($shop->type_identification === 'cédula' ? '02' : '03'));
            $this->_($dom, $detalleCompras, 'idProv', $shop->identication);
            $this->_($dom, $detalleCompras, 'tipoComprobante', str_pad($shop->voucher_type, 2, 0, STR_PAD_LEFT));
            $this->_($dom, $detalleCompras, 'tipoProv', $shop->type_identification === 'ruc' && (substr($shop->type_identification, 2, 1) === '9' || substr($shop->type_identification, 2, 1) === '6') ? '02' : '01');
            $this->_($dom, $detalleCompras, 'parteRel', 'NO');

            $date = Carbon::createFromFormat('Y-m-d', $shop->date)->format('d/m/Y');

            $this->_($dom, $detalleCompras, 'fechaRegistro', $date);
            $this->_($dom, $detalleCompras, 'establecimiento', substr($shop->serie, 0, 3));
            $this->_($dom, $detalleCompras, 'puntoEmision', substr($shop->serie, 4, 3));
            $this->_($dom, $detalleCompras, 'secuencial', substr($shop->serie, 8, 9));
            $this->_($dom, $detalleCompras, 'fechaEmision', $date);
            $this->_($dom, $detalleCompras, 'autorizacion', $shop->authorization);
            $this->_($dom, $detalleCompras, 'baseNoGraIva', $shop->no_iva);
            $this->_($dom, $detalleCompras, 'baseImponible', $shop->base0);
            $this->_($dom, $detalleCompras, 'baseImpGrav', $shop->base12);
            $this->_($dom, $detalleCompras, 'baseImpExe', 0);
            $this->_($dom, $detalleCompras, 'montoIce', $shop->ice);
            $this->_($dom, $detalleCompras, 'montoIva', $shop->iva);
            $this->_($dom, $detalleCompras, 'valRetBien10', 0);
            $this->_($dom, $detalleCompras, 'valRetServ20', 0);
            $this->_($dom, $detalleCompras, 'valorRetBienes', 0);
            $this->_($dom, $detalleCompras, 'valRetServ50', 0);
            $this->_($dom, $detalleCompras, 'valorRetServicios', 0);
            $this->_($dom, $detalleCompras, 'valRetServ100', 0);
            $this->_($dom, $detalleCompras, 'valorRetencionNc', 0);
            $this->_($dom, $detalleCompras, 'totbasesImpReemb', 0);

            $pagoExterior = $detalleCompras->appendChild($dom->createElement("pagoExterior"));
            $this->_($dom, $pagoExterior, 'pagoLocExt', '01');
            $this->_($dom, $pagoExterior, 'paisEfecPago', 'NA');
            $this->_($dom, $pagoExterior, 'aplicConvDobTrib', 'NA');
            $this->_($dom, $pagoExterior, 'pagExtSujRetNorLeg', 'NA');

            $formasDePago = $detalleCompras->appendChild($dom->createElement("formasDePago"));
            $base = $shop->no_iva + $shop->base0 - $shop->base12;
            $this->_($dom, $formasDePago, 'formaPago', $base > 999.99 ? '20' : '01');
        }
    }

    private function loadVentasEstablecimiento($dom, $xmlRoot, $ordersEst)
    {
        $ventasEstablecimiento = $xmlRoot->appendChild($dom->createElement("ventasEstablecimiento"));

        foreach ($ordersEst as $order) {
            // Falta llenar los espaciones de los establecimientos en 0
            // Recorrer desde el establecimiento 1 hasta el que existe con valores en 0
            $ventaEst = $ventasEstablecimiento->appendChild($dom->createElement("ventaEst"));

            $this->_($dom, $ventaEst, 'codEstab', $order->asserie);
            $this->_($dom, $ventaEst, 'ventasEstab', $order->b0 + $order->b12);
            $this->_($dom, $ventaEst, 'ivaComp', 0);
        }
    }

    private function loadOrders($dom, $xmlRoot, $orders)
    {
        $ventas = $xmlRoot->appendChild($dom->createElement("ventas"));

        foreach ($orders as $order) {
            $detalleVentas = $ventas->appendChild($dom->createElement("detalleVentas"));

            $this->_($dom, $detalleVentas, 'tpIdCliente', $order->type_identification === 'ruc' ? '04' : '05');
            $this->_($dom, $detalleVentas, 'idCliente', $order->identication);

            if ($order->type_identification !== 'cf') {
                $this->_($dom, $detalleVentas, 'parteRelVtas', 'NO');
            }

            if ($order->type_identification === 'pasaporte') {
                $this->_($dom, $detalleVentas, 'tipoCliente', '01');
                $this->_($dom, $detalleVentas, 'denoCli', 'PERSONA EXTRANJERA');
            }

            $this->_($dom, $detalleVentas, 'tipoComprobante', $order->voucher_type == 1 ? 18 : '04');
            $this->_($dom, $detalleVentas, 'tipoEmision', 'F');
            $this->_($dom, $detalleVentas, 'numeroComprobantes', $order->numeroComprobantes);
            $this->_($dom, $detalleVentas, 'baseNoGraIva', 0);
            $this->_($dom, $detalleVentas, 'baseImponible', $order->base0);
            $this->_($dom, $detalleVentas, 'baseImpGrav', $order->base12);
            $this->_($dom, $detalleVentas, 'montoIva', $order->iva);
            $this->_($dom, $detalleVentas, 'montoIce', $order->ice);
            $this->_($dom, $detalleVentas, 'valorRetIva', 0);
            $this->_($dom, $detalleVentas, 'valorRetRenta', 0);

            $formasDePago = $detalleVentas->appendChild($dom->createElement("formasDePago"));
            $this->_($dom, $formasDePago, 'formaPago', '01');
        }
    }

    private function _($dom, $xmlRoot, $name, $value)
    {
        $xmlRoot->appendChild($dom->createElement($name, $value));
    }
}
