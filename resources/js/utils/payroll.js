// Payroll label/color maps. Keep in sync with the model constants
// (PayrollRun::STATUSES, StatutoryRuleVersion::STATUSES, PayrollDisbursement::STATUSES, ...).
export const RUN_STATUS = {
  draft: { label: "Draft", color: "grey" },
  calculated: { label: "Calculated", color: "info" },
  reviewed: { label: "Reviewed", color: "primary" },
  approved: { label: "Approved", color: "warning" },
  finalized: { label: "Finalized", color: "success" },
  cancelled: { label: "Cancelled", color: "error" },
};

export const RUN_TYPE = {
  regular: { label: "Regular", color: "primary" },
  supplemental: { label: "Supplemental", color: "info" },
  reversal: { label: "Reversal", color: "error" },
};

export const RULE_STATUS = {
  draft: { label: "Draft (unverified)", color: "warning" },
  verified: { label: "Verified", color: "success" },
  superseded: { label: "Superseded", color: "grey" },
  rejected: { label: "Rejected", color: "error" },
};

export const INPUT_STATUS = {
  pending: { label: "Pending", color: "warning" },
  approved: { label: "Approved", color: "success" },
  applied: { label: "Applied", color: "grey" },
};

export const COMPENSATION_STATUS = {
  pending: { label: "Pending approval", color: "warning" },
  approved: { label: "Approved", color: "success" },
};

export const DISBURSEMENT_STATUS = {
  pending: { label: "Pending", color: "grey" },
  exported: { label: "Exported", color: "info" },
  paid: { label: "Paid", color: "success" },
  failed: { label: "Failed", color: "error" },
};

export const LINE_CLASS = {
  earning: "Earning",
  employee_deduction: "Employee deduction",
  employee_tax: "Employee tax",
  employer_contribution: "Employer contribution",
  informational: "Informational",
};

export const FREQUENCY = {
  weekly: "Weekly",
  biweekly: "Bi-weekly",
  semi_monthly: "Semi-monthly",
  monthly: "Monthly",
};

export function statusInfo(map, status) {
  return map[status] || { label: status, color: "grey" };
}

export function label(value) {
  return value ? String(value).replace(/_/g, " ").replace(/^./, (c) => c.toUpperCase()) : "—";
}

export function employeeName(employee) {
  return employee ? `${employee.emp_last_name}, ${employee.emp_first_name}` : "—";
}

// Dates arrive as "YYYY-MM-DD"; parse as local dates so they never shift a day.
export function formatDate(value, locale) {
  return value ? new Date(`${String(value).substring(0, 10)}T00:00:00`).toLocaleDateString(locale || undefined) : "—";
}

export function formatDateTime(value, locale) {
  return value ? new Date(value).toLocaleString(locale || undefined) : "—";
}
