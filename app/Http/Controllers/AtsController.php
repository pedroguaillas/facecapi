<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Auth;
use App\Models\Branch;
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
        // Provider
        $select = 'p.type_identification,p.identication,p.name,';
        // Order
        $select .= 'voucher_type,date,serie,authorization,no_iva,base0,base12,ice,iva,';
        // Retention
        $select .= 'serie_retencion,date_retention,authorization_retention,';
        // Retention IVA Items
        $select .= '(SELECT SUM(value) FROM shop_retention_items WHERE shop_retention_items.shop_id = shops.id AND shop_retention_items.code = 2 AND shop_retention_items.tax_code = 9) AS r10,';
        $select .= '(SELECT SUM(value) FROM shop_retention_items WHERE shop_retention_items.shop_id = shops.id AND shop_retention_items.code = 2 AND shop_retention_items.tax_code = 10) AS r20,';
        $select .= '(SELECT SUM(value) FROM shop_retention_items WHERE shop_retention_items.shop_id = shops.id AND shop_retention_items.code = 2 AND shop_retention_items.tax_code = 1) AS r30,';
        $select .= '(SELECT SUM(value) FROM shop_retention_items WHERE shop_retention_items.shop_id = shops.id AND shop_retention_items.code = 2 AND shop_retention_items.tax_code = 11) AS r50,';
        $select .= '(SELECT SUM(value) FROM shop_retention_items WHERE shop_retention_items.shop_id = shops.id AND shop_retention_items.code = 2 AND shop_retention_items.tax_code = 2) AS r70,';
        $select .= '(SELECT SUM(value) FROM shop_retention_items WHERE shop_retention_items.shop_id = shops.id AND shop_retention_items.code = 2 AND shop_retention_items.tax_code = 3) AS r100,';
        // Retention Renta Items
        $select .= 'sri.tax_code,sri.base,sri.porcentage,sri.value';

        $shops = Shop::join('providers AS p', 'provider_id', 'p.id')
            ->leftJoin('shop_retention_items AS sri', function ($query) {
                $query->on('sri.shop_id', 'shops.id')
                    ->where('code', 1);
            })
            ->selectRaw($select)
            ->whereYear('date', $year)
            ->whereMonth('date', $month);

        $shops = $shops->get();

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

        for ($i = 0; $i < $shops->count(); $i++) {

            $shop = $shops[$i];

            $detalleCompras = $compras->appendChild($dom->createElement("detalleCompras"));

            $this->_($dom, $detalleCompras, 'codSustento', $shop->voucher_type === 2 ? '02' : '01');
            $this->_($dom, $detalleCompras, 'tpIdProv', $shop->type_identification === 'ruc' ? '01' : ($shop->type_identification === 'cédula' ? '02' : '03'));
            $this->_($dom, $detalleCompras, 'idProv', $shop->identication);
            $this->_($dom, $detalleCompras, 'tipoComprobante', str_pad($shop->voucher_type, 2, 0, STR_PAD_LEFT));
            $this->_($dom, $detalleCompras, 'tipoProv', $shop->type_identification === 'ruc' && (substr($shop->identication, 2, 1) === '9' || substr($shop->identication, 2, 1) === '6') ? '02' : '01');
            $this->_($dom, $detalleCompras, 'denoProv', $this->removeOtherCharacter($shop->name));
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

            $this->_($dom, $detalleCompras, 'valRetBien10', $shop->r10 ?? 0);
            $this->_($dom, $detalleCompras, 'valRetServ20', $shop->r20 ?? 0);
            $this->_($dom, $detalleCompras, 'valorRetBienes', $shop->r30 ?? 0);
            $this->_($dom, $detalleCompras, 'valRetServ50', $shop->r50 ?? 0);
            $this->_($dom, $detalleCompras, 'valorRetServicios', $shop->r70 ?? 0);
            $this->_($dom, $detalleCompras, 'valRetServ100', $shop->r100 ?? 0);
            $this->_($dom, $detalleCompras, 'valorRetencionNc', 0);
            $this->_($dom, $detalleCompras, 'totbasesImpReemb', 0);

            $pagoExterior = $detalleCompras->appendChild($dom->createElement("pagoExterior"));
            $this->_($dom, $pagoExterior, 'pagoLocExt', '01');
            $this->_($dom, $pagoExterior, 'paisEfecPago', 'NA');
            $this->_($dom, $pagoExterior, 'aplicConvDobTrib', 'NA');
            $this->_($dom, $pagoExterior, 'pagExtSujRetNorLeg', 'NA');

            if ($shop->voucher_type !== 3) {
                $formasDePago = $detalleCompras->appendChild($dom->createElement("formasDePago"));
                $j = $i;

                do {
                    $this->_($dom, $formasDePago, 'formaPago', $shops[$j]->base > 999.99 ? '20' : '01');
                    $j++;
                } while ($j < count($shops) && $shop->serie === $shops[$j]->serie && $shop->voucher_type === $shops[$j]->voucher_type && $shop->identication === $shops[$j]->identication);
            }

            if ($shop->tax_code !== null) {

                $air = $detalleCompras->appendChild($dom->createElement("air"));

                $j = $i;

                do {
                    $detalleAir = $air->appendChild($dom->createElement("detalleAir"));
                    $this->_($dom, $detalleAir, 'codRetAir', $shops[$j]->tax_code);
                    $this->_($dom, $detalleAir, 'baseImpAir', $shops[$j]->base);
                    $this->_($dom, $detalleAir, 'porcentajeAir', $shops[$j]->porcentage);
                    $this->_($dom, $detalleAir, 'valRetAir', $shops[$j]->value);

                    $j++;
                } while ($j < count($shops) && $shop->serie === $shops[$j]->serie && $shop->voucher_type === $shops[$j]->voucher_type && $shop->identication === $shops[$j]->identication);
                $i = $j - 1;
            }

            // Retencion info
            if ($shop->tax_code !== null && $shop->tax_code !== '332') {
                $this->_($dom, $detalleCompras, 'estabRetencion1', substr($shop->serie_retencion, 0, 3));
                $this->_($dom, $detalleCompras, 'ptoEmiRetencion1', substr($shop->serie_retencion, 4, 3));
                $this->_($dom, $detalleCompras, 'secRetencion1', substr($shop->serie_retencion, 8));
                $this->_($dom, $detalleCompras, 'autRetencion1', $shop->authorization_retention);
                $this->_($dom, $detalleCompras, 'fechaEmiRet1', $date);
            }

            //Notas
            if ($shop->voucher_type === 5 || $shop->voucher_type === 4) {
                $this->_($dom, $detalleCompras, 'docModificado', '01');
                $this->_($dom, $detalleCompras, 'estabModificado', substr($shop->serie, 0, 3));
                $this->_($dom, $detalleCompras, 'ptoEmiModificado', substr($shop->serie, 4, 3));
                $this->_($dom, $detalleCompras, 'secModificado', substr($shop->serie, 8));
                $this->_($dom, $detalleCompras, 'autModificado', $shop->authorization);
            }
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

    private function removeOtherCharacter($deno)
    {
        $permit = array("á", "é", "í", "ó", "ú", "ñ", "&");
        $replace = array("a", "e", "i", "o", "u", "n", "y");
        $deno = str_replace($permit, $replace, $deno);

        $permit = array("Á", "É", "Í", "Ó", "Ú", "Ñ", "&");
        $deno = str_replace($permit, $replace, $deno);

        $deno = strtoupper($deno);

        $count  = strlen($deno);
        $newc = str_split($deno);

        for ($i = 0; $i < $count; $i++) {
            if (($newc[$i] < 'A' || $newc[$i] > 'Z') && $newc[$i] != ' ') {
                $deno = str_replace($newc[$i], '', $deno);
            }
        }

        return $deno;
    }
}
