<div style="width: 400px;">
    <div class="parent-img">
        <img src="{{ storage_path('app/logos/' .$company->logo_dir) }}" alt="Logo" style="width: auto; height: 125px;" />
    </div>
    <table style="margin-top: .5em; width: 100%;">
        <tbody class="widthboder">
            <tr>
                <td class="relleno" style="text-align: center;" colspan="3">{{ $company->company }}</td>
            </tr>
            @if($branch->name !== null)
            <tr>
                <th class="relleno" colspan="3">{{ $branch->name }}</th>
            </tr>
            @endif
            <tr>
                <td class="relleno">Dirección</td>
                <td class="align-middle" colspan="2">{{ $branch->address }}</td>
            </tr>
            <tr>
                <td class="relleno" colspan="2">Obligado a llevar contabilidad</td>
                <td class="align-middle">{{ $company->accounting ? 'SI' : 'NO' }}</td>
            </tr>
            @if($movement->voucher_type!==4)

            @if($company->retention_agent)
            <tr>
                <td class="relleno" colspan="2">Agente de Retención Resolución No.</td>
                <td class="align-middle">{{ $company->retention_agent }}</td>
            </tr>
            @endif

            @if($company->rimpe === 1)
            <tr>
                <td style="text-align: left;" class="relleno" colspan="3">CONTRIBUYENTE RÉGIMEN RIMPE</td>
            </tr>
            @elseif($company->rimpe === 2)
            <tr>
                <td style="text-align: left;" class="relleno" colspan="3">CONTRIBUYENTE NEGOCIO POPULAR - RÉGIMEN RIMPE</td>
            </tr>
            @endif

            @endif
        </tbody>
    </table>
</div>