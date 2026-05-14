<x-mail::message>
# Verify Your Email Address

Please click the button below to verify your email address.

<x-mail::button :url="$verificationUrl">
Verify Email Address
</x-mail::button>

If you did not create an account, no further action is required.

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
<br>

If you're having trouble clicking the "Verify Email Address" button, copy and paste the URL below into your web browser: "$verificationUrl"