// Labels and theme colors for onboarding (one place for every page).
export const ONBOARDING_STATUS = {
  pending: { label: "Not started", color: "grey" },
  in_progress: { label: "In progress", color: "info" },
  completed: { label: "Completed", color: "success" },
  cancelled: { label: "Cancelled", color: "error" },
};

export const TASK_STATUS = {
  pending: { label: "To do", color: "grey" },
  in_progress: { label: "In progress", color: "info" },
  completed: { label: "Done", color: "success" },
  skipped: { label: "Skipped", color: "grey" },
  cancelled: { label: "Cancelled", color: "grey" },
};

export const CATEGORIES = {
  before_start: "Before start date",
  first_day: "First day",
  first_week: "First week",
  first_month: "First 30 days",
  other: "Other",
};

export const ASSIGNEE_TYPES = {
  employee: "The employee",
  hr: "HR",
  supervisor: "Supervisor",
  specific_employee: "Designated employee",
};

export const EVENT_LABELS = {
  created: "Onboarding created",
  task_created: "Task created",
  task_assigned: "Task assigned",
  task_started: "Task started",
  task_completed: "Task completed",
  task_verified: "Task verified",
  task_skipped: "Task skipped",
  task_cancelled: "Task cancelled",
  started: "Onboarding started",
  completed: "Onboarding completed",
  cancelled: "Onboarding cancelled",
};

export function statusInfo(map, status) {
  return map[status] || { label: status, color: "grey" };
}

export function personName(employee) {
  return employee ? `${employee.emp_first_name} ${employee.emp_last_name}` : "—";
}

// Dates arrive as "YYYY-MM-DD"; parse as local dates so they never shift a day.
export function formatDate(value) {
  return value ? new Date(`${String(value).substring(0, 10)}T00:00:00`).toLocaleDateString() : "—";
}

export function formatDateTime(value) {
  return value ? new Date(value).toLocaleString() : "—";
}

export function percent(done, total) {
  return total ? Math.round((done / total) * 100) : 0;
}

export function assigneeLabel(task) {
  if (task.assignee_type === "hr") return "HR";
  if (task.assignee_type === "employee") return "Employee";
  return `${ASSIGNEE_TYPES[task.assignee_type]}: ${personName(task.assignee)}`;
}
