<?php
namespace App\Filament\Resources\PayrollTransactions\Pages;
use App\Filament\Resources\PayrollTransactions\PayrollTransactionResource; use Filament\Resources\Pages\ListRecords;
class ListPayrollTransactions extends ListRecords { protected static string $resource=PayrollTransactionResource::class; }