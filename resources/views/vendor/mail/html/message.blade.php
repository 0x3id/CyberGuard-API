@php
    $frontendUrl = rtrim(config('app.frontend_url', env('FRONTEND_URL', url('/'))), '/');
@endphp
<x-mail::layout>
    <x-slot:header>
        <x-mail::header />
    </x-slot:header>

    {!! $slot !!}

    @isset($subcopy)
        <x-slot:subcopy>
            <x-mail::subcopy>
                {!! $subcopy !!}
            </x-mail::subcopy>
        </x-slot:subcopy>
    @endisset

    <x-slot:footer>
        <x-mail::footer>
            If you did not expect this message, you can safely ignore this email.<br />
            Need help? <a href="mailto:{{ config('mail.from.address') }}" style="color:#3b82f6;text-decoration:none;font-weight:500;">{{ config('mail.from.address') }}</a>
            &nbsp;·&nbsp;
            <a href="{{ $frontendUrl }}" style="color:#3b82f6;text-decoration:none;font-weight:500;">Back to CyberGuard</a>
        </x-mail::footer>
    </x-slot:footer>
</x-mail::layout>
