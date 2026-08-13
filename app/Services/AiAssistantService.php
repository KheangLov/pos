<?php

namespace App\Services;

use Anthropic\Client;
use Anthropic\Core\Exceptions\APIStatusException;
use Anthropic\Lib\Tools\BetaRunnableTool;
use App\Models\Company;
use App\Models\Product;
use App\Models\StockTransaction;
use Illuminate\Support\Facades\DB;

/**
 * A read-only Q&A assistant over a single company's own POS data. Every tool
 * closure captures $company directly rather than reading it from a model
 * argument, so there is no company_id the model could be prompted into
 * substituting - the tenant boundary isn't something Claude can see or move.
 */
class AiAssistantService
{
    /**
     * @param  array<int, array{role: string, content: mixed}>  $history  Prior
     *                                                                    completed turns (plain question/answer text only - no tool_use
     *                                                                    internals are carried across turns, so every question re-fetches
     *                                                                    fresh data rather than reusing a stale earlier answer).
     * @return array{answer?: string, history?: array, error?: string}
     */
    public function ask(Company $company, string $question, array $history = []): array
    {
        if (blank($company->anthropic_api_key)) {
            return ['error' => 'No Anthropic API key is configured for this company yet.'];
        }

        $client = new Client(apiKey: $company->anthropic_api_key);

        $messages = [...$history, ['role' => 'user', 'content' => $question]];

        try {
            $runner = $client->beta->messages->toolRunner(
                maxTokens: 2048,
                messages: $messages,
                model: 'claude-opus-5',
                tools: $this->tools($company),
                maxIterations: 10,
                extraParams: ['system' => $this->systemPrompt($company)],
            );

            $final = $runner->runUntilDone();
        } catch (APIStatusException $e) {
            return ['error' => $this->friendlyError($e)];
        } catch (\Throwable $e) {
            return ['error' => 'Something went wrong talking to the AI service: '.$e->getMessage()];
        }

        $answer = $this->extractText($final);

        return [
            'answer' => $answer,
            'history' => [...$messages, ['role' => 'assistant', 'content' => $answer]],
        ];
    }

    private function systemPrompt(Company $company): string
    {
        return "You are the AI assistant inside {$company->name}'s point-of-sale admin panel. "
            .'Staff ask you questions about their own sales, stock, and orders; answer using the tools '
            .'available rather than guessing at numbers. All figures are in USD unless the data says '
            .'otherwise. Keep answers short and concrete: lead with the number or fact being asked for, '
            .'then a sentence of context if it helps. You only have read access to this business\'s own '
            .'data - you cannot change prices, stock, or orders.';
    }

    private function extractText(mixed $message): string
    {
        if (! $message) {
            return "Sorry, I couldn't get a response that time.";
        }

        $text = '';
        foreach ($message->content as $block) {
            if ($block->type === 'text') {
                $text .= $block->text;
            }
        }

        return $text !== '' ? $text : "I wasn't able to find an answer to that.";
    }

    private function friendlyError(APIStatusException $e): string
    {
        return match ($e->type?->value ?? null) {
            'authentication_error' => 'That Anthropic API key looks invalid - check it in Company settings.',
            'rate_limit_error' => 'Too many requests right now - try again in a moment.',
            'overloaded_error' => "Anthropic's service is temporarily overloaded - try again shortly.",
            default => 'Something went wrong talking to the AI service: '.$e->getMessage(),
        };
    }

    /**
     * @return list<BetaRunnableTool>
     */
    private function tools(Company $company): array
    {
        return [
            new BetaRunnableTool(
                definition: [
                    'name' => 'get_sales_summary',
                    'description' => 'Total revenue and order count for this business over a date range.',
                    'inputSchema' => [
                        'type' => 'object',
                        'properties' => [
                            'start_date' => ['type' => 'string', 'description' => 'YYYY-MM-DD, inclusive. Defaults to today.'],
                            'end_date' => ['type' => 'string', 'description' => 'YYYY-MM-DD, inclusive. Defaults to start_date.'],
                        ],
                        'required' => [],
                    ],
                ],
                run: function (array $input) use ($company): string {
                    $start = $input['start_date'] ?? now()->toDateString();
                    $end = $input['end_date'] ?? $start;

                    $row = DB::table('invoice_daily_summary')
                        ->whereIn('branch_id', $company->branches()->pluck('id'))
                        ->whereBetween('sale_date', [$start, $end])
                        ->selectRaw('COALESCE(SUM(revenue), 0) as revenue, COALESCE(SUM(order_count), 0) as orders')
                        ->first();

                    return json_encode([
                        'start_date' => $start,
                        'end_date' => $end,
                        'revenue' => (float) $row->revenue,
                        'orders' => (int) $row->orders,
                    ]);
                },
            ),
            new BetaRunnableTool(
                definition: [
                    'name' => 'get_low_stock_products',
                    'description' => 'Products at or below the low-stock threshold, with how many are on hand.',
                    'inputSchema' => [
                        'type' => 'object',
                        'properties' => [
                            'branch_id' => ['type' => 'integer', 'description' => 'Limit to one branch. Omit to check every branch.'],
                        ],
                        'required' => [],
                    ],
                ],
                run: function (array $input) use ($company): string {
                    $branchIds = $company->branches()->pluck('id');
                    if (! empty($input['branch_id']) && $branchIds->contains((int) $input['branch_id'])) {
                        $branchIds = collect([(int) $input['branch_id']]);
                    }

                    $onHand = StockTransaction::query()
                        ->whereIn('branch_id', $branchIds)
                        ->selectRaw('product_id, SUM(quantity) as on_hand')
                        ->groupBy('product_id')
                        ->havingRaw('SUM(quantity) <= ?', [StockTransaction::LOW_STOCK_THRESHOLD])
                        ->pluck('on_hand', 'product_id');

                    $names = Product::query()
                        ->whereIn('id', $onHand->keys())
                        ->where('company_id', $company->id)
                        ->pluck('name', 'id');

                    return json_encode(
                        $names->map(fn ($name, $id) => ['product' => $name, 'on_hand' => (int) $onHand[$id]])->values()
                    );
                },
            ),
            new BetaRunnableTool(
                definition: [
                    'name' => 'search_products',
                    'description' => 'Search this business\'s product catalog by name, SKU, or barcode.',
                    'inputSchema' => [
                        'type' => 'object',
                        'properties' => [
                            'query' => ['type' => 'string', 'description' => 'Product name, SKU, or barcode to search for.'],
                        ],
                        'required' => ['query'],
                    ],
                ],
                run: function (array $input) use ($company): string {
                    $results = app(ProductSearch::class)->search($company->id, (string) ($input['query'] ?? ''), null, 10);

                    return json_encode($results->map(fn ($p) => [
                        'name' => $p->name,
                        'price' => (float) $p->base_price,
                        'sku' => $p->sku,
                        'active' => (bool) $p->is_active,
                    ])->values());
                },
            ),
            new BetaRunnableTool(
                definition: [
                    'name' => 'get_top_products',
                    'description' => 'Best-selling products by revenue over a date range.',
                    'inputSchema' => [
                        'type' => 'object',
                        'properties' => [
                            'start_date' => ['type' => 'string', 'description' => 'YYYY-MM-DD, inclusive. Defaults to 30 days ago.'],
                            'end_date' => ['type' => 'string', 'description' => 'YYYY-MM-DD, inclusive. Defaults to today.'],
                            'limit' => ['type' => 'integer', 'description' => 'Max products to return. Defaults to 5, capped at 20.'],
                        ],
                        'required' => [],
                    ],
                ],
                run: function (array $input) use ($company): string {
                    $start = $input['start_date'] ?? now()->subDays(30)->toDateString();
                    $end = $input['end_date'] ?? now()->toDateString();
                    $limit = min(20, max(1, (int) ($input['limit'] ?? 5)));

                    $rows = DB::table('product_sales_daily')
                        ->whereIn('branch_id', $company->branches()->pluck('id'))
                        ->whereBetween('sale_date', [$start, $end])
                        ->selectRaw('product_id, SUM(quantity) as units, SUM(revenue) as revenue')
                        ->groupBy('product_id')
                        ->orderByDesc('revenue')
                        ->limit($limit)
                        ->get();

                    $names = Product::whereIn('id', $rows->pluck('product_id'))->pluck('name', 'id');

                    return json_encode($rows->map(fn ($r) => [
                        'product' => $names[$r->product_id] ?? 'Unknown',
                        'units_sold' => (int) $r->units,
                        'revenue' => (float) $r->revenue,
                    ])->values());
                },
            ),
        ];
    }
}
