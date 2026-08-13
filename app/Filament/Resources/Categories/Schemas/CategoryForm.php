<?php

namespace App\Filament\Resources\Categories\Schemas;

use App\Support\ImageOptimizer;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class CategoryForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Hidden::make('company_id')->default(fn () => auth()->user()->company_id),
                TextInput::make('name')->required(),
                TextInput::make('slug')->required(),
                Textarea::make('description')->columnSpanFull(),
                FileUpload::make('image_url')
                    ->image()
                    ->disk('minio')
                    ->directory('category-images')
                    ->saveUploadedFileUsing(fn (FileUpload $component, $file) => ImageOptimizer::store($file, $component->getDiskName(), $component->getDirectory())),
                Toggle::make('is_active')->required(),
                TextInput::make('sort_order')->numeric(),
            ]);
    }
}
