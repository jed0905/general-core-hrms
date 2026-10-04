// Dates arrive as "YYYY-MM-DD"; parse as local dates so they never shift a day.
export function formatDate(value) {
  return value ? new Date(`${String(value).substring(0, 10)}T00:00:00`).toLocaleDateString(undefined, { year: "numeric", month: "long", day: "numeric" }) : "";
}

export function money(amount, currency) {
  return amount === null || amount === undefined ? "" : `${currency || ""} ${Number(amount).toLocaleString(undefined, { maximumFractionDigits: 0 })}`.trim();
}

export function fileSize(bytes) {
  if (!bytes) return "";
  return bytes < 1024 * 1024 ? `${Math.max(1, Math.round(bytes / 1024))} KB` : `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
}

// Candidate-facing status colours; the label is always shown too (never colour alone).
export const STATUS_COLORS = {
  submitted: "info",
  under_review: "info",
  interview: "primary",
  assessment: "primary",
  evaluation: "primary",
  offer_available: "success",
  offer_accepted: "success",
  withdrawn: "grey",
  closed: "grey",
};
