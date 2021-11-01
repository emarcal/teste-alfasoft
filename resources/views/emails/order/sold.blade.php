{{--$order collection is available here--}}

@component('mail::message')
# Venda realizada com sucesso!

Venda Realizada. ID de rastreamento de pedido: {{$order->tracking_number}}

@component('mail::button', ['url' => $adminUrl.'orders/details/'.$order->tracking_number ])
Consultar pedido
@endcomponent

Obrigado,<br>
{{ config('app.name') }}
@endcomponent  