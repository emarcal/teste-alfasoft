@component('mail::message')
# {{$details['subject']}}

Email: {{$details['email']}}

{{$details['description']}}

Obrigado,<br>
{{ $details['name'] }}
@endcomponent 