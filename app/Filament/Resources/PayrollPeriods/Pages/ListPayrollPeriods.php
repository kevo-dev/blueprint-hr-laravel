<?php
namespace App\Filament\Resources\PayrollPeriods\Pages;

use App\Filament\Resources\PayrollPeriods\PayrollPeriodResource;
use App\Services\AuditService;
use App\Services\PayrollCalculationService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\Gate;
use RuntimeException;

class ListPayrollPeriods extends ListRecords
{
    protected static string $resource = PayrollPeriodResource::class;

    protected function getHeaderActions(): array
    {
        return [
            \Filament\Actions\CreateAction::make(),
        ];
    }

    protected function getTableRecordActions(): array
    {
        return [
            Action::make('process')
                ->label('Process Payroll')
                ->icon('heroicon-o-calculator')
                ->color('success')
                ->requiresConfirmation()
                ->modalHeading('Process payroll for this period?')
                ->modalDescription('This uses the existing payroll calculation service, creates the payroll run and transactions, and marks the period as processed.')
                ->visible(fn ($record): bool => (bool) auth()->user()?->role?->canProcessPayroll()
                    && $record->tenant_id === auth()->user()?->tenant_id
                    && ! in_array($record->status, ['Processed', 'Locked'], true))
                ->action(function ($record): void {
                    Gate::authorize('process', $record);

                    try {
                        $run = app(PayrollCalculationService::class)->process($record, (int) auth()->id());

                        app(AuditService::class)->record(
                            'payroll_processed',
                            'PayrollPeriod',
                            (int) $record->id,
                            null,
                            ['payroll_run_id' => $run->id, 'status' => $record->fresh()->status],
                            'Payroll period processed from Filament.'
                        );

                        Notification::make()
                            ->title('Payroll processed successfully')
                            ->body("Run #{$run->id} was created.")
                            ->success()
                            ->send();
                    } catch (RuntimeException $e) {
                        Notification::make()
                            ->title('Payroll could not be processed')
                            ->body($e->getMessage())
                            ->danger()
                            ->send();
                    }
                }),
        ];
    }
}
