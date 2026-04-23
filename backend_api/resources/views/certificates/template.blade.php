<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Certificado {{ $certificate->certificate_code }}</title>
    <style>
        * { box-sizing: border-box; }

        @page {
            size: A4 landscape;
            margin: 10mm;
        }

        html, body {
            margin: 0;
            padding: 0;
            font-family: DejaVu Sans, Arial, sans-serif;
            color: #1f2f44;
            background: #f6f2ea;
        }

        .preview-toolbar {
            position: sticky;
            top: 0;
            z-index: 20;
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            padding: 12px 24px;
            background: rgba(14, 31, 54, 0.92);
            border-bottom: 1px solid rgba(255, 255, 255, 0.12);
        }

        .preview-toolbar button {
            border: 0;
            border-radius: 999px;
            padding: 10px 16px;
            font-size: 13px;
            font-weight: 700;
            cursor: pointer;
            color: #ffffff;
            background: linear-gradient(135deg, #b58c3d, #f0d48a);
        }

        .preview-toolbar .secondary {
            background: rgba(255, 255, 255, 0.12);
        }

        .shell {
            min-height: 100vh;
            padding: 18px;
            display: flex;
            align-items: center;
            justify-content: center;
            background:
                radial-gradient(circle at top left, rgba(212, 189, 132, 0.16), transparent 28%),
                radial-gradient(circle at bottom right, rgba(31, 47, 68, 0.08), transparent 22%),
                #f6f2ea;
        }

        .certificate {
            width: 277mm;
            height: 190mm;
            position: relative;
            overflow: hidden;
            background: #fcfbf8;
            border: 1.8mm solid #d9c48c;
            box-shadow: 0 18px 55px rgba(34, 44, 60, 0.15);
        }

        .frame {
            position: absolute;
            inset: 5mm;
            border: 0.6mm solid rgba(181, 140, 61, 0.55);
            pointer-events: none;
        }

        .top-band {
            height: 24mm;
            background: #1f3146;
        }

        .ornament {
            position: absolute;
            top: 50%;
            width: 26mm;
            height: 94mm;
            transform: translateY(-50%);
            opacity: 0.18;
            color: #6f7f8f;
            font-family: DejaVu Serif, serif;
            font-size: 56px;
            line-height: 0.9;
            text-align: center;
        }

        .ornament.left { left: 4mm; }
        .ornament.right { right: 4mm; }

        .ornament span {
            display: block;
        }

        .content {
            position: relative;
            z-index: 1;
            height: 100%;
            padding: 0 18mm 9mm;
            text-align: center;
            display: flex;
            flex-direction: column;
        }

        .seal {
            width: 22mm;
            height: 22mm;
            margin: -10mm auto 4mm;
            position: relative;
            border-radius: 50%;
            background:
                radial-gradient(circle at 32% 30%, #fff6c7 0, #f2d57f 28%, #c79f42 65%, #966d24 100%);
            border: 1.2mm solid rgba(160, 119, 35, 0.82);
            box-shadow: 0 8px 18px rgba(135, 102, 34, 0.28);
        }

        .seal::before,
        .seal::after {
            content: "";
            position: absolute;
            bottom: -10mm;
            width: 0;
            height: 0;
            border-left: 7mm solid transparent;
            border-right: 7mm solid transparent;
            border-top: 16mm solid #e7cd79;
        }

        .seal::before { left: 1mm; transform: rotate(10deg); }
        .seal::after { right: 1mm; transform: rotate(-10deg); }

        .seal-core {
            position: absolute;
            inset: 3.5mm;
            border-radius: 50%;
            background:
                radial-gradient(circle at 35% 35%, #fffbe0 0, #f2da8e 24%, #d6ac54 62%, #9a6e1e 100%);
            border: 0.7mm solid rgba(255, 248, 214, 0.75);
        }

        .title {
            margin: 0;
            font-family: DejaVu Serif, serif;
            font-size: 22px;
            letter-spacing: 0.28em;
            font-weight: 400;
            color: #223449;
            text-transform: uppercase;
        }

        .eyebrow {
            margin: 4mm 0 2mm;
            font-size: 9px;
            letter-spacing: 0.28em;
            text-transform: uppercase;
            color: #32475e;
        }

        .recipient {
            margin: 3mm 0 3mm;
            font-family: DejaVu Serif, serif;
            font-size: 28px;
            font-style: italic;
            font-weight: 400;
            color: #1f3146;
            line-height: 1.15;
        }

        .summary {
            max-width: 178mm;
            margin: 0 auto 4mm;
            font-size: 10.5px;
            line-height: 1.55;
            color: #2d3c4c;
        }

        .course-name {
            max-width: 174mm;
            margin: 0 auto 4mm;
            font-family: DejaVu Serif, serif;
            font-size: 14px;
            line-height: 1.35;
            font-weight: 700;
            color: #6d5122;
        }

        .meta-line {
            display: flex;
            justify-content: center;
            gap: 14px;
            flex-wrap: wrap;
            margin-bottom: 4mm;
            font-size: 8px;
            letter-spacing: 0.06em;
            text-transform: uppercase;
            color: #536372;
        }

        .meta-line strong {
            color: #223449;
            font-weight: 700;
        }

        .bottom-grid {
            display: grid;
            grid-template-columns: 1fr 55mm 1fr;
            gap: 8mm;
            align-items: end;
            margin-top: auto;
        }

        .signature-block {
            text-align: center;
        }

        .signature-mark {
            min-height: 14mm;
            display: flex;
            align-items: flex-end;
            justify-content: center;
            margin-bottom: 2mm;
            font-family: DejaVu Serif, serif;
            font-size: 17px;
            font-style: italic;
            color: #141d29;
        }

        .signature-line {
            width: 70mm;
            margin: 0 auto 3mm;
            border-top: 0.35mm solid rgba(34, 52, 73, 0.35);
        }

        .signature-name {
            font-size: 9px;
            font-weight: 700;
            color: #203247;
        }

        .signature-role {
            font-size: 8px;
            color: #5e6c78;
        }

        .center-mark {
            text-align: center;
        }

        .center-logo {
            width: 18mm;
            height: 18mm;
            margin: 0 auto 2mm;
            position: relative;
        }

        .center-logo::before,
        .center-logo::after {
            content: "";
            position: absolute;
            inset: 0;
            clip-path: polygon(50% 0, 0 100%, 100% 100%);
            border: 1.8mm solid #c7a24f;
            background: transparent;
        }

        .center-logo::after {
            inset: 5mm 5mm 3mm;
            border-width: 1.2mm;
        }

        .center-label {
            font-family: DejaVu Serif, serif;
            font-size: 9px;
            letter-spacing: 0.12em;
            text-transform: uppercase;
            color: #8f6e2f;
        }

        .verification {
            margin-top: 4mm;
            display: flex;
            justify-content: space-between;
            gap: 6mm;
            align-items: center;
            font-size: 7px;
            color: #697784;
        }

        .verification-copy {
            text-align: left;
            max-width: 150mm;
            line-height: 1.5;
        }

        .verification-copy strong {
            color: #203247;
        }

        .verification-copy a {
            color: #6d5122;
            text-decoration: none;
            word-break: break-all;
        }

        .qr-panel {
            width: 22mm;
            text-align: center;
        }

        .qr-box {
            width: 18mm;
            height: 18mm;
            margin: 0 auto 2mm;
            padding: 1.5mm;
            background: #fff;
            border: 0.4mm solid rgba(34, 52, 73, 0.16);
        }

        .qr-box img {
            width: 100%;
            height: 100%;
            object-fit: contain;
        }

        .qr-caption {
            font-size: 6.5px;
            color: #6f7b86;
            line-height: 1.35;
        }

        @media print {
            html, body {
                background: #ffffff;
                width: 297mm;
                height: 210mm;
            }

            .preview-toolbar {
                display: none;
            }

            .shell {
                min-height: auto;
                padding: 0;
                background: #ffffff;
            }

            .certificate {
                width: 277mm;
                height: 190mm;
                box-shadow: none;
                page-break-inside: avoid;
                break-inside: avoid;
                overflow: hidden;
            }

            .content {
                padding: 0 18mm 8mm;
            }
        }

        @media (max-width: 1100px) {
            .certificate {
                width: 100%;
                min-height: auto;
            }

            .bottom-grid {
                grid-template-columns: 1fr;
                gap: 7mm;
            }

            .verification {
                flex-direction: column;
                align-items: flex-start;
            }
        }
    </style>
</head>
<body>
    @if($isPreview && empty($isEmbedded))
        <div class="preview-toolbar">
            <button class="secondary" type="button" onclick="window.location.href='{{ $certificate->download_url ?: url('/api/certificates/'.$certificate->id.'/download') }}'">
                Descargar PDF
            </button>
            <button type="button" onclick="window.print()">
                Imprimir OnePage
            </button>
        </div>
    @endif

    <div class="shell">
        <div class="certificate">
            <div class="frame"></div>
            <div class="top-band"></div>

            <div class="ornament left">
                <span>§</span>
                <span>❦</span>
                <span>§</span>
            </div>
            <div class="ornament right">
                <span>§</span>
                <span>❦</span>
                <span>§</span>
            </div>

            <div class="content">
                <div class="seal"><div class="seal-core"></div></div>

                <h1 class="title">Certificado</h1>
                <div class="eyebrow">Otorgado a</div>

                <div class="recipient">{{ $certificate->student_name ?: ($certificate->user->name ?? 'Estudiante') }}</div>

                <p class="summary">
                    En reconocimiento a la dedicacion, constancia y logro academico demostrado al completar satisfactoriamente el recorrido formativo definido dentro de esta plataforma educativa.
                </p>

                <div class="course-name">
                    {{ $certificate->course_name ?: ($certificate->course->title ?? 'Curso') }}
                </div>

                <div class="meta-line">
                    <span>Emitido el <strong>{{ $issuedDate ?: '-' }}</strong></span>
                    <span>Codigo <strong>{{ $certificate->certificate_code }}</strong></span>
                    <span>Puntaje final <strong>{{ number_format((float) $certificate->final_score, 2) }}%</strong></span>
                </div>

                <div class="bottom-grid">
                    <div class="signature-block">
                        <div class="signature-mark">{{ $certificate->course->instructor->name ?? ($certificate->metadata['instructor'] ?? 'Instructor') }}</div>
                        <div class="signature-line"></div>
                        <div class="signature-name">{{ $certificate->course->instructor->name ?? ($certificate->metadata['instructor'] ?? 'Instructor') }}</div>
                        <div class="signature-role">Instructor responsable</div>
                    </div>

                    <div class="center-mark">
                        <div class="center-logo"></div>
                        <div class="center-label">LMS Creator</div>
                    </div>

                    <div class="signature-block">
                        <div class="signature-mark">Plataforma</div>
                        <div class="signature-line"></div>
                        <div class="signature-name">Validacion academica</div>
                        <div class="signature-role">Emision digital verificada</div>
                    </div>
                </div>

                <div class="verification">
                    <div class="verification-copy">
                        <strong>Validacion publica:</strong>
                        <a href="{{ $verificationUrl }}" target="_blank" rel="noopener noreferrer">{{ $verificationUrl }}</a>
                    </div>

                    <div class="qr-panel">
                        <div class="qr-box">
                            <img src="{{ $qrCodeBase64 }}" alt="QR de validacion">
                        </div>
                        <div class="qr-caption">Escanea para verificar</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    @if($autoPrint)
        <script>
            window.addEventListener('load', () => {
                window.print()
            })
        </script>
    @endif
</body>
</html>
