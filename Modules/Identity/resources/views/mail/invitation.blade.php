<x-mail::message>
# {{ __('Hello :name!', ['name' => $name]) }}

{{ __('An account was created for you at :app. Use the button below to choose your password and sign in for the first time.', ['app' => $appName]) }}

<x-mail::button :url="$url">
{{ __('Set my password') }}
</x-mail::button>

<x-mail::subcopy>
{{ __('This invitation link expires in :count minutes.', ['count' => $expires]) }} {{ __('If you were not expecting this invitation, you can safely ignore this email.') }}

{{ __('If you are having trouble clicking the button, copy and paste the URL below into your browser:') }} [{{ $url }}]({{ $url }})
</x-mail::subcopy>
</x-mail::message>
