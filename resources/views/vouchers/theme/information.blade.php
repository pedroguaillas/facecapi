    <table>
        <tbody class="widthboder">
            <tr style="font-size: 16px;">
                <td class="relleno">R.U.C.:</td>
                <td class="align-middle">{{ $company->ruc }}</td>
            </tr>
            <tr>
                <th style="font-size: 16px; letter-spacing: 2px;" class="align-middle" colspan="2">
                    @switch($movement->voucher_type)
                    @case(1)
                    FACTURA
                    @break

                    @case(3)
                    LIQUIDACIÓN EN COMPRA
                    @break

                    @case(4)
                    NOTA DE CRÉDITO
                    @break

                    @case(5)
                    NOTA DE DÉDITO
                    @break

                    @case(6)
                    GUIA DE REMISIÓN
                    @break

                    @case(7)
                    COMPROBANTE DE RETENCIÓN
                    @break

                    @default
                    OTROS
                    @endswitch
                </th>
            </tr>
            <tr>
                <td class="relleno">No.</td>
                <td class="align-middle">{{ $movement->serie }}</td>
            </tr>
            <tr>
                <td class="relleno" colspan="2">NÚMERO DE AUTORIZACIÓN</td>
            </tr>
            <tr>
                <td class="relleno" colspan="2">{{ $movement->authorization }}</td>
            </tr>
            <tr>
                <td class="relleno">FECHA Y HORA DE AUTORIZACIÓN: </td>
                <td class="align-middle">
                    {{ $movement->autorized !== null ? date( "d/m/Y H:i:s.000", strtotime( $movement->autorized ) ) : null }}
                </td>
            </tr>
            <tr>
                <td class="relleno">AMBIENTE:</td>
                <td class="align-middle">{{ $movement->xml!== null ? (int)substr($movement->xml, -30, 1) === 1 ? 'PRUEBAS' : 'PRODUCCION' : null }}</td>
            </tr>
            <tr>
                <td class="relleno">EMISIÓN:</td>
                <td class="align-middle">NORMAL</td>
            </tr>
            <tr>
                <td class="relleno" colspan="2">CLAVE DE ACCESO</td>
            </tr>
            <tr>
                <td class="relleno" colspan="2">{{ substr($movement->xml, -53, 49) }}</td>
            </tr>
            <!-- <tr>
                <td class="relleno" colspan="2">
                    Código de barra
                </td>
            </tr> -->
        </tbody>
    </table>