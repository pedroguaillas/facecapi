<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Auth;
use App\Models\Customer;
use Illuminate\Http\Request;
use App\Models\Company;
use App\Http\Resources\CustomerResources;
use App\Models\Branch;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class CustomerController extends Controller
{
    public function customerlist(Request $request = null)
    {
        $auth = Auth::user();
        $level = $auth->companyusers->first();
        $company = Company::find($level->level_id);
        $branch = Branch::where('company_id', $company->id)
            ->orderBy('created_at')->first();

        $search = '';
        $paginate = 15;

        if ($request) {
            $search = $request->search;
            $paginate = $request->has('paginate') ? $request->paginate : $paginate;
        }

        $customers = Customer::where('branch_id', $branch->id)
            ->where(function ($query) use ($search) {
                return $query->where('identication', 'LIKE', "%$search%")
                    ->orWhere('name', 'LIKE', "%$search%");
            })
            ->orderBy('created_at', 'DESC');

        return CustomerResources::collection($customers->paginate($paginate));
    }

    public function store(Request $request)
    {
        $auth = Auth::user();
        $level = $auth->companyusers->first();
        $company = Company::find($level->level_id);

        try {
            $customer = Branch::where('company_id', $company->id)
                ->orderBy('created_at')->first()
                ->customers()->create($request->all());
            return response()->json(['customer' => $customer]);
        } catch (\Illuminate\Database\QueryException $e) {
            $errorCode = $e->errorInfo[1];
            if ($errorCode == 1062) {
                return response()->json(['message' => 'KEY_DUPLICATE']);
            }
        }
    }

    public function edit(int $id)
    {
        $customer = Customer::find($id);
        return response()->json(['customer' => $customer]);
    }

    public function update(Request $request, int $id)
    {
        $customer = Customer::findOrFail($id);

        try {
            $customer->update($request->all());
        } catch (\Illuminate\Database\QueryException $e) {
            $errorCode = $e->errorInfo[1];
            if ($errorCode == 1062) {
                return response()->json(['message' => 'KEY_DUPLICATE']);
            }
        }
    }

    public function importCsv(Request $request)
    {
        $auth = Auth::user();
        $level = $auth->companyusers->first();
        $company = Company::find($level->level_id);
        $branch = Branch::where('company_id', $company->id)
            ->orderBy('created_at')->first();

        $customers = $request->get('customers');

        $newcustomers = [];
        foreach ($customers as $customer) {
            array_push($newcustomers, [
                'type_identification' => $customer['type_identification'],
                'identication' => $customer['identication'],
                'name' => $customer['name'],
                'address' => $customer['address'] === '' ? null : $customer['address'],
                'email' => $customer['email'] === '' ? null : $customer['email'],
                'phone' => $customer['phone'] === '' ? null : $customer['phone'],
            ]);
        }
        $customer = Branch::where('company_id', $company->id)
            ->orderBy('created_at')->first()
            ->customers()->createMany($newcustomers);

        $customers = Customer::where('branch_id', $branch->id);

        return CustomerResources::collection($customers->latest()->paginate());
    }

    public function export()
    {
        $auth = Auth::user();
        $level = $auth->companyusers->first();
        $company = Company::find($level->level_id);
        $branch = Branch::where('company_id', $company->id)
            ->orderBy('created_at')->first();

        $spreadsheet = new Spreadsheet();
        $activeWorksheet = $spreadsheet->getActiveSheet();
        $activeWorksheet->setCellValue('A1', 'Tipo ID');
        $activeWorksheet->setCellValue('B1', 'Identificación');
        $activeWorksheet->setCellValue('C1', 'Cliente');
        $activeWorksheet->setCellValue('D1', 'Dirección');
        $activeWorksheet->setCellValue('E1', 'Teléfono');
        $activeWorksheet->setCellValue('F1', 'Correo');

        $customers = Customer::select('type_identification', 'identication', 'name', 'address', 'phone', 'email')
            ->where('branch_id', $branch->id)
            ->where('type_identification', '<>', 'cf')
            ->get();

        $row = 2;

        foreach ($customers as $customer) {
            $activeWorksheet->setCellValue('A' . $row, $customer->type_identification);
            $activeWorksheet->getCell('B' . $row)->setValueExplicit($customer->identication, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
            $activeWorksheet->setCellValue('C' . $row, $customer->name);
            $activeWorksheet->setCellValue('D' . $row, $customer->address);
            $activeWorksheet->getCell('E' . $row)->setValueExplicit($customer->phone, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
            $activeWorksheet->setCellValue('F' . $row, $customer->email);
            $row++;
        }

        $filename = Storage::path("customers.xlsx");

        try {
            $writer = new Xlsx($spreadsheet);
            $writer->save($filename);
            $content = file_get_contents($filename);

            unlink($filename);

            return $content;
        } catch (Exception $e) {
            exit($e->getMessage());
        }

        exit($content);
    }
}
