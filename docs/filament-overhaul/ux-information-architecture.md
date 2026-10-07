# Filament Overhaul — UX & Information Architecture

## Design direction

Filament becomes the standard authenticated HR administration UI. The existing Vue/Bootstrap SPA remains available during migration and remains the compatibility surface for existing URLs/API clients until each replacement is verified.

### Proposed navigation

- **Overview**
  - Dashboard
  - My Tasks / Approvals
- **People**
  - Employees
  - Departments
  - Designations
  - Grades
  - Employment Types
  - Employee Documents
- **Time & Leave**
  - Leave Requests
  - Leave Balances
  - Leave Types
  - Attendance (when/if already present in the repository)
- **Payroll**
  - Payroll Periods
  - Payroll Runs
  - Payroll Transactions
  - Statutory Tax Brackets
  - Tax Reliefs
  - NSSF Rates
  - SHIF Rates
  - Housing Levy Rates
  - Payslips / Reports
- **Organization**
  - Tenant / Organization profile
  - Branches
  - Departments
- **Governance**
  - Audit Trail
  - Reports / Exports
- **Administration**
  - Users / profile
  - Settings
  - Notifications

Items not currently implemented are placeholders in the information architecture only and must not be presented as existing functionality until their repository presence is verified.

## Resource mapping

| Domain | Filament surface | Primary UX |
|---|---|---|
| Employee | Resource + profile/infolist + relations | Searchable table, filters, onboarding-style form, profile tabs |
| Branch | Resource | Table + form + department relation |
| Department | Resource | Table + form + branch dependency |
| Designation | Resource | Table + form |
| Grade | Resource | Table + salary-range validation |
| Employment Type | Resource | Table + form |
| Leave Type | Resource | Table + policy-aware form |
| Leave Balance | Resource/read-only relation | Employee/year/type filters |
| Leave Request | Resource + approval actions | Status badges, action confirmation, comments |
| Payroll Period | Resource | Status-driven actions |
| Payroll Run | Read-only resource/infolist | Totals and drill-down |
| Payroll Transaction | Resource/read-only | Privacy-aware table + payslip action |
| Statutory config | Resources | Effective-date-aware forms |
| Audit Log | Read-only resource | Actor/entity/action/date filters + detail infolist |
| Dashboard | Widgets/pages | KPI cards + drill-down tables |
| Existing Vue UI | Legacy page | Preserved until replacement verified |

## Dashboard wireframe

```
┌─────────────────────────────────────────────────────────────────────┐
│ BluePrint HR                         Search   Notifications   User  │
├──────────────┬──────────────────────────────────────────────────────┤
│ Overview     │  HR Dashboard                         Date range ▾  │
│ People       │                                                     │
│ Time & Leave │  ┌────────┐ ┌────────┐ ┌────────┐ ┌──────────────┐ │
│ Payroll      │  │Headcount│ │New hire│ │Pending │ │Payroll gross │ │
│ Organization │  │   128   │ │   12   │ │ leave  │ │ KES 12.4m   │ │
│ Governance   │  └────────┘ └────────┘ └────────┘ └──────────────┘ │
│ Admin        │                                                     │
│              │  Headcount trend        Leave / approvals           │
│              │  ┌─────────────────┐    ┌────────────────────────┐ │
│              │  │      chart      │    │ pending requests       │ │
│              │  └─────────────────┘    └────────────────────────┘ │
│              │                                                     │
│              │  Recent employee activity / expiring documents      │
└──────────────┴──────────────────────────────────────────────────────┘
```

## Employee profile wireframe

```
┌─────────────────────────────────────────────────────────────────┐
│ Employees / George Wamola                         Edit  Actions  │
├─────────────────────────────────────────────────────────────────┤
│ [Avatar] George Wamola    EMP-2026-001   Active   HR Manager    │
│                                                                 │
│ Overview | Employment | Statutory | Payroll | Leave | Audit    │
│                                                                 │
│ Personal information          Employment information            │
│ ID / DOB / gender              Branch / department / grade       │
│ Contact                        Employment type / start date      │
│                                                                 │
│ Sensitive financial/statutory values are permission-aware.      │
└─────────────────────────────────────────────────────────────────┘
```

## Leave approval wireframe

```
┌────────────────────────────────────────────────────────────────┐
│ Leave Request #123                         Pending              │
├────────────────────────────────────────────────────────────────┤
│ Employee        George Wamola                                  │
│ Leave type      Annual Leave                                   │
│ Dates           07 Oct → 15 Oct       Days: 7                   │
│ Balance         14 days available                              │
│ Reason          Family travel                                  │
│                                                                │
│ Activity / decision history                                    │
│                                                                │
│                         Reject   Approve                        │
└────────────────────────────────────────────────────────────────┘
```

## React/Vue integration rule

The audited repository currently uses Vue 3, not React. The migration therefore follows the same principle requested for React:

- Keep Vue until equivalent Filament behavior is implemented and tested.
- Do not create a second competing admin framework.
- Use Filament/Livewire for standard CRUD and workflows.
- If a specialized interactive Vue surface remains, document a token mapping for typography, spacing, surfaces, borders, badges, buttons and focus states.
- Keep Vite/Vue build green throughout.

## Accessibility

- Every form control has a programmatic label.
- Validation errors are associated with the relevant field.
- Keyboard focus remains visible.
- Tables are usable without pointer-only interactions.
- Confirmation dialogs describe the irreversible consequence.
- Color is never the only status indicator.
- Sensitive values use authorization-aware rendering.
- Mobile tables use responsive patterns rather than fixed-width layouts.
