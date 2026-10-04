// Labels and theme colors for recruitment statuses (one place for every page).
export const REQUISITION_STATUS = {
  draft: { label: "Draft", color: "grey" },
  pending_approval: { label: "Pending approval", color: "warning" },
  approved: { label: "Approved", color: "success" },
  rejected: { label: "Rejected", color: "error" },
  cancelled: { label: "Cancelled", color: "grey" },
};

export const APPROVAL_STATUS = {
  pending: { label: "Pending", color: "warning" },
  approved: { label: "Approved", color: "success" },
  rejected: { label: "Rejected", color: "error" },
  skipped: { label: "Skipped", color: "grey" },
};

export const VACANCY_STATUS = {
  draft: { label: "Draft", color: "grey" },
  open: { label: "Open", color: "success" },
  on_hold: { label: "On hold", color: "warning" },
  closed: { label: "Closed", color: "info" },
  filled: { label: "Filled", color: "primary" },
  cancelled: { label: "Cancelled", color: "grey" },
};

export const REASON_LABELS = { new_position: "New position", replacement: "Replacement" };

export const APPROVER_TYPE_LABELS = {
  immediate_supervisor: "Requester's supervisor",
  higher_supervisor: "Supervisor's supervisor",
  specific_employee: "Designated approver",
};

export const VISIBILITY_LABELS = { internal: "Internal only", external: "External only", both: "Internal and external" };

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

export const APPLICATION_STATUS = {
  active: { label: "In progress", color: "info" },
  shortlisted: { label: "Shortlisted", color: "success" },
  rejected: { label: "Rejected", color: "error" },
  withdrawn: { label: "Withdrawn", color: "grey" },
};

export const STAGE_TYPE_LABELS = {
  applied: "Applied",
  screening: "Screening",
  shortlisted: "Shortlisted",
  interview: "Interview",
  assessment: "Assessment",
  evaluation: "Evaluation",
};

export const INTERVIEW_STATUS = {
  scheduled: { label: "Scheduled", color: "info" },
  completed: { label: "Completed", color: "success" },
  cancelled: { label: "Cancelled", color: "grey" },
};

export const INTERVIEW_MODES = {
  in_person: { label: "In person", icon: "mdi-account-group-outline" },
  video: { label: "Video call", icon: "mdi-video-outline" },
  phone: { label: "Phone", icon: "mdi-phone-outline" },
};

export const ASSESSMENT_STATUS = {
  scheduled: { label: "Scheduled", color: "info" },
  in_progress: { label: "In progress", color: "warning" },
  completed: { label: "Completed", color: "success" },
  cancelled: { label: "Cancelled", color: "grey" },
};

export const RESULT_TYPE_LABELS = { score: "Score", pass_fail: "Pass / fail" };

export const RECOMMENDATIONS = {
  recommend: { label: "Recommend", color: "success" },
  neutral: { label: "Neutral", color: "warning" },
  do_not_recommend: { label: "Do not recommend", color: "error" },
};

export function interviewModeLabel(mode) {
  return INTERVIEW_MODES[mode]?.label || mode;
}

// "Mon, Oct 5, 2026 · 10:00 AM – 11:00 AM"
export function interviewWhen(interview) {
  if (!interview?.starts_at) return "—";
  const start = new Date(interview.starts_at);
  const end = new Date(interview.ends_at);
  const day = start.toLocaleDateString(undefined, { weekday: "short", year: "numeric", month: "short", day: "numeric" });
  const time = (d) => d.toLocaleTimeString(undefined, { hour: "numeric", minute: "2-digit" });
  return `${day} · ${time(start)} – ${time(end)}`;
}

// Local "YYYY-MM-DD" and "HH:MM" parts of a timestamp, for form fields.
export function localDateParts(value) {
  const d = value ? new Date(value) : new Date();
  const pad = (n) => String(n).padStart(2, "0");
  return { date: `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`, time: `${pad(d.getHours())}:${pad(d.getMinutes())}` };
}

// Default 1–5 scale (labels mirror ApplicationEvaluation::RATINGS).
export const RATING_LABELS = { 1: "Poor", 2: "Needs Improvement", 3: "Meets Expectations", 4: "Very Good", 5: "Excellent" };

export function averageRating(scores) {
  if (!scores?.length) return null;
  return (scores.reduce((sum, s) => sum + s.rating, 0) / scores.length).toFixed(1);
}

export const HISTORY_ACTION_LABELS = {
  applied: "Applied",
  moved: "Moved",
  shortlisted: "Shortlisted",
  rejected: "Rejected",
  withdrawn: "Withdrawn",
};

export function applicantName(applicant) {
  return applicant ? [applicant.first_name, applicant.last_name].filter(Boolean).join(" ") : "—";
}

export function fileSize(bytes) {
  if (!bytes) return "0 KB";
  return bytes < 1024 * 1024 ? `${Math.max(1, Math.round(bytes / 1024))} KB` : `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
}

export const SELECTION_DECISIONS = {
  selected: { label: "Selected", color: "success" },
  not_selected: { label: "Not selected", color: "grey" },
};

export const OFFER_STATUS = {
  draft: { label: "Draft", color: "grey" },
  pending_approval: { label: "Pending approval", color: "warning" },
  approved: { label: "Approved", color: "info" },
  rejected: { label: "Rejected in approval", color: "error" },
  issued: { label: "Issued", color: "primary" },
  accepted: { label: "Accepted", color: "success" },
  declined: { label: "Candidate rejected", color: "error" },
  expired: { label: "Expired", color: "grey" },
  withdrawn: { label: "Withdrawn", color: "grey" },
};

export const OFFER_EVENT_LABELS = {
  created: "Draft created",
  updated: "Draft edited",
  submitted: "Submitted for approval",
  step_approved: "Approval step approved",
  approved: "Fully approved",
  approval_rejected: "Rejected in approval",
  issued: "Issued to candidate",
  candidate_accepted: "Candidate accepted",
  candidate_declined: "Candidate rejected the offer",
  expired: "Expired",
  withdrawn: "Withdrawn",
  converted: "Converted to employee",
};

export const SALARY_FREQUENCIES = {
  hourly: "per hour",
  daily: "per day",
  weekly: "per week",
  semi_monthly: "semi-monthly",
  monthly: "per month",
  annually: "per year",
};

export function money(amount, currency) {
  if (amount === null || amount === undefined || amount === "") return "—";
  return `${currency || ""} ${Number(amount).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`.trim();
}
