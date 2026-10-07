<?php
namespace App\Filament\Resources\AuditLogs;
use App\Filament\Resources\AuditLogs\Pages\ListAuditLogs;
use App\Filament\Resources\TenantScopedResource; use App\Models\AuditLog;
use Filament\Schemas\Schema; use Filament\Tables\Columns\TextColumn; use Filament\Tables\Table; use UnitEnum; use BackedEnum;
class AuditLogResource extends TenantScopedResource {
 protected static ?string $model=AuditLog::class; protected static string|UnitEnum|null $navigationGroup='Governance'; protected static string|BackedEnum|null $navigationIcon='heroicon-o-shield-check';
 public static function form(Schema $schema):Schema{return $schema;}
 public static function table(Table $table):Table{return $table->columns([
  TextColumn::make('created_at')->dateTime()->sortable(), TextColumn::make('user.name')->label('User')->searchable(),
  TextColumn::make('action')->badge()->sortable(), TextColumn::make('entity_type')->searchable(), TextColumn::make('entity_id')->sortable(),
  TextColumn::make('ip_address')->toggleable(), TextColumn::make('details')->limit(80)->toggleable(),
 ])->defaultSort('created_at','desc');}
 public static function getPages():array{return ['index'=>ListAuditLogs::route('/')];}
}