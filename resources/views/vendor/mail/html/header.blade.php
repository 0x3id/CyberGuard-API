@php
    $imgBase = rtrim(config('app.frontend_url', env('FRONTEND_URL', url('/'))), '/') . '/images/cyberguard';
@endphp
<table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" class="card-header" style="background:linear-gradient(135deg,#1f2937 0%,rgba(59,130,246,0.15) 100%);border-bottom:1px solid #1e293b;padding:0;">
    <tr>
        <td style="padding:20px 24px 24px;text-align:center;">

            {{-- Shield icon + Brand name --}}
            <table role="presentation" cellspacing="0" cellpadding="0" border="0" style="margin:0 auto;">
                <tr>
                    <td valign="middle" style="padding-right:10px;">
                        <img src="{{ $imgBase }}/shield-logo.png" width="38" height="38" alt="CyberGuard" style="display:block;border:0;outline:none;" />
                    </td>
                    <td valign="middle">
                        <table role="presentation" cellspacing="0" cellpadding="0" border="0">
                            <tr>
                                <td style="font-size:20px;font-weight:800;letter-spacing:-0.5px;line-height:1.1;font-family:'Inter',Helvetica,Arial,sans-serif;">
                                    <span style="color:#1d4ed8;">Cyber</span><span style="color:#ffffff;">Guard</span>
                                </td>
                            </tr>
                            <tr>
                                <td style="font-size:9px;font-weight:600;letter-spacing:0.18em;color:#64748b;text-transform:uppercase;padding-top:1px;font-family:'Inter',Helvetica,Arial,sans-serif;">
                                    Security Protocol Active
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>
            </table>

            {{-- Envelope icon circle --}}
            <table role="presentation" cellspacing="0" cellpadding="0" border="0" style="margin:16px auto 0;">
                <tr>
                    <td align="center" width="56" height="56" style="width:56px;height:56px;border-radius:50%;background:rgba(59,130,246,0.15);border:2px solid rgba(59,130,246,0.4);">
                        <img src="{{ $imgBase }}/envelope-icon.png" width="26" height="26" alt="Email" style="display:block;border:0;outline:none;margin:0 auto;" />
                    </td>
                </tr>
            </table>

        </td>
    </tr>
</table>
