# HR / People Ops Module Plan

## Purpose

Define the final major product-expansion module for Port-101: `HR / People Ops`.

This plan is intentionally detailed. It is meant to answer:

- what the HR module covers
- how it fits into the current ERP architecture
- what its dependencies are on Core, Accounting, Projects, Reports, and Approvals
- how it should be implemented from migrations through UI and API
- what should ship first versus what should stay out of scope

This is a planning and delivery document, not a claim that the HR module is already implemented.

## 1) Position In Port-101

`HR / People Ops` should be a first-class business module, implemented the same way the rest of Port-101 is implemented:

- shared cross-cutting concerns in `app/Core`
- business logic in `app/Modules`
- web routes in `routes/moduleroutes/*.php`
- company workspace pages under `/company/*`
- API v1 endpoints under `/api/v1/*` where external integration is worth maintaining

Recommended module identity:

- code namespace: `App\Modules\Hr`
- route prefix: `/company/hr`
- API prefix: `/api/v1/hr`
- frontend pages: `resources/js/pages/hr/*`

This should be the last major feature expansion after the current operations/finance/projects scope.

## 2) Scope Decision

### In scope

The HR module for Port-101 should cover:

- employees
- leave / time off
- attendance
- payroll lite
- reimbursements

### Explicitly out of scope for this module

Do not mix these into the first HR rollout:

- recruiting / applicants
- onboarding workflow automation beyond simple employee activation
- performance reviews / appraisals
- learning management
- benefits administration
- country-specific payroll localization engines
- tax filing automation
- pension / social-security filing
- full workforce planning
- talent management

Those can exist later, but they are not part of the final planned Port-101 scope right now.

## 3) External Benchmark Patterns

The planning direction here follows current ERP/HCM patterns from official Odoo and Frappe HR documentation:

- employee records are separate from login users
- attendance and leave are distinct subdomains
- approvals are assigned at employee and/or department level
- payroll consumes normalized work/leave information instead of raw UI actions
- reimbursements can be paid directly or folded into payroll
- accounting integration is explicit and auditable

Key patterns worth copying into Port-101:

1. Employee records should exist without requiring a system login.
2. Employee-level approver defaults should exist, with department-level fallback.
3. Leave allocations and attendance records should be separate from payroll calculations.
4. Payroll should use frozen period snapshots or work entries, not volatile live data.
5. Expense reimbursement should support manager approval and Accounting handoff.
6. Payroll posting should remain tightly controlled and mostly internal, not broadly exposed in API v1.

## 4) Module Dependency Map

### Hard dependencies

`HR / People Ops` depends on:

- **Core**
  - companies
  - users and roles
  - settings
  - attachments
  - notifications
  - audit logs
  - approval authority / approval queue

- **Accounting**
  - reimbursement payments
  - payroll accrual journal entries
  - payroll payable / reimbursement payable settlement

### Strong integration dependencies

`HR / People Ops` should integrate tightly with:

- **Reports**
  - headcount
  - joiners/leavers
  - leave balances
  - attendance anomalies
  - payroll register
  - reimbursement aging

- **Approvals**
  - leave approval
  - attendance correction approval
  - reimbursement approval
  - payroll run approval or release

### Optional but important integrations

- **Projects**
  - optional cost-center or project allocation for payroll cost analysis
  - optional timesheet-aware payroll proration later
  - optional time-off visibility in project capacity planning

- **Notifications**
  - leave submitted / approved / rejected
  - attendance correction requested
  - reimbursement awaiting approval
  - payslip ready

### Dependencies that should stay weak or optional

- **Sales**
  - no hard dependency
  - only indirect if commissions are introduced later

- **Inventory / Purchasing**
  - no hard dependency
  - only indirect if uniforms, equipment, or employee advances become more complex later

## 5) High-Level Internal Dependency Order

Inside the HR module, the dependency order should be:

1. `Employees`
2. `Leave`
3. `Attendance`
4. `Reimbursements`
5. `Payroll Lite`

More specifically:

- `Employees` is foundational for every other HR subdomain
- `Leave` depends on employee, company calendar, and approver structure
- `Attendance` depends on employee, shifts, and leave interactions
- `Reimbursements` depends on employee plus Accounting handoff
- `Payroll Lite` depends on employee contracts/compensation and should read from leave, attendance, and reimbursements

## 6) Module Design Principles

### Core decisions

1. Keep `employee` separate from `user`
- `user_id` on employee should be nullable
- not every employee must be able to log into Port-101

2. Use employee records for HR, not company-user membership rows
- `company_users` remains workspace access and RBAC
- HR records live in dedicated HR tables

3. Use work-entry-style payroll inputs
- payroll should not compute directly from mutable attendance/leave screens
- it should freeze approved inputs for the payroll period

4. Keep payroll lite deliberately narrow
- salary structures
- payroll periods
- payslips
- payroll run
- accounting handoff
- no country-specific localization engine yet

5. Keep self-service and payroll-admin surfaces separate
- employees may request leave and submit reimbursement claims
- payroll posting remains finance/payroll admin only

## 7) Recommended Role Model

Add new functional roles:

- `hr_manager`
- `hr_officer`
- `payroll_manager`
- `employee_self_service`
- `line_manager`

These should integrate with the existing Port-101 role system rather than replacing it.

### Suggested permission namespaces

- `hr.employees.view`
- `hr.employees.manage`
- `hr.employees.private_view`
- `hr.employees.private_manage`
- `hr.leave.view`
- `hr.leave.manage`
- `hr.leave.approve`
- `hr.attendance.view`
- `hr.attendance.manage`
- `hr.attendance.approve`
- `hr.reimbursements.view`
- `hr.reimbursements.manage`
- `hr.reimbursements.approve`
- `hr.payroll.view`
- `hr.payroll.manage`
- `hr.payroll.post`
- `hr.payroll.approve`
- `hr.reports.view`

### Access model

- `employee_self_service`
  - own employee profile
  - own leave requests
  - own attendance entries / correction requests
  - own reimbursement claims
  - own payslip visibility only

- `line_manager`
  - direct-report leave approval
  - direct-report attendance correction approval
  - direct-report reimbursement first approval
  - read access to team attendance and leave balances

- `hr_officer`
  - manage employee master data
  - manage leave types and allocations
  - manage attendance structures
  - view reimbursements and payroll runs

- `hr_manager`
  - all HR admin capabilities
  - sensitive employee profile fields
  - approval override

- `payroll_manager`
  - compensation assignments
  - payroll run creation
  - payslip generation
  - payroll posting / release

### Privacy rules

This module needs stricter privacy than most existing modules.

Use separate visibility rules for:

- employee public profile data
- employee private HR data
- compensation data
- payslip data

Do not make compensation readable to general managers by default.

## 8) Data Model And Migrations

Create migrations under a new HR rollout.

### 8.1 Foundation tables

#### `hr_departments`

Purpose:

- reporting hierarchy
- leave approver defaults
- payroll grouping

Suggested fields:

- `id`
- `company_id`
- `name`
- `code`
- `manager_employee_id` nullable
- `leave_approver_user_id` nullable
- `attendance_approver_user_id` nullable
- `reimbursement_approver_user_id` nullable
- `payroll_cost_center_reference` nullable
- timestamps

#### `hr_designations`

Purpose:

- job title / classification / payroll grouping

Suggested fields:

- `id`
- `company_id`
- `name`
- `code`
- timestamps

#### `hr_employees`

Purpose:

- canonical employee record

Suggested fields:

- `id` UUID
- `company_id`
- `user_id` nullable
- `department_id` nullable
- `designation_id` nullable
- `employee_number`
- `employment_status` (`draft`, `active`, `leave`, `inactive`, `offboarded`)
- `employment_type` (`full_time`, `part_time`, `contract`, `intern`)
- `first_name`
- `last_name`
- `display_name`
- `work_email`
- `personal_email` nullable
- `work_phone` nullable
- `personal_phone` nullable
- `date_of_birth` nullable
- `hire_date`
- `termination_date` nullable
- `manager_employee_id` nullable
- `attendance_approver_user_id` nullable
- `leave_approver_user_id` nullable
- `reimbursement_approver_user_id` nullable
- `default_shift_id` nullable
- `timezone`
- `country_code` nullable
- `work_location` nullable
- `bank_account_reference` nullable
- `emergency_contact_name` nullable
- `emergency_contact_phone` nullable
- `notes` nullable
- `created_by`
- `updated_by`
- timestamps + soft deletes

Indexes:

- unique `(company_id, employee_number)`
- `(company_id, employment_status)`
- `(company_id, department_id)`
- `(company_id, manager_employee_id)`

#### `hr_employee_documents`

Purpose:

- employee contracts, IDs, permits, onboarding docs, payroll docs

Suggested fields:

- `id`
- `company_id`
- `employee_id`
- `attachment_id`
- `document_type`
- `is_private`
- `valid_until` nullable
- timestamps

#### `hr_employee_contracts`

Purpose:

- contract terms and compensation baseline

Suggested fields:

- `id`
- `company_id`
- `employee_id`
- `contract_number`
- `status` (`draft`, `active`, `expired`, `terminated`)
- `start_date`
- `end_date` nullable
- `pay_frequency` (`weekly`, `biweekly`, `monthly`)
- `salary_basis` (`fixed`, `hourly`)
- `base_salary_amount`
- `hourly_rate` nullable
- `currency_id`
- `salary_structure_id` nullable
- `working_days_per_week`
- `standard_hours_per_day`
- `is_payroll_eligible`
- `created_by`
- `updated_by`
- timestamps

### 8.2 Leave tables

#### `hr_holiday_calendars`

- `id`
- `company_id`
- `name`
- `country_code` nullable
- `is_default`
- timestamps

#### `hr_holiday_dates`

- `id`
- `company_id`
- `holiday_calendar_id`
- `holiday_date`
- `name`
- `is_half_day`
- timestamps

#### `hr_leave_types`

- `id`
- `company_id`
- `name`
- `code`
- `unit` (`days`, `hours`)
- `requires_allocation`
- `is_paid`
- `requires_approval`
- `allow_negative_balance`
- `max_consecutive_days` nullable
- `color` nullable
- timestamps

#### `hr_leave_periods`

- `id`
- `company_id`
- `name`
- `start_date`
- `end_date`
- `is_closed`
- timestamps

#### `hr_leave_policies`

- `id`
- `company_id`
- `department_id` nullable
- `designation_id` nullable
- `leave_type_id`
- `leave_period_id`
- `annual_allocation`
- `carry_forward_limit` nullable
- `accrual_method` (`none`, `monthly`, `quarterly`)
- timestamps

#### `hr_leave_allocations`

- `id`
- `company_id`
- `employee_id`
- `leave_type_id`
- `leave_period_id`
- `allocated_amount`
- `used_amount`
- `balance_amount`
- `carry_forward_amount`
- `expires_at` nullable
- timestamps

#### `hr_leave_requests`

- `id`
- `company_id`
- `employee_id`
- `leave_type_id`
- `leave_period_id`
- `requested_by_user_id`
- `approved_by_user_id` nullable
- `status` (`draft`, `submitted`, `approved`, `rejected`, `cancelled`)
- `from_date`
- `to_date`
- `duration_amount`
- `is_half_day`
- `reason` nullable
- `decision_notes` nullable
- `payroll_status` (`open`, `consumed`, `deferred`)
- timestamps

### 8.3 Attendance tables

#### `hr_shifts`

- `id`
- `company_id`
- `name`
- `code`
- `start_time`
- `end_time`
- `grace_minutes`
- `auto_attendance_enabled`
- timestamps

#### `hr_shift_assignments`

- `id`
- `company_id`
- `employee_id`
- `shift_id`
- `from_date`
- `to_date` nullable
- timestamps

#### `hr_attendance_checkins`

- `id`
- `company_id`
- `employee_id`
- `recorded_at`
- `log_type` (`in`, `out`)
- `source` (`manual`, `web`, `mobile`, `biometric`, `import`)
- `location_data` nullable
- `device_reference` nullable
- `created_by_user_id` nullable
- timestamps

#### `hr_attendance_records`

- `id`
- `company_id`
- `employee_id`
- `shift_id` nullable
- `attendance_date`
- `status` (`present`, `absent`, `on_leave`, `half_day`, `missing`, `holiday`)
- `check_in_at` nullable
- `check_out_at` nullable
- `worked_minutes`
- `overtime_minutes`
- `late_minutes`
- `approval_status` (`not_required`, `submitted`, `approved`, `rejected`)
- `approved_by_user_id` nullable
- `source_summary` nullable
- timestamps

#### `hr_attendance_requests`

- `id`
- `company_id`
- `employee_id`
- `requested_by_user_id`
- `approved_by_user_id` nullable
- `status` (`draft`, `submitted`, `approved`, `rejected`, `cancelled`)
- `from_date`
- `to_date`
- `requested_status`
- `reason`
- timestamps

### 8.4 Reimbursement tables

#### `hr_reimbursement_categories`

- `id`
- `company_id`
- `name`
- `code`
- `default_expense_account_reference`
- `requires_receipt`
- `is_project_rebillable`
- timestamps

#### `hr_reimbursement_claims`

- `id`
- `company_id`
- `employee_id`
- `claim_number`
- `status` (`draft`, `submitted`, `manager_approved`, `finance_approved`, `rejected`, `posted`, `paid`)
- `currency_id`
- `total_amount`
- `submitted_at` nullable
- `approved_at` nullable
- `approved_by_user_id` nullable
- `accounting_payment_id` nullable
- `payslip_id` nullable
- `project_id` nullable
- `notes` nullable
- timestamps

#### `hr_reimbursement_claim_lines`

- `id`
- `company_id`
- `claim_id`
- `category_id`
- `expense_date`
- `description`
- `amount`
- `tax_amount` nullable
- `receipt_attachment_id` nullable
- `project_id` nullable
- timestamps

### 8.5 Payroll lite tables

#### `hr_salary_structures`

- `id`
- `company_id`
- `name`
- `code`
- `pay_frequency`
- `currency_id`
- `is_active`
- timestamps

#### `hr_salary_structure_lines`

- `id`
- `company_id`
- `salary_structure_id`
- `line_type` (`earning`, `deduction`, `employer_cost`)
- `code`
- `name`
- `calculation_type` (`fixed`, `percentage`, `formula`, `manual`)
- `amount` nullable
- `percentage` nullable
- `formula` nullable
- `sequence`
- timestamps

#### `hr_compensation_assignments`

- `id`
- `company_id`
- `employee_id`
- `contract_id`
- `salary_structure_id`
- `effective_from`
- `effective_to` nullable
- `base_salary_amount`
- `hourly_rate` nullable
- `cost_center_reference` nullable
- timestamps

#### `hr_payroll_periods`

- `id`
- `company_id`
- `name`
- `pay_frequency`
- `start_date`
- `end_date`
- `payment_date`
- `status` (`draft`, `open`, `processing`, `closed`)
- timestamps

#### `hr_payroll_runs`

- `id`
- `company_id`
- `payroll_period_id`
- `status` (`draft`, `prepared`, `approved`, `posted`, `cancelled`)
- `prepared_by_user_id`
- `approved_by_user_id` nullable
- `posted_by_user_id` nullable
- `total_gross`
- `total_deductions`
- `total_net`
- `accounting_journal_entry_reference` nullable
- timestamps

#### `hr_payroll_work_entries`

- `id`
- `company_id`
- `employee_id`
- `payroll_period_id`
- `entry_type` (`worked_time`, `leave_paid`, `leave_unpaid`, `overtime`, `adjustment`, `reimbursement`)
- `source_type`
- `source_id`
- `from_datetime`
- `to_datetime`
- `quantity`
- `amount_reference` nullable
- `status` (`draft`, `confirmed`, `conflict`)
- `conflict_reason` nullable
- timestamps

#### `hr_payslips`

- `id`
- `company_id`
- `employee_id`
- `payroll_run_id`
- `payroll_period_id`
- `status` (`draft`, `approved`, `posted`, `paid`, `cancelled`)
- `gross_pay`
- `total_deductions`
- `net_pay`
- `reimbursement_amount`
- `currency_id`
- `issued_at` nullable
- `paid_at` nullable
- timestamps

#### `hr_payslip_lines`

- `id`
- `company_id`
- `payslip_id`
- `line_type` (`earning`, `deduction`, `reimbursement`, `employer_cost`)
- `code`
- `name`
- `quantity`
- `rate`
- `amount`
- `source_type` nullable
- `source_id` nullable
- `sequence`
- timestamps

## 9) Services Layer

Create domain services under `app/Modules/Hr`.

### Core services

#### `HrEmployeeService`

Responsibilities:

- create/update employee master records
- link/unlink employee from user account
- assign manager/department/designation/approvers
- manage employment status changes

#### `HrLeaveService`

Responsibilities:

- validate leave eligibility
- apply leave policies and allocations
- submit/approve/reject/cancel leave requests
- update leave balances
- emit payroll/work-entry effects for approved leave

#### `HrAttendanceService`

Responsibilities:

- store check-ins/check-outs
- build or refresh daily attendance records
- calculate worked, late, and overtime minutes
- create correction requests

#### `HrAttendanceApprovalService`

Responsibilities:

- approve/reject attendance correction requests
- resolve attendance conflicts before payroll

#### `HrReimbursementService`

Responsibilities:

- create claims and claim lines
- validate receipt requirements
- manager approval
- finance approval
- Accounting payment or payslip reimbursement handoff

#### `HrPayrollWorkEntryService`

Responsibilities:

- generate payroll work entries from attendance, leave, and approved reimbursement inputs
- flag conflicts
- freeze period inputs before payslip generation

#### `HrPayrollService`

Responsibilities:

- create payroll periods and runs
- fetch eligible employees
- generate payslips from compensation assignments and work entries
- apply earnings, deductions, leave/unpaid-day effects, reimbursements
- prepare Accounting journal entry payloads

#### `HrPayrollPostingService`

Responsibilities:

- approve payroll run
- post payroll accruals into Accounting
- mark payslips issued / paid
- preserve immutable audit history once posted

### Optional later services

- `HrEmployeeAdvanceService`
- `HrOffboardingService`
- `HrCapacityService`
- `HrProjectCostAllocationService`

## 10) Policies And Data Security

Create policies:

- `HrEmployeePolicy`
- `HrEmployeeDocumentPolicy`
- `HrLeaveRequestPolicy`
- `HrAttendanceRecordPolicy`
- `HrAttendanceRequestPolicy`
- `HrReimbursementClaimPolicy`
- `HrPayrollRunPolicy`
- `HrPayslipPolicy`

### Security rules

1. Employees can only see their own leave, attendance, reimbursements, and payslips.
2. Managers can see only direct-report or department-scoped records where configured.
3. HR can manage employee records but should not automatically see payroll posting actions.
4. Payroll users can see compensation and payslips.
5. Sensitive private employee data must be protected separately from general employee directory data.

### Segregation of duties

Recommended baseline:

- the same user should not both prepare and approve a payroll run
- the same user should not both submit and final-approve their own reimbursement claim
- the same user should not both request and final-approve their own leave override

## 11) Web Routes And Controllers

Create:

- `routes/moduleroutes/hr.php`

Recommended route groups:

- `/company/hr`
- `/company/hr/employees`
- `/company/hr/leave`
- `/company/hr/attendance`
- `/company/hr/reimbursements`
- `/company/hr/payroll`

Recommended controllers:

- `HrDashboardController`
- `HrEmployeesController`
- `HrEmployeeContractsController`
- `HrLeaveRequestsController`
- `HrLeaveAllocationsController`
- `HrAttendanceController`
- `HrAttendanceRequestsController`
- `HrReimbursementsController`
- `HrPayrollRunsController`
- `HrPayslipsController`

## 12) API Plan

Expose only the parts of HR that benefit from stable external or mobile integration.

### Good API candidates

- employee self-service profile read
- leave request create/list/status
- attendance check-in/check-out
- attendance correction request create
- reimbursement claim create/submit/list
- manager approval actions for leave and reimbursements

### Keep internal or web-only initially

- salary structure management
- payroll run creation
- payroll posting
- payslip regeneration
- compensation management

### Suggested API endpoints

- `GET /api/v1/hr/me`
- `GET /api/v1/hr/leave/requests`
- `POST /api/v1/hr/leave/requests`
- `POST /api/v1/hr/leave/requests/{request}/approve`
- `POST /api/v1/hr/leave/requests/{request}/reject`
- `POST /api/v1/hr/attendance/check-in`
- `POST /api/v1/hr/attendance/check-out`
- `GET /api/v1/hr/attendance/records`
- `POST /api/v1/hr/attendance/requests`
- `GET /api/v1/hr/reimbursements`
- `POST /api/v1/hr/reimbursements`
- `POST /api/v1/hr/reimbursements/{claim}/submit`
- `POST /api/v1/hr/reimbursements/{claim}/approve`
- `GET /api/v1/hr/payslips`
- `GET /api/v1/hr/payslips/{payslip}`

## 13) UI Plan

Create pages under `resources/js/pages/hr`.

### Module navigation

- Dashboard
- Employees
- Leave
- Attendance
- Reimbursements
- Payroll

### Key pages

- `resources/js/pages/hr/index.tsx`
- `resources/js/pages/hr/employees/index.tsx`
- `resources/js/pages/hr/employees/create.tsx`
- `resources/js/pages/hr/employees/show.tsx`
- `resources/js/pages/hr/leave/index.tsx`
- `resources/js/pages/hr/attendance/index.tsx`
- `resources/js/pages/hr/reimbursements/index.tsx`
- `resources/js/pages/hr/payroll/index.tsx`
- `resources/js/pages/hr/payroll/runs/show.tsx`
- `resources/js/pages/hr/payslips/show.tsx`

### UX expectations

- dashboard-first, not just CRUD-first
- self-service widgets for employees
- manager approval inbox for leave, attendance, reimbursements
- payroll control panels for HR/payroll users only
- clear privacy segregation for sensitive information

### Dashboard sections

- headcount
- active/inactive employees
- leave awaiting approval
- attendance anomalies
- reimbursements awaiting approval
- payroll period status
- joiners / leavers trend

## 14) Workflow Design

### A. Employee lifecycle

1. HR creates employee.
2. Employee may optionally be linked to a system user.
3. Department, designation, manager, approvers, contract, and shift are assigned.
4. Employee becomes active.

### B. Leave flow

1. Employee submits leave request.
2. Manager or HR approves/rejects.
3. Leave allocation is decremented.
4. Payroll work-entry impact is created for approved leave.

### C. Attendance flow

1. Employee checks in/out or attendance is imported.
2. Daily attendance record is generated.
3. Exceptions become correction requests.
4. Approved corrections update payroll work entries.

### D. Reimbursement flow

1. Employee creates reimbursement claim with receipts.
2. Manager approves.
3. Finance approves.
4. Claim is either:
   - paid directly through Accounting, or
   - included in the next payslip

### E. Payroll lite flow

1. Payroll period opens.
2. Work entries are generated/frozen from attendance, leave, and approved adjustments.
3. Payroll run fetches eligible employees and compensation assignments.
4. Payslips are generated.
5. Payroll manager approves run.
6. Accounting journal entry is created for accrual.
7. Payslips are issued and marked paid when settled.

## 15) Cross-Module Event Rules

Recommended events:

- `EmployeeActivated`
- `EmployeeOffboarded`
- `LeaveRequestApproved`
- `AttendanceRecordFinalized`
- `AttendanceCorrectionApproved`
- `ReimbursementClaimApproved`
- `PayrollRunApproved`
- `PayrollRunPosted`

Recommended consumers:

- `LeaveRequestApproved` -> generate payroll work-entry impact
- `AttendanceRecordFinalized` -> refresh payroll work entries
- `ReimbursementClaimApproved` -> create Accounting reimbursement payable or mark for payslip reimbursement
- `PayrollRunPosted` -> create Accounting journal entry link and notify employees/payload consumers
- `EmployeeOffboarded` -> deactivate user access, remove future project assignments, stop payroll eligibility

## 16) Accounting Integration Rules

### Reimbursements

- approved reimbursements should create a clear payable/payment flow in Accounting
- support direct payout or payslip reimbursement
- keep audit linkage from reimbursement claim to Accounting payment/journal reference

### Payroll lite

- payroll run posting should create accrual entries in Accounting
- optional detail level:
  - consolidated run posting first
  - employee-wise detail later if needed

### Deliberate limitation

Do not attempt full country payroll tax localization in the first implementation.

`Payroll Lite` for Port-101 should support:

- fixed and hourly pay
- earnings and deductions
- attendance/leave based proration
- reimbursement inclusion
- accounting accrual handoff

That is enough for the final planned product scope without dragging the system into jurisdiction-specific payroll complexity too early.

## 17) Reports Needed

The HR module should add:

- headcount snapshot
- joiners / leavers
- employee directory export
- leave balance report
- leave approval backlog
- attendance anomaly report
- overtime report
- reimbursement aging
- payroll register
- payslip summary

Export formats should follow existing Port-101 standards:

- PDF
- XLSX

## 18) Testing Plan

### Feature tests

- employee CRUD and company scoping
- leave allocation and request lifecycle
- manager approval flow
- attendance check-in/check-out and correction flow
- reimbursement submission/approval/payment handoff
- payroll period -> run -> payslip -> Accounting posting flow

### Authorization tests

- self-service employee access
- manager team access
- HR officer vs payroll manager separation
- compensation privacy

### API tests

- self-service endpoints
- manager approval endpoints
- rate limiting and company scoping

### Regression targets

- leave impact on payroll
- attendance conflicts before payroll
- reimbursement in payslip versus direct payment
- employee offboarding and future payroll exclusion

## 19) Delivery Sequence

### Phase 1: Employees foundation

- departments
- designations
- employees
- employee documents
- employee contracts
- roles and permissions

Exit condition:

- employee records exist end to end with privacy rules and attachments

### Phase 2: Leave

- leave types
- leave periods
- allocations
- leave requests
- approvals

Exit condition:

- leave balances and approvals work end to end

### Phase 3: Attendance

- shifts
- shift assignments
- check-ins
- attendance records
- correction requests

Exit condition:

- attendance data is reliable enough to feed payroll inputs

### Phase 4: Reimbursements

- categories
- claim lines
- receipts
- manager/finance approvals
- Accounting handoff

Exit condition:

- reimbursement claims can be approved and paid

### Phase 5: Payroll lite

- salary structures
- compensation assignments
- payroll periods
- payroll work entries
- payroll runs
- payslips
- Accounting accrual handoff

Exit condition:

- payroll run can be executed and posted in a controlled company scope

### Phase 6: Reports and API completion

- HR dashboard/reporting
- employee self-service API
- manager approval API
- payroll read APIs

## 20) Recommended Implementation Order

When implementation starts, the build order should be:

1. employee foundation schema + models
2. HR roles + policies
3. employee CRUD + contracts + documents
4. leave types / allocations / requests
5. attendance shifts / check-ins / records
6. reimbursement claims + Accounting handoff
7. payroll work entries + payroll runs + payslips
8. reports + APIs + notification polish

## 21) Recommended File Structure

- `app/Modules/Hr/Models/*`
- `app/Modules/Hr/*Service.php`
- `app/Http/Controllers/Hr/*`
- `app/Http/Requests/Hr/*`
- `app/Policies/Hr*Policy.php`
- `routes/moduleroutes/hr.php`
- `resources/js/pages/hr/*`
- `tests/Feature/Hr/*`

## 22) Model Map And Ownership

The HR module should be implemented as a set of subdomains rather than one oversized service.

Recommended model map:

### Employee foundation

- `Department`
- `Designation`
- `Employee`
- `EmployeeDocument`
- `EmployeeContract`

Ownership:

- employee identity, reporting line, approver defaults, work profile, private HR metadata
- contract and compensation baseline ownership for payroll eligibility

### Leave

- `HolidayCalendar`
- `HolidayDate`
- `LeaveType`
- `LeavePeriod`
- `LeavePolicy`
- `LeaveAllocation`
- `LeaveRequest`

Ownership:

- leave entitlement logic
- approval lifecycle
- leave balance effects
- payroll impact inputs for paid and unpaid leave

### Attendance

- `Shift`
- `ShiftAssignment`
- `AttendanceCheckin`
- `AttendanceRecord`
- `AttendanceRequest`

Ownership:

- raw check-in/check-out capture
- normalized daily attendance result
- correction approval flow
- late/overtime/worked-time metrics used by payroll work entries

### Reimbursements

- `ReimbursementCategory`
- `ReimbursementClaim`
- `ReimbursementClaimLine`

Ownership:

- employee expense submission
- receipt validation
- manager/finance approval states
- Accounting reimbursement handoff

### Payroll lite

- `SalaryStructure`
- `SalaryStructureLine`
- `CompensationAssignment`
- `PayrollPeriod`
- `PayrollRun`
- `PayrollWorkEntry`
- `Payslip`
- `PayslipLine`

Ownership:

- frozen pay-period inputs
- pay calculation and draft/approval lifecycle
- Accounting accrual linkage
- employee payslip publication

## 23) Dependency Matrix By Module

The HR module should consume from, integrate with, and publish to other modules as follows.

| Module | Dependency level | HR consumes from it | HR provides back |
| --- | --- | --- | --- |
| Core | Hard | companies, users, roles, settings, attachments, notifications, audit logs, approvals | employee-scoped notifications, audit events, attachment usage |
| Accounting | Hard | journals, payments, payable/accrual workflows, currencies | reimbursement payout requests, payroll accrual posting payloads, settlement references |
| Reports | Strong | export patterns, scheduled delivery, report jobs | headcount, leave, attendance, reimbursement, payroll datasets |
| Approvals | Strong | approval authority, approval queue, approval thresholds | leave approvals, attendance corrections, reimbursement approvals, payroll release |
| Projects/Services | Optional strong | project references for rebillable or labor-cost allocation, future availability context | employee capacity, approved leave visibility, optional labor cost allocation |
| Notifications | Strong | in-app delivery, email delivery patterns | manager alerts, employee self-service status notifications, payroll publication alerts |
| Attachments | Strong | receipt files, employee documents, payslip attachments | attachment metadata and retention use cases |
| Sales | Weak/optional | none for MVP; later commission context only | none initially |
| Inventory | Weak/optional | none for MVP | none initially |
| Purchasing | Weak/optional | none for MVP | none initially |

Implementation rule:

- HR should only create effects in other modules through explicit services and queued jobs.
- HR should never mutate Accounting, Projects, or Reports tables directly from controllers.

## 24) Implementation Blueprint By Layer

This is the recommended execution structure when coding the module.

### Migrations

Create the schema in grouped batches:

1. employee foundation migrations
2. leave migrations
3. attendance migrations
4. reimbursement migrations
5. payroll lite migrations

Migration standards:

- every business table is company-scoped
- use explicit status enums or constrained strings consistently
- add workflow indexes up front for approval queues and payroll processing
- use UUIDs where records are externally referenced or long-lived across workflows

### Models

Create Eloquent models under `app/Modules/Hr/Models`.

Model rules:

- prefer thin models with casts, relations, and scopes only
- keep business workflow in services
- add company scope helpers similar to other modules
- add self-service scopes such as `forEmployeeUser()` where the access pattern is common

### Services

Split services by workflow, not by controller.

Recommended first-pass services:

- `HrEmployeeService`
- `HrEmployeeDocumentService`
- `HrLeaveService`
- `HrAttendanceService`
- `HrAttendanceApprovalService`
- `HrReimbursementService`
- `HrPayrollWorkEntryService`
- `HrPayrollService`
- `HrPayrollPostingService`

Service rules:

- services own cross-table writes
- services emit explicit audit and notification events
- services integrate with Approvals and Accounting through existing patterns already used elsewhere in Port-101

### Policies And Permissions

Create policies around the actual privacy boundaries:

- employee record visibility
- private HR data visibility
- own-record self-service visibility
- manager team visibility
- payroll visibility and posting rights

Do not collapse all HR access into one broad admin permission. That would be too coarse for compensation and payslip privacy.

### Controllers And Requests

Recommended controller groups:

- `HrDashboardController`
- `HrEmployeesController`
- `HrEmployeeContractsController`
- `HrEmployeeDocumentsController`
- `HrLeaveTypesController`
- `HrLeaveRequestsController`
- `HrLeaveAllocationsController`
- `HrAttendanceController`
- `HrAttendanceRequestsController`
- `HrReimbursementsController`
- `HrPayrollPeriodsController`
- `HrPayrollRunsController`
- `HrPayslipsController`

Request validation should live in `app/Http/Requests/Hr`.

Validation rules should explicitly cover:

- company ownership for related records
- self-service versus manager-versus-HR actions
- date overlaps for leave and shift assignment
- status transitions for approvals and payroll posting

### Web UI

Recommended top-level workspace routes:

- `/company/hr`
- `/company/hr/employees`
- `/company/hr/leave`
- `/company/hr/attendance`
- `/company/hr/reimbursements`
- `/company/hr/payroll`

Recommended UX rule:

- separate self-service views from admin workspaces
- separate payroll controls from generic HR administration
- show approval inboxes as first-class views, not hidden actions inside tables

### API v1

Only expose stable self-service and manager approval workflows first.

Good first API set:

- employee self profile
- leave requests
- attendance check-in/check-out
- attendance correction requests
- reimbursement submission/status
- payslip read endpoints

Do not expose payroll run creation/posting in the first API slice.

### Reporting

Add HR datasets into the existing reports/export system rather than inventing a separate export stack.

First report set:

- employee directory
- headcount trend
- leave balances and leave backlog
- attendance anomalies and overtime
- reimbursement aging
- payroll register and payslip summary

### Tests

Create tests in phases that mirror the module rollout:

1. foundation + authorization
2. leave lifecycle
3. attendance lifecycle
4. reimbursement lifecycle
5. payroll lite lifecycle
6. API/self-service coverage

## 25) Release Guardrails And Non-Goals

Before the module can be treated as complete, these conditions should hold:

- employees can exist without system logins
- leave and attendance are manager/HR approved through clear policies
- reimbursement approvals create auditable Accounting handoff
- payroll uses frozen work entries, not raw mutable UI state
- payslip privacy is enforced separately from general HR visibility

Do not expand the first HR rollout into:

- full statutory payroll localization
- tax filing automation
- recruiting/appraisals/benefits
- full planning or workforce scheduling beyond basic attendance shifts
- broad public API mutation for payroll administration

## References

Official sources used for this plan:

- Odoo Employees: https://www.odoo.com/documentation/19.0/applications/hr/employees.html
- Odoo New Employees / approvers: https://www.odoo.com/documentation/19.0/applications/hr/employees/new_employee.html
- Odoo Work Entries: https://www.odoo.com/documentation/19.0/applications/hr/payroll/work_entries.html
- Odoo Approve Expenses: https://www.odoo.com/documentation/19.0/applications/finance/expenses/approve_expenses.html
- Odoo Reimburse Employees: https://www.odoo.com/documentation/19.0/applications/finance/expenses/reimburse.html
- Odoo Time Off Allocations: https://www.odoo.com/documentation/19.0/applications/hr/time_off/allocations.html
- Frappe HR Introduction: https://docs.frappe.io/hr/introduction
- Frappe HR Attendance: https://docs.frappe.io/hr/attendance
- Frappe HR Leave Application: https://docs.frappe.io/hr/leave-application
- Frappe HR Payroll Settings: https://docs.frappe.io/hr/payroll-settings
- Frappe HR Payroll Entry: https://docs.frappe.io/hr/payroll-entry
