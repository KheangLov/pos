<?php

namespace App\Filament\Resources\Products\Schemas;

use App\Models\Company;
use App\Support\ImageOptimizer;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class ProductForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Hidden::make('company_id')->default(fn () => auth()->user()->company_id),
                Select::make('category_id')->relationship('category', 'name', fn ($query) => $query->where('company_id', auth()->user()->company_id))->searchable()->preload()->required(),
                TextInput::make('name')->required(),
                TextInput::make('slug')->required(),
                Textarea::make('description')->columnSpanFull(),
                FileUpload::make('image_url')
                    ->image()
                    ->disk('minio')
                    ->directory('product-images')
                    ->saveUploadedFileUsing(fn (FileUpload $component, $file) => ImageOptimizer::store($file, $component->getDiskName(), $component->getDirectory()))
                    ->helperText('Automatically resized and compressed to WebP, with a thumbnail generated for product grids.'),
                TextInput::make('base_price')->numeric()->prefix('$'),
                TextInput::make('cost_price')->numeric()->prefix('$'),
                TextInput::make('sku')->required(),
                TextInput::make('barcode')
                    ->required()
                    ->suffixAction(
                        Action::make('scanBarcode')
                            ->icon('heroicon-o-camera')
                            ->extraAttributes(['onclick' => "window.BarcodeScanner.open(code => \$wire.set('data.barcode', code))"]),
                    ),
                // Only shown for businesses that actually sell individually
                // trackable goods (see Company::usesSerialNumbers()) — hidden
                // for coffee shops, restaurants, etc. rather than asking every
                // business to answer questions that don't apply to them.
                Toggle::make('is_serialized')
                    ->visible(fn (Get $get): bool => Company::find($get('company_id'))?->usesSerialNumbers() ?? false)
                    ->required(),
                TextInput::make('warranty_period')
                    ->numeric()
                    ->suffix('days')
                    ->visible(fn (Get $get): bool => Company::find($get('company_id'))?->usesSerialNumbers() ?? false),
                Toggle::make('is_active')->required(),
                Select::make('modifierGroups')
                    ->relationship('modifierGroups', 'name', fn ($query) => $query->where('company_id', auth()->user()->company_id))
                    ->multiple()
                    ->preload()
                    ->searchable()
                    ->helperText('Modifier groups a cashier can choose from when adding this product in POS (e.g. milk options, add-ons).')
                    ->columnSpanFull(),
            ]);
    }
}
