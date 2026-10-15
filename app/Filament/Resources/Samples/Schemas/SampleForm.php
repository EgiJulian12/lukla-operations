<?php

namespace App\Filament\Resources\Samples\Schemas;

use App\Models\Sample;
use Filament\Actions\Action;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

/**
 * Formulir sample.
 *
 * Status tidak diinput di sini: seluruh perpindahan status dijalankan oleh
 * SampleService, sehingga sample baru selalu lahir sebagai draf.
 */
class SampleForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Informasi Pesanan & Klien')
                    ->description('Konteks pesanan terkait dari klien.')
                    ->columns(4)
                    ->visible(fn (?Sample $record): bool => $record !== null)
                    ->schema([
                        Placeholder::make('order_number')
                            ->label('Nomor Order')
                            ->content(fn (?Sample $record): string => $record?->article?->order?->order_number ?? '-'),
                        Placeholder::make('client_name')
                            ->label('Klien')
                            ->content(fn (?Sample $record): string => $record?->client?->name ?? '-'),
                        Placeholder::make('work_scheme')
                            ->label('Skema Maklon')
                            ->content(fn (?Sample $record): string => $record?->article?->order?->work_scheme?->getLabel() ?? '-'),
                        Placeholder::make('sample_status')
                            ->label('Status Sample')
                            ->content(fn (?Sample $record): string => $record?->status?->getLabel() ?? 'Draf'),
                    ]),

                Section::make('Desain & Acuan Permintaan Klien')
                    ->description('Gambar acuan dan instruksi desain pesanan dari klien.')
                    ->visible(fn (?Sample $record): bool => $record !== null)
                    ->headerActions([
                        Action::make('downloadDesigns')
                            ->label('Download Berkas Desain')
                            ->icon(Heroicon::OutlinedArrowDownTray)
                            ->color('primary')
                            ->url(fn (?Sample $record): ?string => $record ? route('samples.designs.download', $record) : null)
                            ->openUrlInNewTab()
                            ->visible(fn (?Sample $record): bool => ! empty($record?->designImages())),
                    ])
                    ->schema([
                        Placeholder::make('design_description')
                            ->label('Deskripsi Permintaan Desain')
                            ->content(fn (?Sample $record): string => $record?->designDescription() ?? 'Tidak ada catatan deskripsi khusus dari klien.')
                            ->columnSpanFull(),
                    ]),

                Section::make('Detail Sample & Artikel')
                    ->columns(2)
                    ->schema([
                        Select::make('article_id')
                            ->label('Artikel')
                            ->relationship('article', 'name')
                            ->searchable()
                            ->preload()
                            ->required()
                            ->helperText('Klien pemilik sample mengikuti artikel ini.'),
                        TextInput::make('code')
                            ->label('Kode Sample')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('title')
                            ->label('Judul Sample')
                            ->required()
                            ->columnSpanFull()
                            ->maxLength(255),
                        Textarea::make('notes')
                            ->label('Catatan Sample')
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
