import payrollPage from "@/mixins/payrollPage";
import { minutes, timeIn, dateTimeIn } from "@/utils/attendance";

/** Payroll page helpers (flash toasts, dates, labels) plus attendance formatting. */
export default {
  mixins: [payrollPage],
  methods: {
    minutes,
    timeIn,
    dateTimeIn,
  },
};
