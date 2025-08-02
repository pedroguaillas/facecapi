<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Comprobante Electrónico</title>
</head>

<body>
    <table width="100%" cellpadding="0" cellspacing="0" style="background-color:#f5f5f5; padding: 20px;">
        <tr>
            <td align="center">
                <table width="600" cellpadding="0" cellspacing="0"
                    style="background-color:#ffffff; border-radius:8px; overflow:hidden; box-shadow:0 2px 8px rgba(0,0,0,0.05);">
                    <!-- Encabezado -->
                    <tr>
                        <td style="background-color:#0c4a6e; color:#ffffff; padding:20px; text-align:center;">
                            <h2 style="margin:0;">📄 Comprobante Electrónico</h2>
                            <p style="margin:0; font-size:14px;">Emitido por FACEC</p>
                        </td>
                    </tr>

                    <!-- Cuerpo del mensaje -->
                    <tr>
                        <td style="padding: 30px;">
                            <h3 style="color: #0c4a6e; margin:0; text-align:center;">{{ $customer }}</h3>
                            <p>
                                Recibiste el comprobante electrónico generado por nuestro sistema. Que esta adjunto en
                                este correo.
                            </p>

                            <h3 style="color: #0c4a6e; margin:0; text-align:center;">{{ $title }}</h3>

                            <p style="font-size: 14px; color: #555;">
                                Si tienes dudas o necesitas ayuda, puedes contactarnos por WhatsApp.
                            </p>
                        </td>
                    </tr>

                    <!-- Pie de página -->
                    <tr>
                        <td
                            style="background-color:#f1f5f9; padding:20px; font-size:12px; color:#555; text-align:center;">
                            FACEC | Firma y Facturación Electrónica
                            <br />
                            🌐 Visítanos en <a href="https://facec.ec" style="color:#555;">facec.ec</a>
                            &nbsp;&nbsp;|&nbsp;&nbsp; 📲 WhatsApp: <a href="https://wa.me/593959649714"
                                style="color:#555;">0959649714</a>

                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>

</html>