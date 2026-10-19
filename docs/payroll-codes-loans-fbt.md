# Payroll codes, employee loans and FBT

## Payroll codes
Tenant-scoped earning, deduction, employer-contribution and benefit codes can be created through `POST /api/payroll/configuration/codes`. Each code supports fixed or percentage calculation, effective dates, taxable treatment metadata and statutory contribution flags. Assign a code to an employee with `POST /api/payroll/configuration/assignments`. Existing payroll transaction rows remain intact; component detail is saved as a JSON snapshot on new payroll calculations.

The code builder is not a tax-law override: taxable pay and statutory formulas remain controlled by the payroll calculation service. Review code classification with a Kenyan payroll professional before production use.

## Employee loans
Create loans using `POST /api/payroll/loans`. Newly created loans are Pending and are excluded from payroll until a payroll administrator activates them with a disbursement date using `PATCH /api/payroll/loans/{loan}/status`. Payroll deductions are capped at the outstanding principal and the configured monthly repayment. A unique loan/period repayment prevents duplicate deductions.

## Quarterly fringe benefit tax rates
List rates with `GET /api/payroll/fbt-rates` and add effective-dated rates with `POST /api/payroll/fbt-rates`. Overlapping periods are rejected. The prescribed annual rate and FBT tax rate are configurable, source-referenced values; do not assume a rate from this code is current Kenyan law. Employer FBT is recorded separately from employee PAYE and take-home deductions.

## Permissions and tenancy
All endpoints sit behind Sanctum authentication, tenant middleware and payroll-manager/administrator roles. Records are scoped by the authenticated tenant. Run migrations and the automated test suite before deployment.
