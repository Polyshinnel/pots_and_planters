<?php

namespace App\Filament\Resources;

use App\Filament\Resources\EnquiryResource\Pages\ListEnquiries;
use App\Filament\Resources\EnquiryResource\Pages\ViewEnquiry;
use App\Models\Enquiry;
use Filament\Forms\Components\DatePicker;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class EnquiryResource extends Resource
{
    protected static ?string $model = Enquiry::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-inbox';

    protected static string|\UnitEnum|null $navigationGroup = 'Enquiries';

    protected static ?string $navigationLabel = 'Enquiries';

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Enquiry')->schema([
                TextEntry::make('public_id')->label('Reference')->copyable(),
                TextEntry::make('type')->badge(),
                TextEntry::make('status')->badge(),
                TextEntry::make('submitted_at')->dateTime(),
                TextEntry::make('name'), TextEntry::make('email')->copyable(), TextEntry::make('phone')->copyable(),
                TextEntry::make('company')->placeholder('Not provided'),
                TextEntry::make('role')->placeholder('Not provided'), TextEntry::make('role_other')->placeholder('Not provided'),
                TextEntry::make('message')->placeholder('No message')->columnSpanFull(),
                TextEntry::make('source_path')->label('Source page')->placeholder('Not recorded'),
                TextEntry::make('source_placement')->label('Form placement')->placeholder('Not recorded'),
            ])->columns(2),
            Section::make('Verified catalogue snapshot')->schema([
                TextEntry::make('catalogue_snapshot.title')->label('Catalogue')->placeholder('No verified catalogue snapshot'),
                TextEntry::make('catalogue_snapshot.publicId')->label('Catalogue reference')->placeholder('—'),
            ])->columns(2),
            Section::make('Submitted items')->schema([
                TextEntry::make('items')->state(fn (Enquiry $record): string => $record->items->isEmpty()
                    ? 'No server-verified product items are available for this enquiry type.'
                    : $record->items->map(function ($item): string {
                        $snapshot = $item->product_snapshot ?? [];
                        $price = $item->unit_price_aed === null ? 'Price on enquiry' : 'AED '.(string) $item->unit_price_aed;

                        return ($item->brand_name_snapshot ? $item->brand_name_snapshot.' — ' : '')
                            .$item->sku_snapshot.' — '.($snapshot['name'] ?? 'Product').' × '.$item->quantity.' — '.$price;
                    })->implode("\n"))->columnSpanFull(),
            ]),
            Section::make('Telegram delivery (diagnostic only)')->schema([
                TextEntry::make('outboxEvent.status')->label('Outbox status')->placeholder('No outbox event'),
                TextEntry::make('outboxEvent.deliveries')->label('Recipient deliveries')->state(fn (Enquiry $record): string => $record->outboxEvent?->deliveries
                    ->map(fn ($delivery): string => $delivery->recipient_name_snapshot.' ('.$delivery->chat_id_snapshot.') — '.$delivery->status
                        .'; attempts: '.$delivery->attempts
                        .($delivery->safe_error ? '; '.$delivery->safe_error : ''))
                    ->implode("\n") ?: 'No deliveries recorded. No notification has been sent.')->columnSpanFull(),
            ])->columns(2),
            Section::make('History and internal notes')->schema([
                TextEntry::make('activities')->state(fn (Enquiry $record): string => $record->activities->map(fn ($activity): string => $activity->created_at->format('Y-m-d H:i').' — '.($activity->actor?->name ?? 'System').' — '
                    .($activity->kind === 'status' ? "Status: {$activity->old_status} → {$activity->new_status}" : "Internal note: {$activity->note}"))
                    ->implode("\n") ?: 'No status changes or internal notes yet.')->columnSpanFull(),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('id')->label('#')->sortable(),
            TextColumn::make('type')->badge()->searchable(),
            TextColumn::make('status')->badge()->sortable(),
            TextColumn::make('name')->searchable(),
            TextColumn::make('email')->searchable(),
            TextColumn::make('notification_status')->label('Notification')->state(fn (Enquiry $record): string => $record->outboxEvent?->status ?? 'not queued')->badge(),
            TextColumn::make('submitted_at')->dateTime()->sortable(),
        ])->filters([
            SelectFilter::make('type')->options(['contact' => 'Contact', 'product_enquiry' => 'Product enquiry', 'catalogue_download' => 'Catalogue download', 'cart_enquiry' => 'Cart enquiry']),
            SelectFilter::make('status')->options(['new' => 'New', 'in_progress' => 'In progress', 'completed' => 'Completed', 'cancelled' => 'Cancelled']),
            Filter::make('submitted_at')->schema([
                DatePicker::make('from'),
                DatePicker::make('until'),
            ])->query(fn (Builder $query, array $data): Builder => $query
                ->when($data['from'] ?? null, fn (Builder $q, $date): Builder => $q->whereDate('submitted_at', '>=', $date))
                ->when($data['until'] ?? null, fn (Builder $q, $date): Builder => $q->whereDate('submitted_at', '<=', $date))),
        ])->defaultSort('submitted_at', 'desc')->recordUrl(fn (Enquiry $record): string => static::getUrl('view', ['record' => $record]));
    }

    public static function getPages(): array
    {
        return ['index' => ListEnquiries::route('/'), 'view' => ViewEnquiry::route('/{record}')];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['outboxEvent.deliveries', 'items', 'activities.actor'])->orderByDesc('submitted_at')->orderByDesc('id');
    }
}
