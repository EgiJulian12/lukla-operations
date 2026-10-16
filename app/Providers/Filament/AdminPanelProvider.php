<?php

namespace App\Providers\Filament;

use App\Enums\UserRole;
use App\Filament\Resources\InventoryItems\InventoryItemResource;
use App\Filament\Resources\InventoryMovements\InventoryMovementResource;
use App\Filament\Resources\Materials\MaterialResource;
use App\Filament\Resources\MyPurchaseOrders\MyPurchaseOrderResource;
use App\Filament\Resources\OrderMaterialFlows\OrderMaterialFlowResource;
use App\Filament\Resources\QcBonuses\QcBonusResource;
use App\Filament\Resources\QcOrders\QcOrderResource;
use App\Filament\Resources\QcRecords\QcRecordResource;
use App\Filament\Resources\Repairs\RepairResource;
use App\Models\User;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationBuilder;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\View\PanelsRenderHook;
use Filament\Widgets\AccountWidget;
use Illuminate\Contracts\View\View;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login()
            ->colors([
                'primary' => Color::Amber,
            ])
            ->globalSearch(false)
            ->renderHook(
                PanelsRenderHook::USER_MENU_BEFORE,
                fn (): View => view('filament.components.navbar-user-badge'),
            )
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->navigation(function (NavigationBuilder $builder): NavigationBuilder|bool {
                /** @var User|null $user */
                $user = auth()->user();

                if ($user?->hasRole(UserRole::QC_INSPECTOR)) {
                    return $builder
                        ->items(Dashboard::getNavigationItems())
                        ->group(
                            'Quality Control',
                            [
                                ...QcOrderResource::getNavigationItems(),
                                ...QcRecordResource::getNavigationItems(),
                                ...RepairResource::getNavigationItems(),
                                ...QcBonusResource::getNavigationItems(),
                            ]
                        );
                }

                if ($user?->hasRole(UserRole::WAREHOUSE)) {
                    return $builder
                        ->items(Dashboard::getNavigationItems())
                        ->group(
                            'Gudang & Cutting',
                            [
                                ...OrderMaterialFlowResource::getNavigationItems(),
                                ...MyPurchaseOrderResource::getNavigationItems(),
                                ...InventoryItemResource::getNavigationItems(),
                                ...InventoryMovementResource::getNavigationItems(),
                            ]
                        )
                        ->group(
                            'Master Data',
                            [
                                ...MaterialResource::getNavigationItems(),
                            ]
                        );
                }

                // All other roles (Finance, Production Lead, Sample, etc.)
                // get the default Filament navigation with all registered items.
                return true;
            })
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->widgets([
                AccountWidget::class,
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
