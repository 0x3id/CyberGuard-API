@component('mail::message')
{{-- Greeting with user name --}}
@if (! empty($userName) || ! empty($greeting))
<table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="margin:0 0 16px;">
    <tr>
        <td style="font-size:22px;font-weight:800;color:#ffffff;font-family:'Inter',Helvetica,Arial,sans-serif;letter-spacing:-0.3px;line-height:1.3;">
            Hello, {{ $userName ?? 'User' }}
        </td>
    </tr>
</table>
@if (! empty($greeting))
<table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="margin:0 0 20px;">
    <tr>
        <td style="font-size:14px;font-weight:600;color:#3b82f6;font-family:'Inter',Helvetica,Arial,sans-serif;letter-spacing:0.02em;line-height:1.4;">
            {{ $greeting }}
        </td>
    </tr>
</table>
@endif
@endif

{{-- Main body text (intro lines) --}}
@foreach ($introLines as $line)
<table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="margin:0 0 10px;">
    <tr>
        <td style="font-size:15px;line-height:26px;color:#a1a1aa;font-family:'Inter',Helvetica,Arial,sans-serif;">
            {!! $line !!}
        </td>
    </tr>
</table>
@endforeach

{{-- Horizontal divider --}}
<table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="margin:20px 0 16px;">
    <tr>
        <td style="border-bottom:1px solid #1f1f23;line-height:1px;font-size:1px;height:1px;">&nbsp;</td>
    </tr>
</table>

{{-- CTA Button --}}
@isset($actionText)
<?php
    $color = $level === 'error' ? '#dc2626' : ($level === 'success' ? '#10b981' : '#2563eb');
?>
<table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="margin:0 0 16px;">
    <tr>
        <td align="center">
            <table role="presentation" cellspacing="0" cellpadding="0" border="0">
                <tr>
                    <td align="center" height="48" style="height:48px;border-radius:8px;background:{{ $color }};">
                        <!--[if mso]>
                        <v:roundrect xmlns:v="urn:schemas-microsoft-com:vml" xmlns:w="urn:schemas-microsoft-com:office:word" href="{{ $actionUrl }}" style="height:48px;v-text-anchor:middle;width:auto;" arcsize="17%" strokecolor="{{ $color }}" fillcolor="{{ $color }}">
                            <w:anchorlock/>
                            <center>
                        <![endif]-->
                        <a href="{{ $actionUrl }}" target="_blank" style="display:inline-block;padding:12px 32px;font-size:15px;font-weight:700;font-family:'Inter',Helvetica,Arial,sans-serif;color:#ffffff;text-decoration:none;border-radius:8px;letter-spacing:0.3px;line-height:24px;">
                            {{ $actionText }}
                        </a>
                        <!--[if mso]>
                            </center>
                        </v:roundrect>
                        <![endif]-->
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>
@endisset

{{-- Expiry text (below CTA) --}}
@isset($expiryText)
<table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="margin:0 0 8px;">
    <tr>
        <td style="font-size:13px;line-height:20px;color:#9ca3af;font-family:'Inter',Helvetica,Arial,sans-serif;text-align:center;">
            {!! $expiryText !!}
        </td>
    </tr>
</table>
@endisset

{{-- Outro lines --}}
@foreach ($outroLines as $line)
<table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="margin:0 0 10px;">
    <tr>
        <td style="font-size:15px;line-height:26px;color:#a1a1aa;font-family:'Inter',Helvetica,Arial,sans-serif;">
            {!! $line !!}
        </td>
    </tr>
</table>
@endforeach

{{-- Security Badges --}}
<table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="margin:28px 0 0;padding:18px 20px;background:rgba(16,185,129,0.06);border-radius:8px;border:1px solid rgba(16,185,129,0.15);">
    <tr>
        <td style="font-size:12px;font-weight:700;color:#10b981;font-family:'Inter',Helvetica,Arial,sans-serif;letter-spacing:0.05em;text-transform:uppercase;padding-bottom:14px;">
            &mdash; Security Verified &mdash;
        </td>
    </tr>
    <tr>
        <td style="padding-bottom:8px;">
            <table role="presentation" cellspacing="0" cellpadding="0" border="0">
                <tr>
                    <td valign="middle" style="font-size:14px;color:#a1a1aa;font-family:'Inter',Helvetica,Arial,sans-serif;line-height:20px;padding-left:4px;">
                        &bull; End-to-end encrypted
                    </td>
                </tr>
            </table>
        </td>
    </tr>
    <tr>
        <td style="padding-bottom:8px;">
            <table role="presentation" cellspacing="0" cellpadding="0" border="0">
                <tr>
                    <td valign="middle" style="font-size:14px;color:#a1a1aa;font-family:'Inter',Helvetica,Arial,sans-serif;line-height:20px;padding-left:4px;">
                        &bull; Verified sender identity
                    </td>
                </tr>
            </table>
        </td>
    </tr>
    <tr>
        <td>
            <table role="presentation" cellspacing="0" cellpadding="0" border="0">
                <tr>
                    <td valign="middle" style="font-size:14px;color:#a1a1aa;font-family:'Inter',Helvetica,Arial,sans-serif;line-height:20px;padding-left:4px;">
                        &bull; Protected by CyberGuard
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>

{{-- Subcopy --}}
@isset($actionText)
<table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="margin:28px 0 0;">
    <tr>
        <td style="font-size:13px;line-height:20px;color:#9ca3af;font-family:'Inter',Helvetica,Arial,sans-serif;">
            If you're having trouble clicking the "{{ $actionText }}" button, copy and paste the URL below into your web browser:
            <br />
            <span style="color:#3b82f6;word-break:break-all;">{{ $actionUrl }}</span>
        </td>
    </tr>
</table>
@endisset

{{-- Salutation --}}
@if (! empty($salutation))
<table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="margin:28px 0 0;">
    <tr>
        <td style="font-size:15px;line-height:24px;color:#a1a1aa;font-family:'Inter',Helvetica,Arial,sans-serif;">
            {{ $salutation }}
        </td>
    </tr>
</table>
@else
@endif

@endcomponent
