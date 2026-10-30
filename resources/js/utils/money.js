// Display-only money formatting. Amounts arrive from the server as decimal strings
// already rounded to the currency's ISO 4217 minor units, always with their
// currency code. Never add, subtract or convert amounts in the browser.
export function formatMoney(amount, currency, locale) {
  if (amount === null || amount === undefined || amount === "") return "—";
  if (!currency) return String(amount);
  try {
    return new Intl.NumberFormat(locale || undefined, { style: "currency", currency }).format(Number(amount));
  } catch (e) {
    return `${currency} ${amount}`;
  }
}
