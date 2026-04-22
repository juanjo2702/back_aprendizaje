<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Certificado {{ $certificate->certificate_code }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; color: #111827; margin: 0; padding: 0; }
        .page { padding: 48px; border: 12px solid #5f62ff; min-height: 680px; position: relative; }
        .eyebrow { color: #5f62ff; font-size: 14px; text-transform: uppercase; letter-spacing: 2px; }
        h1 { font-size: 38px; margin: 18px 0 8px; }
        h2 { font-size: 28px; margin: 10px 0 18px; }
        p { font-size: 15px; line-height: 1.6; }
        .meta { margin-top: 26px; display: flex; justify-content: space-between; align-items: flex-end; }
        .meta-box { width: 48%; }
        .label { font-size: 11px; color: #6b7280; text-transform: uppercase; letter-spacing: 1px; }
        .value { font-size: 16px; font-weight: bold; margin-top: 6px; }
        .qr { text-align: right; }
        .footer { position: absolute; left: 48px; right: 48px; bottom: 36px; font-size: 12px; color: #6b7280; }
    </style>
</head>
<body>
    <div class="page">
        <div class="eyebrow">Certificado de finalización</div>
        <h1>{{ $certificate->student_name }}</h1>
        <p>ha completado satisfactoriamente el curso</p>
        <h2>{{ $certificate->course_name }}</h2>

        <p>
            Puntaje final: <strong>{{ number_format((float) $certificate->final_score, 2) }}%</strong><br>
            Código único: <strong>{{ $certificate->certificate_code }}</strong><br>
            Emitido el: <strong>{{ $issuedDate }}</strong>
        </p>

        <div class="meta">
            <div class="meta-box">
                <div class="label">Verificación</div>
                <div class="value">{{ $verificationUrl }}</div>
            </div>
            <div class="qr">
                <img src="{{ $qrCodeBase64 }}" alt="QR de validación" width="120" height="120">
            </div>
        </div>

        <div class="footer">
            Documento generado automáticamente por la plataforma LMS. Escanea el QR para validar autenticidad.
        </div>
    </div>
</body>
</html>
