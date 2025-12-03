@extends('vouchers.theme.voucher')

@section('body')
    <tr>
        <td colspan="2">
            <div style="padding-top: .7em;">
                <table style="width: 100%;" class="table table-sm">
                    <tbody class="widthboder">
                        <tr>
                            <td class="relleno">Razón Social / Nombres Y Apellidos: {{ $movement->name }}</td>
                            <td class="align-middle">Identificación: {{ $movement->identication }}</td>
                        </tr>
                        <tr>
                            <td class="relleno">Fecha de Emisión: {{ date("d/m/Y", strtotime($movement->date)) }}</td>
                            @if($movement->guia)
                                <td class="align-middle">Guia de Remisión: {{ $movement->guia }}</td>
<<<<<<< HEAD
=======
                            @elseif($movement->email)
                                <td class="align-middle">Correo: {{ $movement->email }}</td>
>>>>>>> main
                            @endif
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
                <tbody>
                    <tr>
                        <th style="width: 5em;">Código</th>
                        <th style="width: 4em;">Cant.</th>
                        <th>Descripción</th>
                        <th style="width: 5em;">Precio Unitario</th>
                        @if($enabledDiscount)
                            <th style="width: 5em;">Descuento</th>
                        @endif
                        <th style="width: 5em;">Subtotal</th>
                    </tr>
                </tbody>
            </table>
        </td>
    </tr>
    <!-- No quitar esta tabla porque si no no hace salto de pagina -->
    @foreach($movement_items as $item)
        <tr>
            <td colspan="2">
                <table style="width: 100%;" class="table-collapse">
                    <tbody>
                        <tr>
                            <td style="text-align: center; width: 5em;">{{ $item->iva == 5 ? $item->aux_cod : $item->code }}
                            </td>
                            <td style="text-align: center; width: 4em;">{{ floatval($item->quantity) }}</td>
                            <td>{{ $item->name }}</td>
                            <td style="text-align: right; width: 5em;">{{ number_format($item->price, $company->decimal) }}</td>
                            @if($enabledDiscount)
                                <td style="text-align: right; width: 5em;">{{ number_format($item->discount, 2) }}</td>
                            @endif
                            <td style="text-align: right; width: 5em;">
<<<<<<< HEAD
                                {{ number_format($item->quantity * $item->price - $item->discount, 2) }}</td>
=======
                                {{ number_format($item->quantity * $item->price - $item->discount, 2) }}
                            </td>
>>>>>>> main
                        </tr>
                    </tbody>
                </table>
            </td>
        </tr>
    @endforeach
@endsection

@section('footer')
    <table style="width: 100%;">
        <tbody>
            <tr>
                <td style="width: 350px;">
                    @include('vouchers.theme.adiinfopayment')
                </td>
                <td style="width: 200px;">@include('vouchers.theme.total')</td>
            </tr>
        </tbody>
    </table>
<<<<<<< HEAD
=======

    @if($repayments->count() > 0)
        <table style="width: 100%; border-radius: 10px; margin-top: .5em;" class="table-collapse">
            <thead>
                <tr>
                    <th colspan="6">FACTURAS REEMBOLSADAS</th>
                </tr>
                <tr>
                    <th>Identificación</th>
                    <th>Secuencia</th>
                    <th>Fecha</th>
                    <th>Base Imp</th>
                    <th>IVA</th>
                    <th>Total</th>
                </tr>
            </thead>
            <tbody>
                @foreach($repayments as $repayment)
                    <tr>
                        <td style="text-align: center;">{{ $repayment->identification }}</td>
                        <td style="text-align: center;">{{ $repayment->sequential }}</td>
                        <td style="text-align: center;">{{ date("d/m/Y", strtotime($repayment->date)) }}</td>
                        <td style="text-align: right;">{{ number_format($repayment->base, 2) }}</td>
                        <td style="text-align: right;">{{ number_format($repayment->iva, 2) }}</td>
                        <th style="text-align: right;">{{ number_format($repayment->base + $repayment->iva, 2) }}</th>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
>>>>>>> main
@endsection