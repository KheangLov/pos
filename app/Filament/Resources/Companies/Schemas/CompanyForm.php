<?php

namespace App\Filament\Resources\Companies\Schemas;

use App\Models\Company;
use App\Support\ImageOptimizer;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
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
            ]);
    }
}
