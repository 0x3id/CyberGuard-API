@php
    $frontendUrl = rtrim(config('app.frontend_url', env('FRONTEND_URL', url('/'))), '/');
@endphp
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" xmlns:v="urn:schemas-microsoft-com:vml" xmlns:o="urn:schemas-microsoft-com:office:office" lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="UTF-8" />
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <meta name="color-scheme" content="dark" />
    <meta name="supported-color-schemes" content="dark" />
    <title>{{ $title ?? config('app.name') }}</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet" />
    {!! $head ?? '' !!}
    <!--[if mso]>
    <noscript>
    <xml>
        <o:OfficeDocumentSettings>
            <o:PixelsPerInch>96</o:PixelsPerInch>
        </o:OfficeDocumentSettings>
    </xml>
    </noscript>
    <style type="text/css">
        body, table, td, p, a, li, blockquote { font-family: 'Inter', Arial, sans-serif !important; }
    </style>
    <![endif]-->
    <style type="text/css">
        @media only screen and (max-width: 600px) {
            .email-wrapper { width: 100% !important; max-width: 100% !important; }
            .card-body { padding: 28px 24px 24px !important; }
            .card-header { padding: 28px 24px 32px !important; }
            .card-footer { padding: 18px 24px !important; }
            .back-link { padding: 8px 16px !important; font-size: 13px !important; }
        }
    </style>
</head>
<body style="margin:0;padding:0;background-color:#09090b;font-family:'Inter',Helvetica,Arial,sans-serif;-webkit-font-smoothing:antialiased;">

<table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="background-color:#09090b;">
    <tr>
        <td align="center" style="padding:24px 16px;">

            <!--[if mso]>
            <table role="presentation" width="440" cellspacing="0" cellpadding="0" border="0" align="center">
            <tr><td>
            <![endif]-->

            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" class="email-wrapper" style="max-width:440px;width:100%;background-color:#121214;border:1px solid #1f1f23;border-radius:24px;">
                <tr>
                    <td style="border-radius:24px;overflow:hidden;">

                        {!! $header ?? '' !!}

                        <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0">
                            <tr>
                                <td class="card-body" style="padding:24px 32px 20px;background-color:#121214;font-family:'Inter',Helvetica,Arial,sans-serif;">
                                    {!! $slot !!}
                                    {!! $subcopy ?? '' !!}
                                </td>
                            </tr>
                        </table>

                        {!! $footer ?? '' !!}

                    </td>
                </tr>
            </table>

            <!--[if mso]>
            </td></tr>
            </table>
            <![endif]-->

            {{-- Back link --}}
            @if (isset($backlinkUrl) || ! empty($slot))
                <table role="presentation" cellspacing="0" cellpadding="0" border="0" style="margin:20px auto 0;">
                    <tr>
                        <td align="center">
                            <a href="{{ $backlinkUrl ?? $frontendUrl }}" class="back-link" style="color:#a1a1aa;font-size:14px;text-decoration:none;font-family:'Inter',Helvetica,Arial,sans-serif;font-weight:500;padding:10px 20px;border-radius:9999px;background:rgba(255,255,255,0.02);border:1px solid #1f1f23;display:inline-block;">
                                {{ $backlinkText ?? 'Back to CyberGuard Panel' }}
                            </a>
                        </td>
                    </tr>
                </table>
            @endif

        </td>
    </tr>
</table>

</body>
</html>
