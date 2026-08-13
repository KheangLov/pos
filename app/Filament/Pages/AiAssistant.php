<?php

namespace App\Filament\Pages;

use App\Services\AiAssistantService;
use Filament\Pages\Page;

class AiAssistant extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-sparkles';

    protected static ?string $navigationLabel = 'AI Assistant';

    protected static ?string $title = 'AI Assistant';

    protected static \UnitEnum|string|null $navigationGroup = 'Operations';

    protected string $view = 'filament.pages.ai-assistant';

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return ($user?->hasAnyRole(['Admin', 'Manager']) ?? false)
            && ($user?->company?->ai_assistant_enabled ?? false);
    }

    /** @var array<int, array{role: string, text: string}> Display-only transcript. */
    public array $messages = [];

    /** @var array<int, array{role: string, content: mixed}> Raw API-shaped history fed back to the assistant each turn. */
    public array $history = [];

    public string $question = '';

    public function send(): void
    {
        $question = trim($this->question);

        if ($question === '') {
            return;
        }

        $this->messages[] = ['role' => 'user', 'text' => $question];
        $this->question = '';

        $result = app(AiAssistantService::class)->ask(auth()->user()->company, $question, $this->history);

        if (isset($result['error'])) {
            $this->messages[] = ['role' => 'error', 'text' => $result['error']];

            return;
        }

        $this->messages[] = ['role' => 'assistant', 'text' => $result['answer']];
        $this->history = $result['history'];
    }

    public function clear(): void
    {
        $this->messages = [];
        $this->history = [];
    }
}
