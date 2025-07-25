<?php

namespace Lunar\Admin\Filament\Resources;

use Filament\Forms;
use Filament\Forms\Components\Component;
use Filament\Forms\Form;
use Filament\Pages\SubNavigationPosition;
use Filament\Support\Facades\FilamentIcon;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Str;
use Lunar\Admin\Base\LunarPanelDiscountInterface;
use Lunar\Admin\Filament\Resources\DiscountResource\Pages;
use Lunar\Admin\Filament\Resources\DiscountResource\RelationManagers\BrandLimitationRelationManager;
use Lunar\Admin\Filament\Resources\DiscountResource\RelationManagers\CollectionLimitationRelationManager;
use Lunar\Admin\Filament\Resources\DiscountResource\RelationManagers\CustomerLimitationRelationManager;
use Lunar\Admin\Filament\Resources\DiscountResource\RelationManagers\ProductConditionRelationManager;
use Lunar\Admin\Filament\Resources\DiscountResource\RelationManagers\ProductLimitationRelationManager;
use Lunar\Admin\Filament\Resources\DiscountResource\RelationManagers\ProductRewardRelationManager;
use Lunar\Admin\Filament\Resources\DiscountResource\RelationManagers\ProductVariantLimitationRelationManager;
use Lunar\Admin\Support\Resources\BaseResource;
use Lunar\DiscountTypes\AmountOff;
use Lunar\DiscountTypes\BuyXGetY;
use Lunar\Facades\Discounts;
use Lunar\Models\Contracts\Discount as DiscountContract;
use Lunar\Models\Currency;

class DiscountResource extends BaseResource
{
    protected static ?string $permission = 'sales:manage-discounts';

    protected static ?string $model = DiscountContract::class;

    protected static ?int $navigationSort = 3;

    protected static SubNavigationPosition $subNavigationPosition = SubNavigationPosition::End;

    public static function getLabel(): string
    {
        return __('lunarpanel::discount.label');
    }

    public static function getPluralLabel(): string
    {
        return __('lunarpanel::discount.plural_label');
    }

    public static function getNavigationIcon(): ?string
    {
        return FilamentIcon::resolve('lunar::discounts');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('lunarpanel::global.sections.sales');
    }

    public static function getDefaultForm(Form $form): Form
    {
        $discountSchemas = Discounts::getTypes()->map(function ($discount) {
            if (! $discount instanceof LunarPanelDiscountInterface) {
                return;
            }

            return Forms\Components\Section::make(Str::slug(get_class($discount)))
                ->heading($discount->getName())
                ->visible(
                    fn (Forms\Get $get) => $get('type') == get_class($discount)
                )->schema($discount->lunarPanelSchema());
        })->filter();

        return $form->schema([
            Forms\Components\Section::make('')->schema(
                static::getMainFormComponents()
            ),
            Forms\Components\Section::make('conditions')->schema(
                static::getConditionsFormComponents()
            )->heading(
                __('lunarpanel::discount.form.conditions.heading')
            ),
            Forms\Components\Section::make('buy_x_get_y')
                ->heading(
                    __('lunarpanel::discount.form.buy_x_get_y.heading')
                )
                ->visible(
                    fn (Forms\Get $get) => $get('type') == BuyXGetY::class
                )->schema(
                    static::getBuyXGetYFormComponents()
                ),
            Forms\Components\Section::make('amount_off')
                ->heading(
                    __('lunarpanel::discount.form.amount_off.heading')
                )
                ->visible(
                    fn (Forms\Get $get) => $get('type') == AmountOff::class
                )->schema(
                    static::getAmountOffFormComponents()
                ),
            ...$discountSchemas,
        ]);
    }

    protected static function getMainFormComponents(): array
    {
        return [
            Forms\Components\Group::make([
                static::getNameFormComponent(),
                static::getHandleFormComponent(),
            ])->columns(2),
            Forms\Components\Group::make([
                static::getStartsAtFormComponent(),
                static::getEndsAtFormComponent(),
            ])->columns(2),
            Forms\Components\Group::make([
                static::getPriorityFormComponent(),
                static::getDiscountTypeFormComponent(),
            ])->columns(2),
            static::getStopFormComponent(),
        ];
    }

    protected static function getConditionsFormComponents(): array
    {
        return [
            Forms\Components\Group::make([
                static::getCouponFormComponent(),
                static::getMaxUsesFormComponent(),
                static::getMaxUsesPerUserFormComponent(),
            ])->columns(3),
            Forms\Components\Fieldset::make()->schema(
                static::getMinimumCartAmountsFormComponents()
            )->label(
                __('lunarpanel::discount.form.minimum_cart_amount.label')
            ),
        ];
    }

    public static function getNameFormComponent(): Component
    {
        return Forms\Components\TextInput::make('name')
            ->label(__('lunarpanel::discount.form.name.label'))
            ->live(onBlur: true)
            ->afterStateUpdated(function (string $operation, $state, Forms\Set $set) {
                if ($operation !== 'create') {
                    return;
                }
                $set('handle', Str::slug($state));
            })
            ->required()
            ->maxLength(255)
            ->autofocus();
    }

    public static function getHandleFormComponent(): Component
    {
        return Forms\Components\TextInput::make('handle')
            ->label(__('lunarpanel::discount.form.handle.label'))
            ->required()
            ->unique(ignoreRecord: true)
            ->maxLength(255)
            ->autofocus();
    }

    public static function getStartsAtFormComponent(): Component
    {
        return Forms\Components\DateTimePicker::make('starts_at')
            ->label(__('lunarpanel::discount.form.starts_at.label'))
            ->required()
            ->before(function (Forms\Get $get) {
                return $get('ends_at');
            });
    }

    public static function getEndsAtFormComponent(): Component
    {
        return Forms\Components\DateTimePicker::make('ends_at')
            ->label(__('lunarpanel::discount.form.ends_at.label'));
    }

    protected static function getPriorityFormComponent(): Component
    {
        return Forms\Components\Select::make('priority')
            ->label(__('lunarpanel::discount.form.priority.label'))
            ->helperText(
                __('lunarpanel::discount.form.priority.helper_text')
            )
            ->options(function () {
                return [
                    1 => __('lunarpanel::discount.form.priority.options.low.label'),
                    5 => __('lunarpanel::discount.form.priority.options.medium.label'),
                    10 => __('lunarpanel::discount.form.priority.options.high.label'),
                ];
            });
    }

    protected static function getStopFormComponent(): Component
    {
        return Forms\Components\Toggle::make('stop')
            ->label(
                __('lunarpanel::discount.form.stop.label')
            );
    }

    protected static function getCouponFormComponent(): Component
    {
        return Forms\Components\TextInput::make('coupon')
            ->label(
                __('lunarpanel::discount.form.coupon.label')
            )->helperText(
                __('lunarpanel::discount.form.coupon.helper_text')
            )
            ->unique(ignoreRecord: true);
    }

    protected static function getMaxUsesFormComponent(): Component
    {
        return Forms\Components\TextInput::make('max_uses')
            ->label(
                __('lunarpanel::discount.form.max_uses.label')
            )->helperText(
                __('lunarpanel::discount.form.max_uses.helper_text')
            );
    }

    protected static function getMaxUsesPerUserFormComponent(): Component
    {
        return Forms\Components\TextInput::make('max_uses_per_user')
            ->label(
                __('lunarpanel::discount.form.max_uses_per_user.label')
            )->helperText(
                __('lunarpanel::discount.form.max_uses_per_user.helper_text')
            );
    }

    protected static function getMinimumCartAmountsFormComponents(): array
    {
        $currencies = Currency::enabled()->get();
        $inputs = [];

        foreach ($currencies as $currency) {
            $inputs[] = Forms\Components\TextInput::make('data.min_prices.'.$currency->code)->label(
                $currency->code
            )->afterStateHydrated(function (Forms\Components\TextInput $component, $state) {
                $currencyCode = last(explode('.', $component->getStatePath()));
                $currency = Currency::whereCode($currencyCode)->first();

                if ($currency) {
                    $component->state($state / $currency->factor);
                }
            });
        }

        return $inputs;
    }

    public static function getDiscountTypeFormComponent(): Component
    {
        return Forms\Components\Select::make('type')->options(
            Discounts::getTypes()->mapWithKeys(
                fn ($type) => [get_class($type) => $type->getName()]
            )
        )->required()->live();
    }

    protected static function getAmountOffFormComponents(): array
    {
        $currencies = Currency::get();

        $currencyInputs = [];

        foreach ($currencies as $currency) {
            $currencyInputs[] = Forms\Components\TextInput::make(
                'data.fixed_values.'.$currency->code
            )->label($currency->name)->afterStateHydrated(function (Forms\Components\TextInput $component, $state) use ($currencies) {
                $currencyCode = last(explode('.', $component->getStatePath()));
                $currency = $currencies->first(
                    fn ($currency) => $currency->code == $currencyCode
                );

                if ($currency) {
                    $component->state($state / $currency->factor);
                }
            });
        }

        return [
            Forms\Components\Toggle::make('data.fixed_value')
                ->label(__('lunarpanel::discount.form.fixed_value.label'))
                ->live(),
            Forms\Components\TextInput::make('data.percentage')
                ->label(__('lunarpanel::discount.form.percentage.label'))
                ->visible(
                    fn (Forms\Get $get) => ! $get('data.fixed_value')
                )->numeric(),
            Forms\Components\Group::make(
                $currencyInputs
            )->visible(
                fn (Forms\Get $get) => (bool) $get('data.fixed_value')
            )->columns(3),
        ];
    }

    public static function getBuyXGetYFormComponents(): array
    {
        return [
            Forms\Components\TextInput::make('data.min_qty')
                ->label(
                    __('lunarpanel::discount.form.min_qty.label')
                )->helperText(
                    __('lunarpanel::discount.form.min_qty.helper_text')
                )->numeric(),
            Forms\Components\Group::make([
                Forms\Components\TextInput::make('data.reward_qty')
                    ->label(
                        __('lunarpanel::discount.form.reward_qty.label')
                    )->helperText(
                        __('lunarpanel::discount.form.reward_qty.helper_text')
                    )->numeric(),
                Forms\Components\TextInput::make('data.max_reward_qty')
                    ->label(
                        __('lunarpanel::discount.form.max_reward_qty.label')
                    )->helperText(
                        __('lunarpanel::discount.form.max_reward_qty.helper_text')
                    )->numeric(),
            ])->columns(2),
            Forms\Components\Toggle::make('data.automatically_add_rewards')
                ->label(
                    __('lunarpanel::discount.form.automatic_rewards.label')
                )->helperText(
                    __('lunarpanel::discount.form.automatic_rewards.helper_text')
                ),
        ];
    }

    public static function getDefaultTable(Table $table): Table
    {
        return $table
            ->columns(static::getTableColumns())
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])->searchable();
    }

    protected static function getTableColumns(): array
    {
        return [
            Tables\Columns\TextColumn::make('status')
                ->formatStateUsing(function ($state) {
                    return __("lunarpanel::discount.table.status.{$state}.label");
                })
                ->label(__('lunarpanel::discount.table.status.label'))
                ->badge()
                ->color(fn (string $state): string => match ($state) {
                    \Lunar\Models\Discount::ACTIVE => 'success',
                    \Lunar\Models\Discount::EXPIRED => 'danger',
                    \Lunar\Models\Discount::PENDING => 'gray',
                    \Lunar\Models\Discount::SCHEDULED => 'info',
                })
                ->toggleable(),
            Tables\Columns\TextColumn::make('name')
                ->label(__('lunarpanel::discount.table.name.label'))
                ->searchable()
                ->sortable()
                ->toggleable(),
            Tables\Columns\TextColumn::make('type')
                ->formatStateUsing(function ($state) {
                    return (new $state)->getName();
                })
                ->label(__('lunarpanel::discount.table.type.label'))
                ->toggleable(),
            Tables\Columns\TextColumn::make('starts_at')
                ->label(__('lunarpanel::discount.table.starts_at.label'))
                ->date()
                ->sortable()
                ->toggleable(),
            Tables\Columns\TextColumn::make('ends_at')
                ->label(__('lunarpanel::discount.table.ends_at.label'))
                ->date()
                ->sortable()
                ->toggleable(),
            Tables\Columns\TextColumn::make('coupon')
                ->label(__('lunarpanel::discount.table.coupon.label'))
                ->searchable()
                ->sortable()
                ->toggleable(isToggledHiddenByDefault: true),
            Tables\Columns\TextColumn::make('created_at')
                ->label(__('lunarpanel::discount.table.created_at.label'))
                ->date()
                ->sortable()
                ->toggleable(isToggledHiddenByDefault: true),
        ];
    }

    public static function getDefaultSubNavigation(): array
    {
        return [
            Pages\EditDiscount::class,
            Pages\ManageDiscountAvailability::class,
            Pages\ManageDiscountLimitations::class,
        ];
    }

    protected static function getDefaultRelations(): array
    {
        return [
            CollectionLimitationRelationManager::class,
            BrandLimitationRelationManager::class,
            ProductLimitationRelationManager::class,
            ProductVariantLimitationRelationManager::class,
            CustomerLimitationRelationManager::class,
            ProductRewardRelationManager::class,
            ProductConditionRelationManager::class,
            ProductRewardRelationManager::class,
            ProductConditionRelationManager::class,
        ];
    }

    public static function getDefaultPages(): array
    {
        return [
            'index' => Pages\ListDiscounts::route('/'),
            'edit' => Pages\EditDiscount::route('/{record}'),
            'limitations' => Pages\ManageDiscountLimitations::route('/{record}/limitations'),
            'availability' => Pages\ManageDiscountAvailability::route('/{record}/availability'),
        ];
    }
}
