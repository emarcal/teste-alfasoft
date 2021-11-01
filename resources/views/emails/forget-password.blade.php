@component('mail::message')
# Token de redefinição de senha

Copie o token abaixo para redefinir sua senha.

```{{$token}}```

Obrigado,<br>
{{ config('app.name') }}
@endcomponent 