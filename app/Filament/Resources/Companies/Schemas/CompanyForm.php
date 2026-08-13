<?php

namespace App\Filament\Resources\Companies\Schemas;

use App\Models\Company;
use App\Support\ImageOptimizer;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class CompanyForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')->required(),
                Select::make('business_type')
                    ->label('Business type')
                    ->options(Company::businessTypes())
                    ->searchable()
                    ->preload()
                    ->helperText('Drives which features are relevant to this business — e.g. serial number tracking only applies to Computer / Phone Shop.')
                    ->required(),
                TextInput::make('email')->email()->required(),
                TextInput::make('phone')->required(),
                TextInput::make('address')->required(),
                FileUpload::make('logo_url')
                    ->image()
                    ->disk('minio')
                    ->directory('company-logos')
                    ->saveUploadedFileUsing(fn (FileUpload $component, $file) => ImageOptimizer::store($file, $component->getDiskName(), $component->getDirectory())),
                Toggle::make('is_active')->required(),
                Toggle::make('requires_2fa')
                    ->label('Require two-factor authentication')
                    ->helperText('Every user at this company must set up an authenticator app or email code before they can use the admin panel.'),
                Toggle::make('ai_assistant_enabled')
                    ->label('Enable AI Assistant')
                    ->live()
                    ->helperText('Lets staff ask questions about sales, stock, and orders in plain language.'),
                TextInput::make('anthropic_api_key')
                    ->label('Anthropic API key')
                    ->password()
                    ->revealable()
                    ->visible(fn (Get $get) => $get('ai_assistant_enabled'))
                    ->required(fn (Get $get) => $get('ai_assistant_enabled'))
                    ->helperText('Your own key from console.anthropic.com — usage is billed to your account, not ours. Leave blank when editing to keep the current key.')
                    ->dehydrated(fn (?string $state) => filled($state)),
            ]);
    }
}
