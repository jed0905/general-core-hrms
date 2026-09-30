// Display helpers shared by the Employee Movement pages.
// Business rules live on the server; this only labels what it returns.

export const FIELD_LABELS = {
  department_id: "Department",
  job_title_id: "Job Title",
  employment_status_id: "Employment Status",
  location_id: "Location",
  supervisor_id: "Supervisor",
};

// Keys used in the movement snapshot ({ from: {...}, to: {...} }).
export const SNAPSHOT_KEYS = {
  department_id: "department",
  job_title_id: "job_title",
  employment_status_id: "employment_status",
  location_id: "location",
  supervisor_id: "supervisor",
  status: "status",
};

export const SNAPSHOT_LABELS = {
  department: "Department",
  job_title: "Job Title",
  employment_status: "Employment Status",
  location: "Location",
  supervisor: "Supervisor",
  status: "Record Status",
};

export const MOVEMENT_STATUS = {
  implemented: { label: "Effective", color: "success" },
  approved: { label: "Scheduled", color: "info" },
  cancelled: { label: "Cancelled", color: "grey" },
};

export function movementStatus(status) {
  return MOVEMENT_STATUS[status] ?? { label: status, color: "default" };
}

export function employeeName(employee) {
  if (!employee) return "";
  return `${employee.emp_last_name}, ${employee.emp_first_name}`;
}

export function formatDate(value, options = undefined) {
  if (!value) return "—";
  return new Date(`${String(value).substring(0, 10)}T00:00:00`).toLocaleDateString(undefined, options);
}

// "Junior Developer → Senior Developer" lines for the fields that changed.
export function changeSummary(movement) {
  const from = movement.snapshot?.from ?? {};
  const to = movement.snapshot?.to ?? {};
  return Object.keys(SNAPSHOT_LABELS)
    .filter((key) => key in to && (from[key] ?? null) !== (to[key] ?? null))
    .map((key) => ({
      label: SNAPSHOT_LABELS[key],
      from: from[key] ?? null,
      to: to[key] ?? null,
    }));
}
