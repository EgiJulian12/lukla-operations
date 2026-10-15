<?php

namespace App\Filament\Resources\Users\Actions;

use App\Enums\UserRole;
use App\Models\User;
use App\Services\Ai\TelegramRegistrationService;
use Filament\Actions\Action;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\HtmlString;

/**
 * Table row action: generates a single-use Telegram registration token for a
 * CLIENT_MAKLON user and displays it in a modal for the admin to share.
 *
 * The modal leads with a one-tap deep link (https://t.me/<bot>?start=<token>)
 * that registers the client automatically, with the raw /start command kept as
 * a manual fallback.
 *
 * Only visible for users with the CLIENT_MAKLON role who have an active account.
 * Replaces any existing unused token so there is never more than one valid token
 * per user.
 */
class GenerateTelegramTokenAction
{
    public static function make(): Action
    {
        return Action::make('generateTelegramToken')
            ->label('Token Telegram')
            ->icon(Heroicon::OutlinedDevicePhoneMobile)
            ->color('info')
            ->visible(fn (User $record): bool => $record->is_active
                && $record->hasRole(UserRole::CLIENT_MAKLON->value)
            )
            ->action(function (User $record): array {
                $service = app(TelegramRegistrationService::class);

                return $service->generateToken($record);
            })
            ->successNotificationTitle('Token berhasil dibuat')
            ->modalHeading(fn (User $record): string => "Token Telegram — {$record->name}")
            ->modalDescription('Bagikan token ini kepada klien. Token berlaku selama 24 jam dan hanya dapat digunakan sekali.')
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('Tutup')
            ->modalContent(function (User $record): HtmlString {
                $service = app(TelegramRegistrationService::class);
                $result = $service->generateToken($record);

                $token = $result['token'];
                $expiresAt = $result['expires_at']->format('d M Y, H:i');
                $botUsername = config('services.telegram.bot_username', 'LuklaOperationsBot');
                $deepLink = $service->buildDeepLink($token);

                return new HtmlString(
                    self::buildModalHtml($record->name, $token, $expiresAt, $botUsername, $deepLink)
                );
            })
            ->modalWidth('lg');
    }

    private static function buildModalHtml(
        string $userName,
        string $token,
        string $expiresAt,
        string $botUsername,
        string $deepLink,
    ): string {
        $startCommand = "/start {$token}";

        return <<<HTML
        <div style="font-family:sans-serif">

            <!-- Token display -->
            <div style="background:#f0f9ff;border:1px solid #bae6fd;border-radius:10px;padding:20px;margin-bottom:16px;text-align:center">
                <p style="margin:0 0 8px;font-size:12px;text-transform:uppercase;letter-spacing:.08em;color:#0369a1;font-weight:600">Token Registrasi</p>
                <p style="margin:0;font-size:28px;font-weight:800;letter-spacing:.2em;color:#0c4a6e;font-family:monospace">{$token}</p>
                <p style="margin:8px 0 0;font-size:12px;color:#64748b">Berlaku hingga: <strong>{$expiresAt}</strong></p>
            </div>

            <!-- One-tap deep link -->
            <div style="background:#ffffff;border:1px solid #e2e8f0;border-radius:10px;padding:16px;margin-bottom:16px;text-align:center">
                <p style="margin:0 0 4px;font-size:13px;font-weight:600;color:#374151">Cara tercepat — kirim link ini ke <strong>{$userName}</strong>:</p>
                <p style="margin:0 0 12px;font-size:12px;color:#64748b">Sekali klik, akun langsung terhubung tanpa perlu mengetik apa pun.</p>
                <a href="{$deepLink}" target="_blank" rel="noopener noreferrer"
                   style="display:inline-block;background:#0088cc;color:#ffffff;text-decoration:none;font-size:14px;font-weight:600;padding:12px 24px;border-radius:8px">
                    Buka Telegram &amp; Hubungkan Akun
                </a>
                <div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;padding:10px 12px;margin-top:14px;word-break:break-all">
                    <code style="color:#334155;font-size:12px;font-family:monospace">{$deepLink}</code>
                </div>
            </div>

            <!-- Manual fallback -->
            <div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:10px;padding:16px;margin-bottom:16px">
                <p style="margin:0 0 10px;font-size:13px;font-weight:600;color:#374151">Jika link tidak bisa dibuka:</p>
                <ol style="margin:0;padding-left:20px;color:#475569;font-size:13px;line-height:1.8">
                    <li>Buka Telegram dan cari bot <strong>@{$botUsername}</strong></li>
                    <li>Kirim perintah berikut ke bot:</li>
                </ol>
                <div style="background:#1e293b;border-radius:8px;padding:12px 16px;margin-top:10px">
                    <code style="color:#e2e8f0;font-size:15px;font-family:monospace">{$startCommand}</code>
                </div>
                <p style="margin:10px 0 0;font-size:12px;color:#94a3b8">Token hanya berlaku sekali. Jika sudah digunakan atau kedaluwarsa, buat token baru.</p>
            </div>

            <!-- Warning -->
            <div style="display:flex;align-items:flex-start;gap:8px;background:#fffbeb;border:1px solid #fde68a;border-radius:8px;padding:12px">
                <span style="font-size:16px;flex-shrink:0">⚠️</span>
                <p style="margin:0;font-size:12px;color:#92400e">Jangan bagikan token atau link ini kepada pihak yang tidak berwenang. Keduanya memberikan akses penuh ke data pesanan klien tersebut.</p>
            </div>

        </div>
        HTML;
    }
}
