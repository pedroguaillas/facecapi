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
            ->select(
                'referral_guides.*',
                'c.name',
                'ca.name AS carrier_name',
                \DB::raw("DATE_FORMAT(date_start, '%d-%m-%Y') as date_start"),
                \DB::raw("DATE_FORMAT(date_end, '%d-%m-%Y') as date_end"),
            )
            ->where('c.branch_id', $branch->id)
            ->orderBy('referral_guides.created_at', 'DESC');

        return ReferralGuideResources::collection($referralguide->paginate());
    }

    public function create()
    {
        $auth = Auth::user();
        $level = $auth->companyusers->first();
        $company = Company::find($level->level_id);

        $points = Branch::selectRaw("branches.id AS branch_id,LPAD(store,3,'0') AS store,ep.id,LPAD(point,3,'0') AS point,ep.referralguide,recognition")
            ->leftJoin('emision_points AS ep', 'branches.id', 'branch_id')
            ->where('company_id', $company->id)
            ->get();

        return response()->json([
            'points' => $points
        ]);
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
                $emisionPoint->referralguide = (int) substr($request->serie, 8) + 1;
                $emisionPoint->save();

                if ($request->get('send')) {
                    (new ReferralGuideXmlController())->xml($referralguide->id);
                }
            }
        }
    }

    public function show($id)
    {
        $referralguide = ReferralGuide::find($id);

        $filteredReferralguide = collect($referralguide->toArray())
            ->filter(function ($value) {
                return !is_null($value);
            })
            ->all();

        $products = Product::join('referral_guide_items AS rgi', 'product_id', 'products.id')
            ->select('products.*')
            ->where('referral_guide_id', $id)
            ->get();

        $referralguide_items = Product::join('referral_guide_items AS rgi', 'product_id', 'products.id')
            ->select('rgi.id', 'quantity', 'name', 'product_id')
            ->where('referral_guide_id', $id)
            ->get()
            ->map(function ($item) {
                $item->quantity = floatval($item->quantity);
                return $item;
            });

        $customers = Customer::where('id', $referralguide->customer_id)->get();
        $carriers = Carrier::where('id', $referralguide->carrier_id)->get();

        return response()->json([
            'referralguide' => $filteredReferralguide,
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
            ->select('quantity', 'name', 'code')
            ->where('referral_guide_id', $id)
            ->get();

        $auth = Auth::user();
        $level = $auth->companyusers->first();
        $company = Company::find($level->level_id);

        $branch = Branch::where([
            'company_id' => $company->id,
            'store' => (int) substr($movement->serie, 0, 3),
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
