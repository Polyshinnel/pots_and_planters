<?php

namespace App\Filament\Pages;

use App\Models\TelegramRecipient;
use App\Models\TelegramSetting;
use App\Services\TelegramBotClient;
use App\Services\TelegramSettingsManager;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Throwable;

class TelegramSettings extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-paper-airplane';

    protected static string|\UnitEnum|null $navigationGroup = 'Enquiries';

    protected static ?string $navigationLabel = 'Telegram Settings';

    protected static ?string $title = 'Telegram Settings';

    protected string $view = 'filament.pages.telegram-settings';

    public ?array $data = [];

    public static function canAccess(): bool
    {
        return auth()->user()?->is_admin === true;
    }

    public function mount(): void
    {
        $settings = TelegramSetting::query()->find(1);
        $this->form->fill([
            'notifications_enabled' => $settings?->notifications_enabled ?? false,
            'bot_token' => '',
            'bot_token_configured' => filled($settings?->bot_token),
            'proxy_enabled' => $settings?->proxy_enabled ?? false,
            'proxy_host' => $settings?->proxy_host,
            'proxy_port' => $settings?->proxy_port,
            'proxy_username' => $settings?->proxy_username,
            'proxy_password' => '',
            'proxy_password_configured' => filled($settings?->proxy_password),
            'test_recipient_id' => null,
            'recipients' => TelegramRecipient::query()->orderBy('name')->get()->map(fn (TelegramRecipient $recipient): array => [
                'id' => $recipient->id, 'name' => $recipient->name, 'chat_id' => (string) $recipient->chat_id, 'enabled' => $recipient->enabled,
            ])->all(),
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Notifications')->schema([
                Toggle::make('notifications_enabled')->label('Enable Telegram notifications'),
                TextInput::make('bot_token')->label('Bot token')->password()->revealable(false)->autocomplete('new-password')->maxLength(500)
                    ->helperText(fn (): string => ($this->data['bot_token_configured'] ?? false) ? 'A token is configured. Leave blank to keep it.' : 'No token is configured.'),
                Toggle::make('proxy_enabled')->label('Use Squid HTTP proxy')->live(),
                TextInput::make('proxy_host')->label('Proxy host')->maxLength(253)->visible(fn (Get $get): bool => (bool) $get('proxy_enabled')),
                TextInput::make('proxy_port')->label('Proxy port')->numeric()->minValue(1)->maxValue(65535)->visible(fn (Get $get): bool => (bool) $get('proxy_enabled')),
                TextInput::make('proxy_username')->label('Proxy username')->maxLength(255)->visible(fn (Get $get): bool => (bool) $get('proxy_enabled')),
                TextInput::make('proxy_password')->label('Proxy password')->password()->revealable(false)->autocomplete('new-password')->maxLength(500)
                    ->helperText(fn (): string => ($this->data['proxy_password_configured'] ?? false) ? 'A password is configured. Leave blank to keep it.' : 'No proxy password is configured.')
                    ->visible(fn (Get $get): bool => (bool) $get('proxy_enabled')),
            ])->columns(2),
            Section::make('Recipients')->description(fn (): string => TelegramRecipient::query()->where('enabled', true)->count() === 0
                ? 'No enabled recipients are configured. Enquiries will still be stored; notifications will be marked pending without recipient deliveries.'
                : 'Disabled recipients remain visible but will not receive future notifications. Existing delivery snapshots are preserved.')
                ->schema([Repeater::make('recipients')->schema([
                    Hidden::make('id'),
                    TextInput::make('name')->required()->maxLength(120),
                    TextInput::make('chat_id')->label('Chat ID')->required()->maxLength(20)->regex('/^-?[1-9][0-9]{0,18}$/')
                        ->helperText('Signed integer string. Group and supergroup IDs can be negative.'),
                    Toggle::make('enabled')->default(true),
                ])->columns(3)->addActionLabel('Add recipient')->reorderable(false)->defaultItems(0)->columnSpanFull()]),
            Section::make('Connection checks')->description('These checks use the configured bot and proxy. Test messages are sent only to the selected enabled recipient and do not create an enquiry.')
                ->schema([Select::make('test_recipient_id')->label('Test recipient')->options(fn (): array => TelegramRecipient::query()->where('enabled', true)->orderBy('name')->pluck('name', 'id')->all())
                    ->placeholder('Choose an enabled recipient')->native(false)]),
        ])->statePath('data');
    }

    public function testConnection(TelegramBotClient $client): void
    {
        $this->authorizeTestAction();
        $settings = TelegramSetting::query()->find(1);
        if (! $settings || blank($settings->bot_token)) {
            Notification::make()->title('Bot token is not configured')->danger()->send();

            return;
        }
        try {
            $result = $client->getMe($settings);
        } catch (Throwable) {
            $result = ['ok' => false, 'name' => null, 'username' => null];
        }
        if ($result['ok']) {
            Notification::make()->title('Telegram connection successful')->body('Connected to '.($result['username'] ? '@'.$result['username'] : $result['name']).'.')->success()->send();
        } else {
            Notification::make()->title('Telegram connection failed')->body('Check the bot token and proxy settings.')->danger()->send();
        }
    }

    public function sendTestMessage(TelegramBotClient $client): void
    {
        $this->authorizeTestAction();
        $recipient = TelegramRecipient::query()->whereKey($this->data['test_recipient_id'] ?? null)->where('enabled', true)->first();
        $settings = TelegramSetting::query()->find(1);
        if (! $recipient || ! $settings || blank($settings->bot_token)) {
            Notification::make()->title('Choose a recipient and configure a bot token')->danger()->send();

            return;
        }
        try {
            $result = $client->sendMessage($settings, (string) $recipient->chat_id, 'Pots & Planters Telegram test message.');
        } catch (Throwable) {
            $result = ['ok' => false];
        }
        $notification = Notification::make()->title($result['ok'] ? 'Test message sent' : 'Test message failed')
            ->body($result['ok'] ? 'Telegram accepted the message for the selected recipient.' : 'Check the bot permissions, recipient, and proxy settings.');
        ($result['ok'] ? $notification->success() : $notification->danger())->send();
    }

    private function authorizeTestAction(): void
    {
        abort_unless(auth()->user()?->is_admin === true, 403);
        $key = 'telegram-test:'.auth()->id();
        if (! RateLimiter::attempt($key, 5, fn (): bool => true, 60)) {
            throw new TooManyRequestsHttpException(60, 'Please wait before running another Telegram test.');
        }
    }

    public function save(TelegramSettingsManager $manager): void
    {
        $state = $this->form->getState();
        $testRecipientId = $state['test_recipient_id'] ?? null;
        try {
            DB::transaction(function () use ($state, $manager): void {
                $manager->save([
                    'notifications_enabled' => $state['notifications_enabled'] ?? false,
                    'bot_token' => $state['bot_token'] ?? '',
                    'proxy_enabled' => $state['proxy_enabled'] ?? false,
                    'proxy_host' => $state['proxy_host'] ?? null,
                    'proxy_port' => $state['proxy_port'] ?? null,
                    'proxy_username' => $state['proxy_username'] ?? null,
                    'proxy_password' => $state['proxy_password'] ?? '',
                ]);

                $retained = [];
                foreach ($state['recipients'] ?? [] as $row) {
                    $recipient = isset($row['id']) ? TelegramRecipient::query()->find($row['id']) : null;
                    if (! $recipient) {
                        $recipient = new TelegramRecipient;
                    }
                    $recipient->fill(['name' => $row['name'], 'chat_id' => (string) $row['chat_id'], 'enabled' => (bool) ($row['enabled'] ?? false)]);
                    $recipient->save();
                    $retained[] = $recipient->id;
                }

                TelegramRecipient::query()->whereNotIn('id', $retained ?: [0])->delete();
            });
        } catch (QueryException) {
            throw ValidationException::withMessages(['data.recipients' => 'A recipient with this chat ID already exists, or the settings could not be saved.']);
        }

        $this->mount();
        if ($testRecipientId !== null && TelegramRecipient::query()->whereKey($testRecipientId)->where('enabled', true)->exists()) {
            $this->data['test_recipient_id'] = (string) $testRecipientId;
        }
        Notification::make()->title('Telegram settings saved')->success()->send();
    }
}
