<?php

namespace App\Filament\Resources\LeaveRequests;

use App\Filament\Resources\LeaveRequests\Pages\CreateLeaveRequest;
use App\Filament\Resources\LeaveRequests\Pages\ListLeaveRequests;
use App\Filament\Resources\TenantScopedResource;
use App\Models\LeaveRequest;
use App\Models\User;
use App\Services\AuditService;
use App\Services\LeaveDecisionService;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;
use BackedEnum;

class LeaveRequestResource extends TenantScopedResource
{
    protected static ?string $model = LeaveRequest::class;
    protected static string|UnitEnum|null $navigationGroup = 'Time & Leave';
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-inbox-arrow-down';
    protected static ?int $navigationSort = 30;

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()->with(['employee', 'leaveType', 'approver']);
        $user = auth()->user();

        if ($user instanceof User && $user->hasRole('Employee')) {
            $query->where('employee_id', $user->employee_id);
        }

        return $query;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('employee_id')
                ->relationship('employee', 'employee_no')
                ->getOptionLabelFromRecordUsing(fn ($record) => $record->full_name . ' (' . $record->employee_no . ')')
                ->searchable(['employee_no', 'first_name', 'last_name'])
                ->preload()
                ->required()
                ->disabled(fn () => auth()->user()?->hasRole('Employee')),
            Select::make('leave_type_id')->relationship('leaveType', 'name')->searchable()->preload()->required(),
            DatePicker::make('start_date')->required(),
            DatePicker::make('end_date')->required()->afterOrEqual('start_date'),
            TextInput::make('days_requested')->numeric()->minValue(0.5)->required(),
            Textarea::make('reason')->maxLength(2000)->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('employee.full_name')->label('Employee')->searchable(),
            TextColumn::make('leaveType.name')->label('Leave Type')->searchable(),
            TextColumn::make('start_date')->date()->sortable(),
            TextColumn::make('end_date')->date()->sortable(),
            TextColumn::make('days_requested')->sortable(),
            TextColumn::make('status')->badge()->sortable(),
            TextColumn::make('approver.name')->label('Decided By')->toggleable(),
            TextColumn::make('approved_at')->dateTime()->toggleable(),
        ])->filters([
            \Filament\Tables\Filters\SelectFilter::make('status')->options([
                'Pending' => 'Pending',
                'Approved' => 'Approved',
                'Rejected' => 'Rejected',
                'Cancelled' => 'Cancelled',
            ]),
        ])->recordActions([
            Action::make('approve')
                ->label('Approve')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->visible(fn (LeaveRequest $record) => $record->status === 'Pending' && auth()->user()?->role?->canApproveLeave())
                ->requiresConfirmation()
                ->action(function (LeaveRequest $record) {
                    $user = auth()->user();
                    $result = app(LeaveDecisionService::class)->decide($record, $user, 'Approved');
                    app(AuditService::class)->record('STATUS_CHANGE', 'LeaveRequest', $result->id, $record->toArray(), $result->toArray());
                    Notification::make()->success()->title('Leave request approved')->send();
                }),
            Action::make('reject')
                ->label('Reject')
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->visible(fn (LeaveRequest $record) => $record->status === 'Pending' && auth()->user()?->role?->canApproveLeave())
                ->requiresConfirmation()
                ->form([Textarea::make('decision_comment')->maxLength(2000)])
                ->action(function (LeaveRequest $record, array $data) {
                    $user = auth()->user();
                    $result = app(LeaveDecisionService::class)->decide($record, $user, 'Rejected', $data['decision_comment'] ?? null);
                    app(AuditService::class)->record('STATUS_CHANGE', 'LeaveRequest', $result->id, $record->toArray(), $result->toArray());
                    Notification::make()->success()->title('Leave request rejected')->send();
                }),
        ]);
    }

    public static function canCreate(): bool
    {
        return auth()->check() && auth()->user()->tenant_id !== null;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListLeaveRequests::route('/'),
            'create' => CreateLeaveRequest::route('/create'),
        ];
    }
}