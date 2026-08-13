<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PaymentMethodResource\Pages\ManagePaymentMethods;
use App\Models\PaymentMethod;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class PaymentMethodResource extends Resource
{
    protected static ?string $model = PaymentMethod::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-credit-card';

    protected static \UnitEnum|string|null $navigationGroup = 'Settings';

    public static function getEloquentQuery(): Builder
    {
        // Scope to the current company
        return parent::getEloquentQuery()->where('company_id', auth()->user()->company_id);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('branch_id')
                    ->label('Branch')
                    ->relationship('branch', 'name', fn (Builder $query) => $query->where('company_id', auth()->user()->company_id))
                    ->searchable()
                    ->preload()
                    ->helperText('Leave empty to apply to all branches globally.'),

                TextInput::make('name')
                    ->required()
                    ->maxLength(255)
                    ->placeholder('e.g., Cash, ABA QR'),

                Select::make('type')
                    ->required()
                    ->options([
                        'cash' => 'Cash',
                        'card' => 'Card',
                        'khqr' => 'KHQR',
                    ])
                    ->default('cash')
                    ->live(),

                Select::make('currency')
                    ->options([
                        'USD' => 'USD',
                        'KHR' => 'KHR',
                    ])
                    ->default('USD')
                    ->required()
                    ->visible(fn (Get $get) => $get('type') === 'khqr'),

                TextInput::make('account_details')
                    ->label('Bakong Account ID')
                    ->helperText('The name@bank format from your Bakong app (Profile → Account details), not your phone number.')
                    ->required(fn (Get $get) => $get('type') === 'khqr')
                    ->visible(fn (Get $get) => $get('type') === 'khqr')
                    ->maxLength(32),

                TextInput::make('merchant_id')
                    ->label('Bakong Merchant ID')
                    ->helperText('Optional. Leave both this and Acquiring Bank empty to generate an individual-account QR instead of a registered-merchant QR.')
                    ->visible(fn (Get $get) => $get('type') === 'khqr')
                    ->maxLength(255),

                TextInput::make('acquiring_bank')
                    ->label('Acquiring Bank')
                    ->visible(fn (Get $get) => $get('type') === 'khqr')
                    ->maxLength(255),

                Toggle::make('is_active')
                    ->default(true),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->searchable(),
                TextColumn::make('type')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => str($state)->upper()),
                TextColumn::make('branch.name')
                    ->label('Branch')
                    ->placeholder('Global (All Branches)'),
                TextColumn::make('account_details')
                    ->label('Account ID')
                    ->placeholder('—'),
                IconColumn::make('is_active')->boolean(),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                //
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManagePaymentMethods::route('/'),
        ];
    }
}
