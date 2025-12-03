<?php

namespace App\Services;

use App\Models\IvaTax;
use App\Models\Order;
use App\Models\Customer;
use App\Models\Company;
use App\Models\Branch;
use App\Models\EmisionPoint;
use App\Http\Controllers\OrderXmlController;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class OrderStoreService
{
    /**
     * Crear una nueva orden de venta
     */
    public function createOrder(array $data): Order
    {
        $orderSaved = DB::transaction(function () use ($data) {
            $customer = Customer::find($data['customer_id']);
            $this->validateFinalConsumer($customer, $data['total']);

            $company = $this->getAuthCompany();
            $branch = $this->getCompanyBranch($company->id);
            $emisionPoint = EmisionPoint::find($data['point_id']);

            $orderData = $this->prepareOrderData($data, $emisionPoint);
            $order = $branch->orders()->create($orderData);

            $this->createOrderItems($order, $data['products'], $company);
            $this->updateEmisionPointSequence($emisionPoint, $data['voucher_type']);
            $this->createRepayments($order, $data['repayments'] ?? []);
            $this->createOrderAditionals($order, $data['aditionals'] ?? []);

            return $order;
        });

        if ($data['send'] ?? false) {
            $this->sendToSRI($orderSaved->id);
        }

        return $orderSaved;
    }

    /**
     * Validar venta a consumidor final
     */
    protected function validateFinalConsumer(?Customer $customer, float $total): void
    {
        if ($customer && $customer->identication === '9999999999999' && $total > 50) {
            throw new \Exception('No es posible una venta mayor a $50 a consumidor final.');
        }
    }

    /**
     * Obtener la compañía del usuario autenticado
     */
    protected function getAuthCompany(): Company
    {
        $auth = Auth::user();
        $level = $auth->companyusers->first();
        return Company::find($level->level_id);
    }

    /**
     * Obtener la sucursal de la compañía
     */
    protected function getCompanyBranch(int $companyId): Branch
    {
        return Branch::where('company_id', $companyId)
            ->orderBy('created_at')
            ->first();
    }

    /**
     * Preparar datos de la orden
     */
    protected function prepareOrderData(array $data, EmisionPoint $emisionPoint): array
    {
        $input = collect($data)->except(['products', 'send', 'aditionals', 'point_id'])->toArray();

        // Limpiar guía vacía
        if (array_key_exists('guia', $input) && trim($input['guia']) === '') {
            $input['guia'] = null;
        }

        // Generar serie completa
        $serie = $this->generateSerie($data['serie'], $emisionPoint, $data['voucher_type']);
        $input['serie'] = $serie;

        return $input;
    }

    /**
     * Generar la serie del comprobante
     */
    protected function generateSerie(string $serieBase, EmisionPoint $emisionPoint, int $voucherType): string
    {
        $serie = substr($serieBase, 0, 8);
        $field = $voucherType == 1 ? 'invoice' : 'creditnote';
        $sequence = str_pad($emisionPoint->{$field}, 9, "0", STR_PAD_LEFT);
        
        return $serie . $sequence;
    }

    /**
     * Crear items de la orden
     */
    protected function createOrderItems(Order $order, array $products, Company $company): void
    {
        if (empty($products)) {
            return;
        }

        $orderItems = [];
        $inventoryItems = [];

        foreach ($products as $product) {
            $orderItems[] = [
                'product_id' => $product['product_id'],
                'quantity' => $product['quantity'],
                'price' => $product['price'],
                'discount' => $product['discount'],
                'ice' => $product['ice'] ?? 0,
                'iva' => $product['iva'],
            ];

            if ($company->inventory) {
                $inventoryItems[] = [
                    'product_id' => $product['product_id'],
                    'quantity' => $product['quantity'],
                    'price' => $product['price'],
                    'type' => 'Venta',
                    'date' => Carbon::today()->format('Y-m-d')
                ];
            }
        }

        $order->orderitems()->createMany($orderItems);

        if ($company->inventory && !empty($inventoryItems)) {
            $order->inventories()->createMany($inventoryItems);
        }
    }

    /**
     * Actualizar secuencia del punto de emisión
     */
    protected function updateEmisionPointSequence(EmisionPoint $emisionPoint, int $voucherType): void
    {
        $field = $voucherType == 1 ? 'invoice' : 'creditnote';
        $emisionPoint->{$field}++;
        $emisionPoint->save();
    }

    /**
     * Crear rembolsos
     */
    protected function createRepayments(Order $order, array $repayments): void
    {
        if (empty($repayments)) {
            return;
        }

        $validRepayments = array_filter($repayments, function ($repayment) {
            return !empty($repayment['identification']) && !empty($repayment['sequential']) && !empty($repayment['date']) && !empty($repayment['authorization']);
        });

        $ivaTaxes = IvaTax::all(['code', 'percentage'])->pluck('percentage','code');

        foreach($validRepayments as $repayment) {
            $contec = substr($repayment['identification'], 2, 1);
                if ($contec == 6 || $contec == 9) {
                    $type_prov = 2;
                } else {
                    $type_prov = 1;
                }

                $newRepayment = $order->repayments()->create([
                    'type_id_prov' => 4, // Asume que tiene RUC
                    'identification' => $repayment['identification'],
                    'cod_country' => 593, // Ecuador
                    'type_prov' => $type_prov,
                    'type_document' => 1,
                    'sequential' => $repayment['sequential'],
                    'date' => $repayment['date'],
                    'authorization' => $repayment['authorization'],
                ]);

                $repaymentTaxes = [];
                
                foreach($repayment['repaymentTaxes'] as $tax) {
                    $repaymentTaxes[] = [
                        'iva_tax_code' => $tax['iva_tax_code'],
                        'percentage' => $ivaTaxes[$tax['iva_tax_code']],
                        'base' => $tax['base'],
                        'iva' => $tax['iva'],
                    ];
                }
                $newRepayment->repaymenttaxes()->createMany($repaymentTaxes);
        }
    }

    /**
     * Crear información adicional de la orden
     */
    protected function createOrderAditionals(Order $order, array $aditionals): void
    {
        if (empty($aditionals)) {
            return;
        }

        $validAditionals = array_filter($aditionals, function ($aditional) {
            return !empty($aditional['name']) && !empty($aditional['description']);
        });

        $formattedAditionals = array_map(function ($aditional) {
            return [
                'name' => $aditional['name'],
                'description' => $aditional['description']
            ];
        }, $validAditionals);

        if (!empty($formattedAditionals)) {
            $order->orderaditionals()->createMany($formattedAditionals);
        }
    }

    /**
     * Enviar comprobante al SRI
     */
    protected function sendToSRI(int $orderId): void
    {
        (new OrderXmlController())->xml($orderId);
    }
}