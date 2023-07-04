@extends('vouchers.theme.voucher')

@section('body')
<tr>
    <td colspan="2">
        <div style="padding-top: .5em;">
            <table style="width: 100%;" class="table table-sm">
                <tbody class="widthboder">
                    <tr>
                        <td class="relleno">Nombres Y Apellidos: {{ $movement->name }}</td>
                        <td class="align-middle">Identificación: {{ $movement->identication }}</td>
                    </tr>
                    <tr>
                        <td class="relleno">Fecha: {{ date( "d/m/Y", strtotime( $movement->date ) ) }}</td>
                    </tr>
                    <tr>
                        <td class="relleno" colspan="2">Dirección: {{ $movement->address }}</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </td>
</tr>
<tr>
    <td colspan="2">
        <table style="width: 100%; border-radius: 10px; margin-top: .5em;" class="table-collapse">
            <thead>
                <tr>
                    <th style="width: 5em;">Cod. Principal</th>
                    <th style="width: 4em;">Cant.</th>
                    <th>Descripción</th>
                    <th style="width: 5em;">Precio Unitario</th>
                    <th style="width: 5em;">Descuento</th>
                    <th style="width: 5em;">Subtotal</th>
                </tr>
            </thead>
            <tbody>
                @foreach($movement_items as $item)
                <tr>
                    <td style="padding: .5em; text-align: center;">{{ $item->code }}</td>
                    <td style="padding: .5em; text-align: center;">{{ number_format($item->quantity, $company->decimal) }}</td>
                    <td style="padding: .5em;">{{ $item->name }}</td>
                    <td style="padding: .5em; text-align: right;">{{ number_format($item->price, $company->decimal) }}</td>
                    <td style="padding: .5em; text-align: right;">{{ number_format($item->discount, 2) }}</td>
                    <td style="padding: .5em; text-align: right;">{{ number_format($item->quantity * $item->price, 2) }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </td>
</tr>
@endsection

@section('footer')
<table style="width: 100%;">
    <tbody>
        <tr>
            <td style="width: 350px;">
                @include('vouchers.theme.payment')
            </td>
            <td>@include('vouchers.theme.total')</td>
        </tr>
    </tbody>
</table>
@endsection