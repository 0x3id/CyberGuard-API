@props([
    'url',
    'color' => 'primary',
    'align' => 'center',
])
@php
    $imgBase = rtrim(config('app.frontend_url', env('FRONTEND_URL', url('/'))), '/') . '/images/cyberguard';
    $bgStart = '#3b82f6';
    $bgEnd   = '#2563eb';
    $bgSolid = '#2563eb';
    if ($color === 'error') {
        $bgStart = '#ef4444';
        $bgEnd   = '#dc2626';
        $bgSolid = '#dc2626';
    } elseif ($color === 'success') {
        $bgStart = '#22c55e';
        $bgEnd   = '#16a34a';
        $bgSolid = '#16a34a';
    }
@endphp
<table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="margin-bottom:16px;">
    <tr>
        <td align="{{ $align }}" style="padding:0;">
            {{-- VML button for Outlook --}}
            <!--[if mso]>
            <v:roundrect xmlns:v="urn:schemas-microsoft-com:vml" xmlns:w="urn:schemas-microsoft-com:office:word" href="{{ $url }}" style="height:48px;v-text-anchor:middle;width:100%;" arcsize="25%" strokecolor="{{ $bgSolid }}" fillcolor="{{ $bgStart }}">
                <w:anchorlock/>
                <center style="color:#000000;font-family:'Inter',Arial,sans-serif;font-size:14px;font-weight:700;letter-spacing:0.1em;text-transform:uppercase;">
                    {{ $slot }}
                </center>
            </v:roundrect>
            <![endif]-->
            {{-- CSS button for all other clients --}}
            <!--[if !mso]><!-->
            <a href="{{ $url }}" target="_blank" rel="noopener" style="display:block;width:100%;max-width:100%;background-color:{{ $bgSolid }};background-image:linear-gradient(135deg,{{ $bgStart }} 0%,{{ $bgEnd }} 100%);color:#000000;font-family:'Inter',Helvetica,Arial,sans-serif;font-size:14px;font-weight:700;letter-spacing:0.1em;text-transform:uppercase;text-decoration:none;padding:13px 32px;border-radius:12px;text-align:center;box-sizing:border-box;">
                <img src="{{ $imgBase }}/lock-icon.png" width="18" height="18" alt="" style="vertical-align:middle;margin-right:8px;border:0;outline:none;display:inline-block;" />
                {{ $slot }}
            </a>
            <!--<![endif]-->
        </td>
    </tr>
</table>
