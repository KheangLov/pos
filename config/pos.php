<?php

return [
    // Reorder/low-stock alerting threshold: when on-hand stock drops to this
    // level or below, checkout fires StockLow alerts and the products table
    // highlights the row (P2: was a hardcoded const on StockTransaction).
    'low_stock_threshold' => (int) env('LOW_STOCK_THRESHOLD', 10),
];
