<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Estado de situación financiera</title>
    <style>
        @page {
            margin: 5em 2.5em 3em;
        }
    </style>
</head>

<body>
    <main style="margin-top: 10px">
        <div style="text-align: center">
            <strong style="text-align: center; font-size: 1.5em">Estado de situación financiera</strong>
            <!-- <p style="margin-top: .3em;">Del 01/01/2020 al {{date('d/m/Y', strtotime('+5 hours'))}}</p> -->
            <p style="margin-top: .3em;">Del 01/01/2019 al 31/12/2019</p>
            <p style="margin-top: -.5em;">Tipo: General</p>
        </div>
        <table>
            <tbody>
                @foreach($charts as $chart)
                <tr>
                    <td style="width: 130px">
                        @if( substr_count($chart['account'], '.') > 3 )
                        {{ $chart['account'] }}
                        @else
                        <strong>{{ $chart['account'] }}</strong>
                        @endif
                    </td>
                    <td style="width: 430px">
                        @if( substr_count($chart['account'], '.') > 3 )
                        {{$chart['name']}}
                        @else
                        <strong>{{$chart['name']}}</strong>
                        @endif
                    </td>
                    <td style="width: 130px; text-align: right">
                        @if( substr_count($chart['account'], '.') > 3 )
                        {{number_format((float)$chart['amount'], 2, '.', ',')}}
                        @else
                        <strong>{{number_format((float)$chart['amount'], 2, '.', ',')}}</strong>
                        @endif
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </main>
    <footer>
        <div style="margin-top: 5em; display: block; width: 500px">
            <span style="display: inline-block; margin-left: 10em">____________________</span>
            <span style="display: inline-block; margin-left: 5em">____________________</span>
        </div>
        <div style="margin-top: .1em; display: block; width: 500px">
            <span style="display: inline-block; margin-left: 12em">Gerente general</span>
            <span style="display: inline-block; margin-left: 10em">Contador</span>
        </div>
    </footer>
</body>

</html>