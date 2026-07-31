<?php

namespace App\Filament\Resources\Categories\Schemas;

use App\Support\ImageOptimizer;
use Filament\Forms\Components\FileUpload;
use Filament\Schemas\Schema;

class CategoryForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                \Filament\Forms\Components\Select::make('company_id')->relationship('company', 'name')->searchable()->preload()->required(),
                \Filament\Forms\Components\TextInput::make('name')->required(),
                \Filament\Forms\Components\TextInput::make('slug')->required(),
                \Filament\Forms\Components\Textarea::make('description')->columnSpanFull(),
                FileUpload::make('image_url')
                    ->image()
                    ->disk('minio')
                    ->directory('category-images')
                    ->saveUploadedFileUsing(fn (FileUpload $component, $file) => ImageOptimizer::store($file, $component->getDiskName(), $component->getDirectory())),
                \Filament\Forms\Components\Toggle::make('is_active')->required(),
                \Filament\Forms\Components\TextInput::make('sort_order')->numeric()
            ]);
    }
}
