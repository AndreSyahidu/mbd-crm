# MBD CRM — Known Limitations & Pilot Notes

Status: ready for an **internal pilot**. The full pipeline works end-to-end
(Lead → Qualification → Follow-Up → Discovery → Deposit → Planning → Client
Approval → Negotiation → Closing) and all stage gates and audit coverage are
verified.

## Verification method
- All behavioural checks (gates, automation, audit, metrics, import/export,
  full pipeline through the real controllers, activation/deactivation) were
  run against **stubbed WordPress + an in-memory `$wpdb`**. They have **not**
  yet been exercised on a live WordPress install with MySQL.
- `php -l` passes on every PHP file.
- Recommended before pilot: activate on a staging WordPress, flush permalinks
  (done automatically on activation), and walk the pipeline once per role.

## Security posture
- Every state-changing request verifies a nonce (`check_admin_referer`) and a
  capability; ownership is enforced for sales-scoped records.
- All dynamic SQL uses `$wpdb->prepare`; table names derive from the trusted
  prefix. Static queries are marked accordingly.
- Output is escaped in views; the `Components` helper escapes its inputs.
- Uploads go through `media_handle_upload` (CSV import also checks the
  extension and `is_uploaded_file`).

## Known limitations
1. **Master-options import** is stored in an option (`mbd_crm_master_options`)
   but is **not yet merged** into the live vocabularies (the `Options` classes
   are static). Surfacing imported options in dropdowns is a follow-up.
2. **"Customers" import** is mapped to lead records; there is no separate
   customer entity yet.
3. **Notifications** are computed on page load (due promises, deposit/closing
   approvals). There is no read/seen state and no email or WhatsApp push.
4. **SLAs** store a due time and are surfaced as overdue in the dashboard /
   notifications on load; there is no background cron escalation yet.
5. **One active record per lead** for deposit, planning, and closing. Full
   history is retained for qualifications, discoveries, negotiation log, and
   planning revisions/deliverables.
6. **Project draft** on closing approval fires a bridge hook
   (`mbd_crm_generate_project_draft`); no ERP/project-management integration
   is implemented.
7. **Lists and reports are unpaginated** — appropriate for pilot data volumes;
   add pagination before large-scale use.
8. **Approver role**: closing approval uses the `mbd_crm_approve_closing`
   capability (granted to Owner/Administrator). There is no separate
   "Approver" role yet.
9. **Multisite**: uninstall cleans each site; network-activation has not been
   specially tuned.

## Data & cleanup
- Activation creates all tables (via `dbDelta`, repeatable) and the CRM roles
  (Owner, Sales, Viewer, Finance) plus admin capabilities.
- Deactivation only flushes rewrite rules — no data is removed.
- Uninstall removes data **only** when "Remove data on uninstall" is enabled
  in Settings; it then drops all tables, options, roles, and capabilities.
