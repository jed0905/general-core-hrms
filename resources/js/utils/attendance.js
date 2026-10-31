// Attendance label/color maps. Keep in sync with the model constants
// (AttendanceDay::STATUSES, AttendanceEvent::SOURCES/TYPES, AttendanceDevice statuses, ...).
export const DAY_STATUS = {
  present: { label: "Present", color: "success" },
  late: { label: "Late", color: "warning" },
  incomplete: { label: "Missing punch", color: "error" },
  absent: { label: "Absent", color: "error" },
  rest_day: { label: "Rest day", color: "grey" },
  holiday: { label: "Holiday", color: "info" },
  on_leave: { label: "On leave", color: "primary" },
  no_schedule: { label: "No schedule", color: "grey" },
  excused: { label: "Excused", color: "info" },
};

export const DEVICE_STATUS = {
  online: { label: "Online", color: "success" },
  offline: { label: "Offline", color: "error" },
  error: { label: "Error", color: "warning" },
  unknown: { label: "Unknown", color: "grey" },
};

export const EVENT_STATUS = {
  accepted: { label: "Accepted", color: "success" },
  unmatched: { label: "Unmapped user", color: "warning" },
  invalid: { label: "Invalid time", color: "error" },
};

export const SOURCE = {
  device_pull: { label: "Device (pulled)", color: "primary" },
  device_push: { label: "Device (pushed)", color: "primary" },
  connector: { label: "Connector", color: "primary" },
  simulator: { label: "SIMULATED", color: "deep-purple" },
  manual: { label: "Manual entry", color: "warning" },
  import: { label: "CSV import", color: "info" },
  adjustment: { label: "Adjustment", color: "warning" },
};

export const ADJUSTMENT_STATUS = {
  pending: { label: "Pending", color: "warning" },
  approved: { label: "Approved", color: "success" },
  rejected: { label: "Rejected", color: "error" },
};

export const SYNC_STATUS = {
  running: { label: "Running", color: "info" },
  succeeded: { label: "Succeeded", color: "success" },
  partial: { label: "Partial", color: "warning" },
  failed: { label: "Failed", color: "error" },
};

export function minutes(value) {
  const total = Number(value || 0);
  if (!total) return "—";
  const h = Math.floor(total / 60);
  const m = total % 60;
  return h ? `${h}h ${String(m).padStart(2, "0")}m` : `${m}m`;
}

// Instants arrive in ISO 8601 (UTC); show them in the given timezone.
export function timeIn(value, timezone) {
  if (!value) return "—";
  return new Date(value).toLocaleTimeString(undefined, { hour: "2-digit", minute: "2-digit", timeZone: timezone || undefined });
}

export function dateTimeIn(value, timezone) {
  if (!value) return "—";
  return new Date(value).toLocaleString(undefined, { timeZone: timezone || undefined });
}
