@extends('vouchers.theme.voucher')

@section('body')
<tr>
    <td colspan="2">
        <div style="padding-top: .5em;">
            <table style="width: 100%;" class="table table-sm">
                <tbody class="widthboder">
                    <tr>
                        <td class="relleno" colspan="2">Identificación (Transportista):</td>
                        <td class="relleno" colspan="3">{{ $movement->ca_identication }}</td>
                    </tr>
                    <tr>
                        <td class="relleno" colspan="2">Razón Social / Nombres y Apellidos:</td>
                        <td class="relleno" colspan="3">{{ $movement->ca_name }}</td>
                    </tr>
                    <tr>
                        <td class="relleno">Placa:</td>
                        <td class="relleno" colspan="4">{{ $movement->license_plate }}</td>
                        <td></td>
                    </tr>
                    <tr>
                        <td class="relleno">Punto de Partida:</td>
                        <td class="relleno" colspan="4">{{ $movement->address_from }}</td>
                        <td></td>
                    </tr>
                    <tr>
                        <td class="relleno" style="width: 100px;">Fecha inicio:</td>
                        <td class="relleno" colspan="2">{{ $movement->date_start }}</td>
                        <td class="relleno">Fecha fin transporte:</td>
                        <td class="relleno">{{ $movement->date_end }}</td>
                        <td></td>
                    </tr>
                </tbody>
            </table>
        </div>
    </td>
</tr>
<tr>
    <td colspan="2">
        <div style="padding-top: .5em;">
            <table style="width: 100%;" class="table table-sm">
                <tbody class="widthboder">
                    <tr>
                        <td class="relleno" style="width: 200px;">Comprobante de Venta:</td>
                        <td class="relleno">{{ $movement->serie_invoice }}</td>
                        <td class="relleno">Fecha de Emisión:</td>
                        <td class="relleno">{{ $movement->date_invoice }}</td>
                    </tr>
                    <tr>
                        <td class="relleno">Número de Autorización:</td>
                        <td class="relleno" colspan="3">{{ $movement->authorization_invoice }}</td>
                    </tr>
                    <tr>
                        <td class="relleno">Motivo Traslado:</td>
                        <td class="relleno" colspan="3">{{ $movement->reason_transfer }}</td>
                        <td></td>
                    </tr>
                    <tr>
                        <td class="relleno">Destino(Punto de llegada):</td>
                        <td class="relleno" colspan="3">{{ $movement->address_to }}</td>
                        <td></td>
                    </tr>
                    <tr>
                        <td class="relleno">Identificación (Destinatario):</td>
                        <td class="relleno" colspan="3">{{ $movement->identication }}</td>
                    </tr>
                    <tr>
                        <td class="relleno">Razón Social/Nombres Apellidos:</td>
                        <td class="relleno" colspan="3">{{ $movement->name }}</td>
                    </tr>
                    <tr>
                        <td class="relleno">Documento Aduanero:</td>
                        <td class="relleno" colspan="3">{{ $movement->customs_doc }}</td>
                    </tr>
                    <tr>
                        <td class="relleno">Código Establecimiento Destino:</td>
                        <td class="relleno" colspan="3">{{ $movement->branch_destiny }}</td>
                    </tr>
                    <tr>
                        <td class="relleno">Ruta:</td>
                        <td class="relleno" colspan="3">{{ $movement->route }}</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </td>
</tr>
<tr>
    <td colspan="2">
        <table style="width: 100%;margin-top: .5em;" class="table-collapse">
            <tbody>
                <tr>
                    <th style="width: 5em;">Cantidad</th>
                    <th>Descripción</th>
                    <th style="text-align: center; width: 8em;">Código Principal</th>
                </tr>
            </tbody>
        </table>
    </td>
</tr>
@foreach($movement_items as $item)
<tr>
    <td colspan="2">
        <table style="width: 100%;" class="table-collapse">
            <tbody>
                <tr>
                    <td style="text-align: center; width: 5em;">{{ number_format($item->quantity, $company->decimal) }}</td>
                    <td>{{ $item->name }}</td>
                    <td style="text-align: center; width: 8em;">{{ $item->code }}</td>
                </tr>
            </tbody>
        </table>
    </td>
</tr>
@endforeach
@endsection