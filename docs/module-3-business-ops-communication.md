# Module 3 — Business Ops & Communication (session "RH-3 Business Ops & Communication")

Read first: `../CLAUDE.md`, `docs/core-api.md`, then spec sections 2 (business lines),
4, 5, 12, 14, 15, 16, 17, 20, 22 (items 7, 9).

**Start only after the Foundation (Module 1, step 1) is on `main`.** Use the core helpers
and layout. Do not edit core files — write needed core changes in `docs/requests.md`.
Register everything through `inc/modules/business.php`. Every record that belongs to a line
of business carries the **business-line tag** (Outside Hiring, Inside Hiring, Marketing,
Chairman Services, General) and lists filter by it.

## Step 1 — HRM (spec 12, 14.2)

Employees with documents, Staff Attendance Register (reads core `staff_attendance`; all
staff, all days, check-in/out times), HRM attendance history, shifts, overtime, holidays,
leave requests with balances and approve/decline, payroll preparation and payslips,
performance indicators and review cycles, trainers and training types, announcements,
awards, promotions, transfers, resignations, terminations, disciplinary cases, employee
requests, employee self-service (staff see only their own record).

## Step 2 — CRM & POS (spec 14.1, 14.4)

Leads with source and owner, pipeline and deals (business-line tagged), contacts,
proposals/quotations, communication logs, follow-ups with overdue flags, lead-to-client
conversion with history. POS: sales per business line, sale items, barcode labels,
top-selling report.

## Step 3 — ERP, Projects, IT & Digital (spec 14.3, 14.5, 14.6)

ERP: contracts and contract types, suppliers and vendor bills, purchase requests with
approvals, purchase orders, purchase returns, warehouses, inventory and stock movement,
stock transfers, assets with assignment, work orders. Projects: business-line tagged,
milestones (completion % from tasks only), tasks with time/cost, bug register.
IT & Digital: service catalogue, lifecycle (Lead → Discovery → Quotation → Approval &
Contract → Milestones → Testing & UAT → Deployment → Support & Renewal), change requests,
client approvals, support tickets, campaign and content calendar.

## Step 4 — Travel & Tour, Marriage Paybook (spec 14.7, 14.8)

Travel: packages, enquiries, quotations, bookings (customer-paid vs supplier-paid vs
outstanding, profit and margin), supplier payments, support cases, Travel dashboard —
separate from hiring travel. Paybook: files with agreed value, payments, balances,
work tasks, communications, consultations, refunds only with Finance confirmation,
marketing conversion counts. Payments go through the core payment logic.

## Step 5 — WhatsApp (spec 15)

Settings: paste API key + Phone Number ID → integration on; show webhook URL and verify
token. `webhook.php`: Meta verification + store incoming messages + event log. Inbox:
categories, assignment, link to candidate file/client/demand/booking/project/paybook file,
reply notes, manual review queue, manual entry, health screen, reports. No provider sending.

## Step 6 — Communication & content (spec 16)

Messenger (internal staff conversations), Calendar (interviews, meetings, deadlines from all
modules) and Meetings (business line, attendees, agenda, Zoom link field), email templates +
email log, notification templates, CMS pages, media library, Knowledge Base and FAQ
(business-line tagged), newsletter subscribers, support tickets + Support Dashboard
(visibility per spec 5), Company Goals with milestones on the management dashboard.

**Done when:** spec Section 22 items 7 and 9 pass, every module page loads for a user with
that module, and each role dashboard (Travel, IT & Digital, HRM) shows live counters.
Merge to `main`.
