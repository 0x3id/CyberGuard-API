@php
    $imgBase = rtrim(config('app.frontend_url', env('FRONTEND_URL', url('/'))), '/') . '/images/cyberguard';
@endphp
<x-mail::message>
    {{-- Greeting --}}
    @if (! empty($greeting))
        <h1 style="font-size:21px;font-weight:800;color:#ffffff;margin:0 0 4px;letter-spacing:-0.5px;line-height:1.2;font-family:'Inter',Helvetica,Arial,sans-serif;">
            {{ $greeting }}
        </h1>
    @else
        <h1 style="font-size:21px;font-weight:800;color:#ffffff;margin:0 0 4px;letter-spacing:-0.5px;line-height:1.2;font-family:'Inter',Helvetica,Arial,sans-serif;">
            @if ($level === 'error')
                @lang('Whoops!')
            @else
                @lang('Hello!')
            @endif
        </h1>
    @endif

    {{-- Hello line with user name --}}
    @if (! empty($userName))
        <p style="font-size:15px;color:#94a3b8;margin:0 0 12px;font-weight:400;font-family:'Inter',Helvetica,Arial,sans-serif;">
            @lang('Hello,') <span style="color:#3b82f6;font-weight:600;">{{ $userName }}</span>
        </p>
    @endif

    {{-- Intro Lines --}}
    @if (count($introLines) > 0)
        <div style="font-size:14px;color:#94a3b8;line-height:1.6;margin-bottom:20px;font-family:'Inter',Helvetica,Arial,sans-serif;">
            @foreach ($introLines as $line)
                <p style="margin:0 0 12px;font-size:14px;color:#94a3b8;line-height:1.6;font-family:'Inter',Helvetica,Arial,sans-serif;">{!! $line !!}</p>
            @endforeach
        </div>
    @endif

    {{-- Divider --}}
    @isset($actionText)
        <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="margin:0 0 20px;">
            <tr>
                <td style="height:1px;background:linear-gradient(90deg,transparent,rgba(148,163,184,0.25),transparent);font-size:0;line-height:0;">&nbsp;</td>
            </tr>
        </table>

        {{-- Action Button --}}
        <x-mail::button :url="$actionUrl" :color="$level ?? 'primary'">
            {{ $actionText }}
        </x-mail::button>
    @endisset

    {{-- Expiry notice --}}
    @if (! empty($expiryText))
        <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0">
            <tr>
                <td style="text-align:center;padding:0 0 16px;">
                    <p style="margin:0;font-size:12px;color:#64748b;font-style:italic;font-weight:300;font-family:'Inter',Helvetica,Arial,sans-serif;">
                        {!! $expiryText !!}
                    </p>
                </td>
            </tr>
        </table>
    @endif

    {{-- Outro Lines --}}
    @if (count($outroLines) > 0)
        <div style="font-size:14px;color:#94a3b8;line-height:1.6;margin-bottom:20px;font-family:'Inter',Helvetica,Arial,sans-serif;">
            @foreach ($outroLines as $line)
                <p style="margin:0 0 12px;font-size:14px;color:#94a3b8;line-height:1.6;font-family:'Inter',Helvetica,Arial,sans-serif;">{!! $line !!}</p>
            @endforeach
        </div>
    @endif

    {{-- Security badges --}}
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0">
        <tr>
            <td style="text-align:center;padding:4px 0 0;">
                <span class="badge-inline" style="display:inline-block;font-size:10px;font-weight:600;color:#64748b;text-transform:uppercase;letter-spacing:0.05em;margin:0 8px 4px;font-family:'Inter',Helvetica,Arial,sans-serif;">
                    <img src="{{ $imgBase }}/shield-check.png" width="11" height="11" alt="" style="vertical-align:middle;margin-right:3px;border:0;outline:none;display:inline-block;" />
                    @lang('End-to-End Encrypted')
                </span>
                <span class="badge-inline" style="display:inline-block;font-size:10px;font-weight:600;color:#64748b;text-transform:uppercase;letter-spacing:0.05em;margin:0 8px 4px;font-family:'Inter',Helvetica,Arial,sans-serif;">
                    <img src="{{ $imgBase }}/check-icon.png" width="11" height="11" alt="" style="vertical-align:middle;margin-right:3px;border:0;outline:none;display:inline-block;" />
                    @lang('2-Factor Protected')
                </span>
                <span class="badge-inline" style="display:inline-block;font-size:10px;font-weight:600;color:#64748b;text-transform:uppercase;letter-spacing:0.05em;margin:0 8px 4px;font-family:'Inter',Helvetica,Arial,sans-serif;">
                    <img src="{{ $imgBase }}/verified-icon.png" width="11" height="11" alt="" style="vertical-align:middle;margin-right:3px;border:0;outline:none;display:inline-block;" />
                    @lang('Verified Secure')
                </span>
            </td>
        </tr>
    </table>

    {{-- Subcopy / Fallback Link --}}
    @isset($actionText)
        <x-slot:subcopy>
            @lang(
                "If you're having trouble clicking the \":actionText\" button, copy and paste the URL below into your browser:",
                ['actionText' => $actionText]
            )
            <br />
            <span style="font-size:12px;color:#1d4ed8;word-break:break-all;overflow-wrap:break-word;font-family:Consolas,Monaco,'Courier New',monospace;background:rgba(59,130,246,0.08);padding:4px 8px;border-radius:6px;display:inline-block;margin-top:4px;max-width:100%;">
                {{ $actionUrl }}
            </span>
        </x-slot:subcopy>
    @endisset
</x-mail::message>
