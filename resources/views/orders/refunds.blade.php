@if(auth()->user()->hasPermission('payments.refund') && $order->payments->isNotEmpty())
<section class="panel"><div class="panel-heading"><div><h2>Refunds & corrections</h2><p>Refunds are recorded as separate entries. Original payments are preserved.</p></div></div><div class="panel-body stack-form"><p class="field-hint">Return the funds outside this application first. Each payment can be refunded in full once. For a cancellation, refund every payment, then cancel the order. Partial payment refunds are not supported.</p>
@foreach($order->payments as $p)
<div><strong class="section-copy">Payment #{{ $p->id }} · {{ $business->money($p->amount_cents) }} · {{ ucwords(str_replace('_',' ',$p->method)) }}</strong>
@if($p->refund)<p class="field-hint">Refunded {{ $p->refund->created_at->format('M j, Y · g:i A') }} by {{ $p->refund->user->name }} · {{ $p->refund->reason }}</p>
@else<form class="stack-form" method="post" action="{{ route('orders.refund',[$order,$p]) }}" data-confirm="Confirm you have returned this payment to the customer. Record the full refund?">@csrf<label>Refund reason<input name="reason" minlength="5" maxlength="255" required placeholder="Reason for returning this payment"></label><button class="btn danger small">Record full refund · {{ $business->money($p->amount_cents) }}</button></form>@endif
</div>
@endforeach</div></section>
@endif
