<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Company;
use App\Models\EmisionPoint;
use App\Models\Order;
use App\Models\ReferralGuide;
use App\Models\Shop;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Tymon\JWTAuth\Facades\JWTAuth;
use Illuminate\Support\Facades\Auth;
use App\Models\User;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        $name = $request->input('name');
        $user = $request->input('user');
        $email = $request->input('email');
        $rol = $request->input('rol');
        $password = Hash::make($request->input('password'));

        $register = User::create([
            'name' => $name,
            'user' => $user,
            'rol' => $rol,
            'email' => $email,
            'password' => $password
        ]);

        if ($register) {
            return response()->json([
                'success' => true,
                'message' => 'Register Success!',
                'data' => $register,
            ], 201);
        } else {
            return response()->json([
                'success' => false,
                'message' => 'Register Fail!',
                'data' => '',
            ], 401);
        }
    }

    public function login(Request $request)
    {
        //validate incoming request 
        $this->validate($request, [
            'user' => 'required|string',
            'password' => 'required|string',
        ]);

        $credentials = $request->only(['user', 'password']);

        // if (!$token = JWTAuth::attempt($credentials)) {
        if (!$token = Auth::attempt($credentials)) {
            return response()->json(['message' => 'Usuario o contraseña incorrecto'], 401);
        }

        $auth = JWTAuth::user();
        $level = $auth->companyusers->first();

        // Auth admin
        if ($auth->user_type_id === 1) {
            return response()->json([
                'token' => $token,
                'token_type' => 'bearer',
                'expires_in' => 3600,
                'user' => $auth,
            ]);
        }

        $company = Company::find($level->level_id);

        $points = Branch::select('branches.id AS branch_id', 'store', 'point')
            ->leftJoin('emision_points', 'branches.id', 'branch_id')
            ->where('company_id', $company->id)
            ->get();

        // Si existe una sucursal PERO no tiene el punto de emision
        if ($points->count() === 1 && $points[0]->point === null) {
            $this->createPoint($points[0]);
        }

        return response()->json([
            'token' => $token,
            'token_type' => 'bearer',
            'expires_in' => 3600,
            'user' => $auth,
            // Permisos
            'permissions' => [
                'inventory' => $company->inventory,
                'decimal' => $company->decimal,
                'printf' => $company->printf,
                'guia_in_invoice' => $company->guia_in_invoice,
                'import_in_invoice' => $company->import_in_invoice,
                'import_in_invoices' => $company->import_in_invoices,
            ]
        ]);
    }

    // Crear la secuencia de los comprobantes
    private function createPoint($branch)
    {
        $branch_id = $branch->branch_id;
        $store = $branch->store;

        $emisionPoint = new EmisionPoint();
        $point = null;

        // Crear el punto de emision
        $invoice = Order::select('serie')
            ->where([
                ['branch_id', $branch_id], // De la sucursal especifico
                ['voucher_type', 1] // 1-Factura
            ])
            ->orderBy('created_at', 'desc') // Para traer el ultimo
            // uso el take porque puede que no exista registros
            ->take(1)->get();

        if (!$this->validSerie($emisionPoint, $invoice, 'invoice', $store, $point))
            return;

        $cn = Order::select('serie')
            ->where([
                ['branch_id', $branch_id], // De la sucursal especifico
                ['voucher_type', 4] // 4-Nota-Credito
            ])
            ->orderBy('created_at', 'desc') // Para traer el ultimo
            ->take(1)->get();

        if (!$this->validSerie($emisionPoint, $cn, 'creditnote', $store, $point))
            return;

        $set_purchase = Shop::select('serie')
            ->where([
                ['branch_id', $branch_id], // De la sucursal específico
                ['voucher_type', 3] // 3-Liquidacion-de-compra
            ])
            ->orderBy('created_at', 'desc') // Para traer el ultimo
            ->take(1)->get();

        if (!$this->validSerie($emisionPoint, $set_purchase, 'settlementonpurchase', $store, $point))
            return;

        $retention = Shop::select('serie_retencion AS serie')
            ->where('branch_id', $branch_id) // De la sucursal específico
            ->orderBy('created_at', 'desc') // Para traer el ultimo
            ->take(1)->get();

        if (!$this->validSerie($emisionPoint, $retention, 'retention', $store, $point))
            return;

        $referralGuide = ReferralGuide::select('serie')
            ->where('branch_id', $branch_id) // De la sucursal especifico
            ->orderBy('created_at', 'desc') // Para traer el ultimo
            ->take(1)->get();

        if (!$this->validSerie($emisionPoint, $referralGuide, 'referralguide', $store, $point))
            return;

        // Si el Punto de Emision es nulo salir
        if ($point === null)
            return;

        $emisionPoint->branch_id = $branch_id;
        $emisionPoint->point = $point;
        $emisionPoint->save();
    }

    private function validSerie(EmisionPoint $emisionPoint, $invoice, $type, $store, &$point)
    {
        $correct = true;

        // Verificar que exista un registro
        if ($invoice->count()) {
            // Extraer el punto de emision de la serie
            $serie = $invoice[0]->serie;
            //Convert string to array
            $serie = explode("-", $serie);
            // El establecimiento de la serie debe ser igual al establecimiento que existe
            // El punto de emision debe ser al inicio nulo y despues tener un valor igual
            if (intval($serie[0]) === $store && ($point === null || $point === intval($serie[1]))) {
                $point = intval($serie[1]);
                $emisionPoint->{$type} = (int) $serie[2] + 1;
            } else {
                $correct = false;
            }
        }

        return $correct;
    }

    public function me()
    {
        $auth = Auth::user();
        $level = $auth->companyusers->first();
        $company = Company::find($level->level_id);

        return response()->json([
            'user' => $auth,
            'inventory' => $company->inventory === 1,
            'decimal' => $company->decimal
        ]);
    }

    public function refreshToken()
    {
        $refreshed = JWTAuth::refresh(JWTAuth::getToken());
        JWTAuth::setToken($refreshed)->toUser();
        return response()->json([
            'token' => $refreshed
        ]);
    }

    public function logout()
    {
        Auth::logout();
        return response()->json(['logout' => true]);
    }
}
