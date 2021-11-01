{{--$order collection is available here--}}

@component('mail::message')
# Pedido efetuado com sucesso!

Seu pedido foi feito com sucesso. ID de rastreamento de pedido {{$order->tracking_number}}

@component('mail::button', ['url' => $shopUrl.'order-received/'.$order->tracking_number ])
Ver pedido
@endcomponent

Obrigado,<br>
{{ config('app.name') }}
@endcomponent 