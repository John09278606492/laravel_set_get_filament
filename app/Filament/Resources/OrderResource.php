<?php

namespace App\Filament\Resources;

use App\Filament\Resources\OrderResource\Pages;
use App\Filament\Resources\OrderResource\RelationManagers;
use App\Models\Order;
use App\Models\Product;
use Closure;
use Filament\Forms;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Forms\Get; //get value inside the textinput
use Filament\Forms\Set; //set new value inside the textinput
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Validation\ValidationException;

class OrderResource extends Resource
{
    protected static ?string $model = Order::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function form(Form $form): Form
    {
        $calculations = function (Get $get, Set $set) {
            $items = $get('../../orderItems');
            $total = 0;

            if (is_array($items)) {
                foreach ($items as $item) {
                    $product = Product::find($item['product_id'] ?? null);
                    if ($product) {
                        $total += $product->cost_price * (float) ($item['quantity'] ?? 0);
                    }
                }
            }

            $set('total', $total);
        };

        $totalValue = function (Get $get, Set $set) {
            $getValue = $get('total');

            $set('output_total_duplicate', $getValue);
        };

        return $form
            ->schema([
                Section::make('Customer\'s Detail')
                    ->schema([
                        Select::make('user_id')
                            ->relationship('user', 'name')
                            ->searchable(),
                    ])
                    ->compact(),
                Section::make('Ordering Section')
                    ->schema([
                        Repeater::make('orderItems')
                            ->columnSpanFull()
                            ->relationship()
                            ->schema([
                                Select::make('product_id')
                                    ->relationship('product', 'name')
                                    ->live(debounce: 1000)
                                    ->afterStateUpdated(function (Set $set, $state) {
                                        $product = Product::find($state); //query
                                        $set('purchase_price', $product->cost_price ?? 0);
                                    }, $calculations)
                                    ->required()
                                    ->validationMessages([
                                        'required' => 'Please select a product'
                                    ])
                                    ->searchable()
                                    ->columnSpanFull(),
                                TextInput::make('purchase_price')
                                    ->disabled()
                                    ->dehydrated()
                                    ->numeric(),


                                TextInput::make('quantity')
                                    ->numeric()
                                    ->live(debounce: 1000)
                                    ->disabled(fn(Get $get) => $get('product_id') === null)
                                    ->afterStateUpdated($calculations)
                                    // ->afterStateUpdated(function (Get $get, Set $set, $component) use ($calculations) {
                                    //     if ($get('product_id') == null) {
                                    //         throw ValidationException::withMessages([
                                    //             $component->getStatePath() => 'Please select product first.',
                                    //         ]);
                                    //     }
                                    //     $calculations($get, $set);
                                    // })
                                    ->required(),
                            ]),
                        TextInput::make('total')
                            ->numeric()
                            ->disabled()
                            ->dehydrated(),

                        TextInput::make('amount_tendered')
                            ->numeric()
                            ->live(debounce: 1000)
                            ->disabled(fn(Get $get) => $get('total') === null || (float) $get('total') === 0.0)
                            ->rules([
                                fn(Get $get): Closure => function (string $attribute, $value, Closure $fail) use ($get) {
                                    $total = (float) $get('total');
                                    $amountTendered = (float) $value;
                                    if ($total > $amountTendered) {
                                        $fail('Amount tendered should be greater than or equal to total.');
                                    }
                                },
                            ])
                            ->afterStateUpdated(function (Set $set, Get $get, $state) {
                                $change = (float) $get('amount_tendered') - (float) $get('total');
                                $set('change', $change);
                            })
                            ->required(),
                        TextInput::make('input_total_duplicate')
                            ->live(debounce: 1000)
                            ->afterStateUpdated($totalValue),
                        TextInput::make('output_total_duplicate'),
                        TextInput::make('change')
                            ->numeric()
                            ->disabled()


                    ])
                    ->columns(2)
                    ->compact(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id'),
                //orderItems = OrderItem
                TextColumn::make('orderItems.product.name')
                    ->listWithLineBreaks(),
                TextColumn::make('orderItems.purchase_price')
                    ->listWithLineBreaks(),
                TextColumn::make('orderItems.quantity')
                    ->listWithLineBreaks(),
                TextColumn::make('sub_total')
                    ->state(fn($record) => $record->orderItems->map(
                        fn($item) => $item->purchase_price * $item->quantity
                    ))
                    ->listWithLineBreaks()
                    ->money('PHP')
                    ->label('Sub Total'),
                TextColumn::make('total')
                    ->money('PHP'),
                TextColumn::make('total1')
                    ->state(fn($record) => $record->orderItems->sum( //map = pro, sum = pro max
                        fn($item) => $item->purchase_price * $item->quantity
                    ))
                    ->listWithLineBreaks()
                    ->money('PHP')
                    ->label('Total'),
                TextColumn::make('created_at')
                    ->date('m/d/y')

            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListOrders::route('/'),
            'create' => Pages\CreateOrder::route('/create'),
            'edit' => Pages\EditOrder::route('/{record}/edit'),
        ];
    }
}
