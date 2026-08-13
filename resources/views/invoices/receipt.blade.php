<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Receipt #{{ $invoice->id }}</title>
    <style>
        body {
            font-family: 'Courier New', Courier, monospace;
            font-size: 14px;
            color: #000;
            background-color: #fff;
            margin: 0;
            padding: 20px;
            width: 80mm; /* Standard thermal printer width */
        }
        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .font-bold { font-weight: bold; }
        .border-top { border-top: 1px dashed #000; margin-top: 10px; padding-top: 10px; }
        .border-bottom { border-bottom: 1px dashed #000; margin-bottom: 10px; padding-bottom: 10px; }
        .flex { display: flex; }
        .justify-between { justify-content: space-between; }
        .w-full { width: 100%; }
        table { width: 100%; border-collapse: collapse; }
        th, td { text-align: left; padding: 2px 0; }
        .item-row td { vertical-align: top; }
        .quantity { width: 15%; }
        .name { width: 55%; }
        .price { width: 30%; text-align: right; }
        .modifiers { font-size: 12px; margin-left: 10px; }
        
        @media print {
            body { padding: 0; margin: 0; }
            @page { margin: 0; }
        }
    </style>
</head>
<body onload="window.print();">

    <div class="text-center">
        @if($invoice->branch->company->logoUrl())
            <img src="{{ $invoice->branch->company->logoUrl() }}" alt="" style="max-width: 120px; max-height: 60px; margin: 0 auto 8px;">
        @endif
        <div class="font-bold" style="font-size: 18px;">{{ $invoice->branch->company->name }}</div>
        <div>{{ $invoice->branch->name }}</div>
        @if($invoice->branch->phone)
            <div>Tel: {{ $invoice->branch->phone }}</div>
        @endif
        @if($invoice->branch->address)
            <div style="font-size: 12px; margin-top: 5px;">{{ $invoice->branch->address }}</div>
        @endif
    </div>

    <div class="border-top border-bottom" style="font-size: 12px;">
        <div class="flex justify-between">
            <span>Date: {{ $invoice->created_at->format('Y-m-d H:i') }}</span>
            <span>Order #{{ str_pad($invoice->id, 5, '0', STR_PAD_LEFT) }}</span>
        </div>
        <div class="flex justify-between">
            <span>Cashier: {{ $invoice->user->name ?? 'System' }}</span>
            @if($invoice->table)
                <span>Table: {{ $invoice->table->name }}</span>
            @endif
        </div>
    </div>

    <table class="w-full">
        <thead>
            <tr class="border-bottom">
                <th class="quantity">Qty</th>
                <th class="name">Item</th>
                <th class="price">Amount</th>
            </tr>
        </thead>
        <tbody>
            @foreach($invoice->orderItems as $item)
                <tr class="item-row">
                    <td class="quantity">{{ $item->quantity }}</td>
                    <td class="name">
                        {{ $item->product->name ?? 'Unknown Item' }}
                        @if($item->productVariant)
                            <br> <small>{{ $item->productVariant->name }}</small>
                        @endif
                        @if($item->modifiers->isNotEmpty())
                            <div class="modifiers">
                                @foreach($item->modifiers as $mod)
                                    - {{ $mod->name }}<br>
                                @endforeach
                            </div>
                        @endif
                    </td>
                    <td class="price">${{ number_format($item->subtotal, 2) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="border-top">
        <div class="flex justify-between">
            <span>Subtotal:</span>
            <span>${{ number_format($invoice->subtotal, 2) }}</span>
        </div>
        @if($invoice->discount_total > 0)
            <div class="flex justify-between">
                <span>Discount:</span>
                <span>-${{ number_format($invoice->discount_total, 2) }}</span>
            </div>
        @endif
        @if($invoice->tax_total > 0)
            <div class="flex justify-between">
                <span>Tax:</span>
                <span>${{ number_format($invoice->tax_total, 2) }}</span>
            </div>
        @endif
        <div class="flex justify-between font-bold border-top" style="font-size: 16px;">
            <span>TOTAL:</span>
            <span>${{ number_format($invoice->total, 2) }}</span>
        </div>
    </div>

    @if($invoice->payments->isNotEmpty())
        <div class="border-top" style="font-size: 12px;">
            <div class="font-bold mb-1">Payments</div>
            @foreach($invoice->payments as $payment)
                <div class="flex justify-between">
                    <span style="text-transform: capitalize;">{{ $payment->method }}</span>
                    <span>${{ number_format($payment->amount, 2) }} ({{ $payment->status }})</span>
                </div>
            @endforeach
        </div>
    @endif

    <div class="border-top text-center" style="font-size: 12px; margin-top: 20px;">
        <div>Thank you for your visit!</div>
        <div>Please come again.</div>
    </div>

</body>
</html>
