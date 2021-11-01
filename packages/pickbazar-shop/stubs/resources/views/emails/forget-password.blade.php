@component('mail::message')
# Password Reset Token

Copie o token abaixo para redefinir sua passe.

```{{$token}}```

Obrigado,<br>
{{ config('app.name') }}
@endcomponent