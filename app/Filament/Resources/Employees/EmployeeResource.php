<?php

namespace App\Filament\Resources\Employees;

use App\Filament\Resources\Employees\Pages\CreateEmployee;
use App\Filament\Resources\Employees\Pages\EditEmployee;
use App\Filament\Resources\Employees\Pages\ListEmployees;
use App\Filament\Resources\Employees\Pages\ViewEmployee;
use App\Models\Employee;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class EmployeeResource extends Resource
{
    protected static ?string $model = Employee::class;

    protected static string|UnitEnum|null $navigationGroup = 'People';
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-users';
    protected static ?int $navigationSort = 10;
    protected static ?string $recordTitleAttribute = 'employee_no';

    public static function canCreate(): bool
    {
        return (bool) auth()->user()?->role?->canManagePeople();
    }

    public static function canEdit($record): bool
    {
        return (bool) auth()->user()?->role?->canManagePeople() && static::getEloquentQuery()->whereKey($record->getKey())->exists();
    }

    public static function canDelete($record): bool
    {
        return static::canEdit($record);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Identity')
                ->schema([
                    Grid::make(3)->schema([
                        TextInput::make('employee_no')->required()->maxLength(50),
                        TextInput::make('payroll_no')->maxLength(50),
                        Select::make('gender')->options([
                            'Male' => 'Male',
                            'Female' => 'Female',
                            'Other' => 'Other',
                        ]),
                        TextInput::make('first_name')->required()->maxLength(100),
                        TextInput::make('middle_name')->maxLength(100),
                        TextInput::make('last_name')->required()->maxLength(100),
                        DatePicker::make('dob'),
                        TextInput::make('id_no')->label('National ID')->maxLength(50),
                        TextInput::make('kra_pin')->label('KRA PIN')->maxLength(50),
                        TextInput::make('nssf_no')->label('NSSF No.')->maxLength(50),
                        TextInput::make('shif_no')->label('SHA/SHIF No.')->maxLength(50),
                        TextInput::make('phone')->tel()->maxLength(30),
                        TextInput::make('email')->email()->maxLength(150),
                    ]),
                ]),
            Section::make('Employment')
                ->schema([
                    Grid::make(3)->schema([
                        Select::make('branch_id')->relationship('branch', 'name')->searchable()->preload(),
                        Select::make('department_id')->relationship('department', 'name')->searchable()->preload(),
                        Select::make('designation_id')->relationship('designation', 'name')->searchable()->preload(),
                        Select::make('grade_id')->relationship('grade', 'name')->searchable()->preload(),
                        Select::make('employment_type_id')->relationship('employmentType', 'name')->searchable()->preload(),
                        Select::make('employment_status')->options([
                            'Active' => 'Active',
                            'Inactive' => 'Inactive',
                            'Terminated' => 'Terminated',
                            'On Leave' => 'On Leave',
                        ])->required(),
                        DatePicker::make('employment_date'),
                        TextInput::make('basic_salary')->numeric()->prefix('KES'),
                    ]),
                ]),
            Section::make('Banking')
                ->schema([
                    Grid::make(3)->schema([
                        TextInput::make('bank_name')->maxLength(150),
                        TextInput::make('bank_branch')->maxLength(150),
                        TextInput::make('account_number')->maxLength(100),
                    ]),
                ]),
        ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Employee Profile')
                ->schema([
                    Grid::make(3)->schema([
                        TextEntry::make('employee_no')->label('Employee No.'),
                        TextEntry::make('payroll_no')->label('Payroll No.'),
                        TextEntry::make('full_name'),
                        TextEntry::make('gender'),
                        TextEntry::make('dob')->date(),
                        TextEntry::make('employment_status')->badge(),
                        TextEntry::make('department.name')->label('Department'),
                        TextEntry::make('branch.name')->label('Branch'),
                        TextEntry::make('designation.name')->label('Designation'),
                        TextEntry::make('employmentType.name')->label('Employment Type'),
                        TextEntry::make('employment_date')->date(),
                        TextEntry::make('basic_salary')->money('KES'),
                        TextEntry::make('phone'),
                        TextEntry::make('email'),
                    ]),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('employee_no')
            ->columns([
                TextColumn::make('employee_no')->label('Employee No.')->searchable()->sortable(),
                TextColumn::make('full_name')->label('Name')->state(fn (Employee $record): string => $record->full_name)->searchable(['first_name', 'middle_name', 'last_name']),
                TextColumn::make('department.name')->label('Department')->sortable()->searchable(),
                TextColumn::make('designation.name')->label('Designation')->sortable()->searchable(),
                TextColumn::make('employment_status')->badge()->sortable(),
                TextColumn::make('basic_salary')->money('KES')->sortable(),
                TextColumn::make('employment_date')->date()->sortable(),
            ])
            ->filters([
                SelectFilter::make('employment_status')->options([
                    'Active' => 'Active',
                    'Inactive' => 'Inactive',
                    'Terminated' => 'Terminated',
                    'On Leave' => 'On Leave',
                ]),
                SelectFilter::make('department')->relationship('department', 'name'),
                SelectFilter::make('branch')->relationship('branch', 'name'),
            ])
            ->recordActions([
                \Filament\Actions\ViewAction::make(),
                \Filament\Actions\EditAction::make(),
                \Filament\Actions\DeleteAction::make(),
            ])
            ->toolbarActions([
                \Filament\Actions\BulkActionGroup::make([
                    \Filament\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getEloquentQuery(): Builder
    {
        $user = auth()->user();
        $query = parent::getEloquentQuery();

        if (! $user?->tenant_id) {
            return $query->whereRaw('1 = 0');
        }

        $query->where('tenant_id', (int) $user->tenant_id);

        if ($user->hasRole('Employee') && $user->employee_id) {
            $query->whereKey($user->employee_id);
        }

        return $query;
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['employee_no', 'payroll_no', 'first_name', 'middle_name', 'last_name', 'email'];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListEmployees::route('/'),
            'create' => CreateEmployee::route('/create'),
            'view' => ViewEmployee::route('/{record}'),
            'edit' => EditEmployee::route('/{record}/edit'),
        ];
    }
}
