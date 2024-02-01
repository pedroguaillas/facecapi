<?php

namespace App\Http\Controllers;

use App\Models\Carrier;
use App\Models\Company;
use App\Models\Customer;
use App\Http\Resources\CarrierResources;
use App\Http\Resources\CustomerResources;
use App\Http\Resources\ProductResources;
use App\Http\Resources\ReferralGuideResources;
use App\Models\Branch;
use App\Models\EmisionPoint;
use Illuminate\Http\Request;
use App\Models\Product;
use App\Models\ReferralGuide;
use App\Models\ReferralGuideItem;
use Illuminate\Support\Facades\Auth;
use Barryvdh\DomPDF\Facade\Pdf;

class ReferralGuideController extends Controller
{

    public function index()
    {
        $auth = Auth::user();
        $level = $auth->companyusers->first();
        $company = Company::find($level->level_id);
        $branch = Branch::where('company_id', $company->id)
            ->orderBy('created_at')->first();

        $referralguide = ReferralGuide::join('carriers AS ca', 'ca.id', 'carrier_id')
            ->join('customers AS c', 'c.id', 'customer_id')
            ->select('referral_guides.*', 'c.name', 'ca.name AS carrier_name')
            ->where('c.branch_id', $branch->id)
            ->orderBy('referral_guides.created_at', 'DESC');

        return ReferralGuideResources::collection($referralguide->paginate());
    }

    public function create()
    {
        $auth = Auth::user();
        $level = $auth->companyusers->first();
        $company = Company::find($level->level_id);
        $branch = Branch::where('company_id', $company->id)
            ->orderBy('created_at')->first();

        $points = Branch::selectRaw("branches.id AS branch_id,LPAD(store,3,'0') AS store,ep.id,LPAD(point,3,'0') AS point,ep.referralguide,recognition")
            ->leftJoin('emision_points AS ep', 'branches.id', 'branch_id')
            ->where('company_id', $company->id)
            ->get();

        return response()->json([
            // 'serie' => $this->getSeries($branch)
            'points' => $points
        ]);
    }

    private function getSeries($branch)
    {
        $branch_id = $branch->id;
        $invoice = ReferralGuide::select('serie')
            ->where('branch_id', $branch_id) // De la sucursal especifico
            ->orderBy('created_at', 'desc') // Para traer el ultimo
            ->first();

        return $this->generedSerie($invoice, $branch->store);
    }

    //Return the serie of sales generated
    private function generedSerie($serie, $branch_store)
    {
        if ($serie != null) {
            $serie = $serie->serie;
            //Convert string to array
            $serie = explode("-", $serie);
            //Get value Integer from String & sum 1
            $serie[2] = (int) $serie[2] + 1;
            //Complete 9 zeros to left 
            $serie[2] = str_pad($serie[2], 9, 0, STR_PAD_LEFT);
            //convert Array to String
            $serie = implode("-", $serie);
        } else {
            $serie = str_pad($branch_store, 3, 0, STR_PAD_LEFT) . '-010-000000001';
        }

        return $serie;
    }

    public function store(Request $request)
    {
        $auth = Auth::user();
        $level = $auth->companyusers->first();
        $company = Company::find($level->level_id);
        $branch = Branch::where('company_id', $company->id)
            ->orderBy('created_at')->first();

        if ($referralguide = $branch->referralguides()->create($request->except(['products', 'send', 'point_id']))) {
            $products = $request->get('products');

            if (count($products) > 0) {
                $array = [];
                foreach ($products as $product) {
                    $array[] = [
                        'product_id' => $product['product_id'],
                        'quantity' => $product['quantity'],
                    ];
                }

                $referralguide->referralguidetems()->createMany($array);

                // Actualizar secuencia del comprobante
                $emisionPoint = EmisionPoint::find($request->point_id);
                $emisionPoint->referralguide = (int)substr($request->serie, 8) + 1;
                $emisionPoint->save();

                if ($request->get('send')) {
                    (new ReferralGuideXmlController())->xml($referralguide->id);
                }
            }
        }
    }

    public function show($id)
    {
        $referralguide = ReferralGuide::findOrFail($id);

        $products = Product::join('referral_guide_items AS rgi', 'product_id', 'products.id')
            ->select('products.*')
            ->where('referral_guide_id', $id)
            ->get();

        $referralguide_items = Product::join('referral_guide_items AS rgi', 'product_id', 'products.id')
            ->select('products.iva', 'rgi.*')
            ->where('referral_guide_id', $id)
            ->get();

        $customers = Customer::where('id', $referralguide->customer_id)->get();
        $carriers = Carrier::where('id', $referralguide->carrier_id)->get();

        return response()->json([
            'referralguide' => $referralguide,
            'referralguide_items' => $referralguide_items,
            'customers' => CustomerResources::collection($customers),
            'carriers' => CarrierResources::collection($carriers),
            'products' => ProductResources::collection($products),
        ]);
    }

    public function showPdf($id)
    {
        $movement = ReferralGuide::join('customers AS c', 'customer_id', 'c.id')
            ->join('carriers AS ca', 'carrier_id', 'ca.id')
            ->select('referral_guides.*', 'c.*', 'ca.identication AS ca_identication', 'ca.name AS ca_name', 'ca.license_plate')
            ->where('referral_guides.id', $id)
            ->first();

        $movement->voucher_type = 6;

        $movement_items = ReferralGuideItem::join('products AS p', 'p.id', 'product_id')
            ->select('p.*', 'referral_guide_items.quantity')
            ->where('referral_guide_id', $id)
            ->get();

        $auth = Auth::user();
        $level = $auth->companyusers->first();
        $company = Company::find($level->level_id);

        $branch = Branch::where([
            'company_id' => $company->id,
            'store' => (int)substr($movement->serie, 0, 3),
        ])->get();

        if ($branch->count() === 0) {
            $branch = Branch::where('company_id', $company->id)
                ->orderBy('created_at')->first();
        } elseif ($branch->count() === 1) {
            $branch = $branch->first();
        }

        $pdf = PDF::loadView('vouchers/referralguide', compact('movement', 'company', 'branch', 'movement_items'));

        return $pdf->stream();
    }

    public function update(Request $request, $id)
    {
        $referralguide = ReferralGuide::findOrFail($id);

        if ($referralguide->update($request->except(['products', 'send']))) {
            $products = $request->get('products');

            ReferralGuideItem::where('referral_guide_id', $referralguide->id)->delete();

            if (count($products) > 0) {
                $array = [];
                foreach ($products as $product) {
                    $array[] = [
                        'product_id' => $product['product_id'],
                        'quantity' => $product['quantity'],
                    ];
                }
                $referralguide->referralguidetems()->createMany($array);
            }
        }
    }
}
