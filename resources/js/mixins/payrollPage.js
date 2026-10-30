import { formatMoney } from "@/utils/money";
import { formatDate, formatDateTime, statusInfo, label, employeeName } from "@/utils/payroll";

/**
 * Shared helpers for payroll pages: display formatting (never money math) and
 * the success/error flash shared by HandleInertiaRequests, shown once per visit.
 */
export default {
  watch: {
    "$page.props.flash": {
      handler(flash) {
        if (flash?.success) this.showToast(flash.success, "success");
        if (flash?.error) this.showToast(flash.error, "error");
      },
      immediate: true,
    },
  },
  methods: {
    money: formatMoney,
    date: formatDate,
    dateTime: formatDateTime,
    statusInfo,
    label,
    employeeName,
    go(url) {
      this.$inertia.visit(url);
    },
    firstError(errors) {
      const values = Object.values(errors || {});
      return values.length ? values[0] : null;
    },
  },
};
