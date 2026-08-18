<?php

namespace App\Enums;

enum Role: string
{
    case SuperAdmin = 'Super Admin';
    case CompanyAdmin = 'Company Admin';
    case HRManager = 'HR Manager';
    case PayrollManager = 'Payroll Manager';
    case Employee = 'Employee';

    public function canManagePeople(): bool
    {
        return in_array($this, [self::SuperAdmin, self::CompanyAdmin, self::HRManager], true);
    }

    public function canProcessPayroll(): bool
    {
        return in_array($this, [self::SuperAdmin, self::CompanyAdmin, self::PayrollManager], true);
    }

    public function canApproveLeave(): bool
    {
        return in_array($this, [self::SuperAdmin, self::CompanyAdmin, self::HRManager], true);
    }
}
