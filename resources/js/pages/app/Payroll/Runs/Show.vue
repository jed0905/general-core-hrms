<template>
  <SidebarLayout>
    <Head :title="`Payroll ${run.run_number}`" />
    <v-container fluid class="pa-6">
      <div class="d-flex align-center justify-space-between mb-4 flex-wrap ga-2">
        <div>
          <a :href="route('payroll.runs.index')" class="text-caption text-primary text-decoration-none" @click.prevent="go(route('payroll.runs.index'))">← Payroll runs</a>
          <h1 class="text-h5 font-weight-bold">
            {{ run.run_number }}
            <v-chip size="small" variant="tonal" class="ml-2" :color="statusInfo(typeMap, run.run_type).color">{{ statusInfo(typeMap, run.run_type).label }}</v-chip>
            <v-chip size="small" variant="tonal" class="ml-1" :color="statusInfo(statusMap, run.status).color">{{ statusInfo(statusMap, run.status).label }}</v-chip>
          </h1>
          <p class="text-body-2 text-medium-emphasis">
            {{ run.legal_entity.name }} ({{ run.legal_entity.country_code }}) · {{ run.pay_group.name }} ({{ frequencyLabel }}, payroll country {{ run.pay_group.payroll_country_code }}) ·
            {{ date(run.period.period_start) }} – {{ date(run.period.period_end) }} · pay date {{ date(run.period.pay_date) }} · {{ run.currency }}
          </p>
          <p v-if="run.reverses_run" class="text-body-2">Reverses
            <a :href="route('payroll.runs.show', run.reverses_run.id)" class="text-primary" @click.prevent="go(route('payroll.runs.show', run.reverses_run.id))">{{ run.reverses_run.run_number }}</a>
          </p>
          <p v-if="run.reversal" class="text-body-2">Reversed by
            <a :href="route('payroll.runs.show', run.reversal.id)" class="text-primary" @click.prevent="go(route('payroll.runs.show', run.reversal.id))">{{ run.reversal.run_number }}</a> ({{ run.reversal.status }})
          </p>
        </div>
        <div class="d-flex flex-wrap ga-2">
          <v-btn v-if="can.calculate" color="primary" prepend-icon="mdi-calculator" :loading="busy === 'calculate'" @click="act('calculate')">{{ run.status === 'draft' ? 'Calculate' : 'Recalculate' }}</v-btn>
          <v-btn v-if="can.review" color="primary" variant="tonal" prepend-icon="mdi-eye-check" @click="openAction('review')">Mark reviewed</v-btn>
          <v-btn v-if="can.approve" color="warning" variant="tonal" prepend-icon="mdi-check-decagram" @click="openAction('approve')">Approve</v-btn>
          <v-btn v-if="can.finalize" color="success" prepend-icon="mdi-lock-check" @click="openAction('finalize')">Finalize</v-btn>
          <v-btn v-if="can.returnToDraft" variant="tonal" prepend-icon="mdi-undo" @click="openAction('return')">Return to draft</v-btn>
          <v-btn v-if="can.cancel" color="error" variant="text" prepend-icon="mdi-cancel" @click="openAction('cancel')">Cancel run</v-btn>
          <v-btn v-if="can.reverse" color="error" variant="tonal" prepend-icon="mdi-swap-horizontal" @click="openAction('reverse')">Reverse</v-btn>
          <v-btn v-if="can.export" variant="outlined" prepend-icon="mdi-download" :href="route('payroll.runs.export', run.id)">Register CSV</v-btn>
        </div>
      </div>

      <v-alert v-if="run.uses_unverified_rules" type="warning" variant="tonal" class="mb-4" density="compact">
        This run used statutory rule versions that are not verified. It is a preview: it cannot be finalized until those versions are verified and the run is recalculated.
      </v-alert>
      <v-alert v-if="errorCount" type="error" variant="tonal" class="mb-4" density="compact">
        {{ errorCount }} calculation error(s) must be resolved and the run recalculated before review.
      </v-alert>

      <v-row class="mb-2">
        <v-col v-for="item in totalCards" :key="item.key" cols="6" md="2">
          <v-card variant="outlined" class="rounded-lg pa-3">
            <div class="text-caption text-medium-emphasis">{{ item.label }}</div>
            <div class="text-subtitle-1 font-weight-bold text-no-wrap">{{ money(run.totals[item.key], run.currency, run.legal_entity.locale) }}</div>
          </v-card>
        </v-col>
      </v-row>

      <v-tabs v-model="tab" color="primary" class="mb-2">
        <v-tab value="payslips">Payslips ({{ run.employee_count }})</v-tab>
        <v-tab value="exceptions">Exceptions ({{ exceptions.length }})</v-tab>
        <v-tab value="audit">Audit trail</v-tab>
        <v-tab v-if="disbursements" value="payments">Payments</v-tab>
      </v-tabs>

      <v-window v-model="tab">
        <v-window-item value="payslips">
          <v-card variant="outlined" class="rounded-lg">
            <div v-if="can.manageEmployees" class="d-flex ga-2 pa-3 align-center flex-wrap">
              <v-autocomplete v-model="exclude.employment_assignment_id" :items="excludable" item-title="label" item-value="id" label="Exclude an employee" variant="outlined" density="compact" hide-details style="max-width: 360px" />
              <v-text-field v-model="exclude.reason" label="Reason" variant="outlined" density="compact" hide-details style="max-width: 320px" />
              <v-btn variant="tonal" :disabled="!exclude.employment_assignment_id || !exclude.reason" :loading="exclude.processing" @click="excludeEmployee">Exclude</v-btn>
            </div>
            <v-table density="comfortable">
              <thead>
                <tr>
                  <th>Employee</th>
                  <th>Payslip</th>
                  <th class="text-end">Gross</th>
                  <th class="text-end">Deductions &amp; tax</th>
                  <th class="text-end">Net pay</th>
                  <th class="text-end">Employer cost</th>
                  <th class="text-end"></th>
                </tr>
              </thead>
              <tbody>
                <tr v-if="!payslips.data.length"><td colspan="7" class="text-center text-medium-emphasis pa-6">Not calculated yet.</td></tr>
                <tr v-for="p in payslips.data" :key="p.id" :class="{ 'text-medium-emphasis': p.status === 'excluded' }">
                  <td>
                    {{ employeeName(p.employee) }}
                    <div class="text-caption text-medium-emphasis">{{ p.employee?.employee_number }}</div>
                  </td>
                  <td>
                    <span v-if="p.status === 'excluded'">Excluded: {{ p.exclusion_reason }}</span>
                    <span v-else>{{ p.payslip_number || '—' }}</span>
                  </td>
                  <td class="text-end">{{ p.status === 'excluded' ? '' : money(p.gross_pay, run.currency, run.legal_entity.locale) }}</td>
                  <td class="text-end">{{ p.status === 'excluded' ? '' : money(p.deductions, run.currency, run.legal_entity.locale) }}</td>
                  <td class="text-end font-weight-medium">{{ p.status === 'excluded' ? '' : money(p.net_pay, run.currency, run.legal_entity.locale) }}</td>
                  <td class="text-end">{{ p.status === 'excluded' ? '' : money(p.employer_cost, run.currency, run.legal_entity.locale) }}</td>
                  <td class="text-end text-no-wrap">
                    <v-btn v-if="p.status === 'excluded' && can.manageEmployees" size="small" variant="text" @click="includeEmployee(p)">Include</v-btn>
                    <v-btn v-else-if="can.viewPayslips" size="small" variant="text" :href="route('payroll.payslips.show', p.id)" @click.prevent="go(route('payroll.payslips.show', p.id))">View</v-btn>
                  </td>
                </tr>
              </tbody>
            </v-table>
            <div class="pa-4"><Pagination :meta="payslips" /></div>
          </v-card>
        </v-window-item>

        <v-window-item value="exceptions">
          <v-card variant="outlined" class="rounded-lg">
            <v-table density="compact">
              <thead><tr><th>Severity</th><th>Employee</th><th>Code</th><th>Message</th></tr></thead>
              <tbody>
                <tr v-if="!exceptions.length"><td colspan="4" class="text-center text-medium-emphasis pa-6">No exceptions.</td></tr>
                <tr v-for="e in exceptions" :key="e.id">
                  <td><v-chip size="x-small" variant="tonal" :color="e.severity === 'error' ? 'error' : 'warning'">{{ e.severity }}</v-chip></td>
                  <td>{{ e.employee ? employeeName(e.employee) : 'Run' }}</td>
                  <td class="text-caption">{{ e.code }}</td>
                  <td>{{ e.message }}</td>
                </tr>
              </tbody>
            </v-table>
          </v-card>
        </v-window-item>

        <v-window-item value="audit">
          <v-card variant="outlined" class="rounded-lg">
            <v-table density="compact">
              <thead><tr><th>When</th><th>Action</th><th>Status</th><th>By</th><th>Remarks</th></tr></thead>
              <tbody>
                <tr v-for="ev in run.events" :key="ev.id">
                  <td class="text-no-wrap">{{ dateTime(ev.occurred_at) }}</td>
                  <td>{{ label(ev.event) }}</td>
                  <td class="text-caption">{{ ev.from_status || '—' }} → {{ ev.to_status || '—' }}</td>
                  <td>{{ ev.actor?.username || '—' }}</td>
                  <td>{{ ev.remarks }}</td>
                </tr>
              </tbody>
            </v-table>
            <div class="pa-3 text-caption text-medium-emphasis">Engine version {{ run.engine_version || '—' }}</div>
          </v-card>
        </v-window-item>

        <v-window-item v-if="disbursements" value="payments">
          <v-card variant="outlined" class="rounded-lg">
            <div class="d-flex ga-2 pa-3 flex-wrap align-center">
              <v-btn variant="outlined" prepend-icon="mdi-file-delimited" :href="route('payroll.runs.disbursements.csv', run.id)">Payment CSV</v-btn>
              <v-btn variant="outlined" prepend-icon="mdi-bank-transfer" @click="painDialog = true">ISO 20022 file</v-btn>
              <span class="text-caption text-medium-emphasis">Files only: no bank is contacted. Record outcomes below.</span>
            </div>
            <v-table density="compact">
              <thead><tr><th>Employee</th><th>Method</th><th>Account</th><th class="text-end">Amount</th><th>Status</th><th>Reference</th><th></th></tr></thead>
              <tbody>
                <tr v-if="!disbursements.length"><td colspan="7" class="text-center text-medium-emphasis pa-6">No payments for this run.</td></tr>
                <tr v-for="d in disbursements" :key="d.id">
                  <td>{{ employeeName(d.employee) }}</td>
                  <td>{{ label(d.method) }}</td>
                  <td class="text-caption">{{ d.account || '—' }}</td>
                  <td class="text-end">{{ money(d.amount, d.currency, run.legal_entity.locale) }}</td>
                  <td><v-chip size="x-small" variant="tonal" :color="statusInfo(disbursementMap, d.status).color">{{ statusInfo(disbursementMap, d.status).label }}</v-chip>
                    <div v-if="d.failure_reason" class="text-caption text-error">{{ d.failure_reason }}</div></td>
                  <td class="text-caption">{{ d.reference }}</td>
                  <td class="text-end text-no-wrap">
                    <v-btn v-if="d.status !== 'paid'" size="small" variant="text" @click="openPayment(d)">Update</v-btn>
                  </td>
                </tr>
              </tbody>
            </v-table>
          </v-card>
        </v-window-item>
      </v-window>

      <v-dialog v-model="actionDialog.show" max-width="520">
        <v-card class="rounded-lg">
          <v-card-title class="text-h6 pa-4">{{ actionTitles[actionDialog.action] }}</v-card-title>
          <v-card-text>
            <p v-if="actionDialog.action === 'finalize'" class="mb-3">Finalizing locks every payslip permanently, numbers the payslips, marks adjustments applied and opens payments. Corrections afterwards need a reversal or a supplemental run.</p>
            <p v-if="actionDialog.action === 'reverse'" class="mb-3">A reversal run negating every line of this run will be created for review, approval and finalization. This run itself never changes.</p>
            <v-textarea v-model="actionDialog.remarks" :label="remarksRequired ? 'Reason (required)' : 'Remarks (optional)'" variant="outlined" rows="3" :error-messages="actionDialog.error" />
          </v-card-text>
          <v-card-actions class="pa-4">
            <v-spacer />
            <v-btn variant="text" @click="actionDialog.show = false">Close</v-btn>
            <v-btn color="primary" variant="flat" :loading="busy === actionDialog.action" :disabled="remarksRequired && !actionDialog.remarks" @click="act(actionDialog.action, actionDialog.remarks)">Confirm</v-btn>
          </v-card-actions>
        </v-card>
      </v-dialog>

      <v-dialog v-model="paymentForm.show" max-width="480">
        <v-card class="rounded-lg">
          <v-card-title class="text-h6 pa-4">Payment status</v-card-title>
          <v-card-text>
            <v-select v-model="paymentForm.status" :items="['pending', 'exported', 'paid', 'failed']" label="Status" variant="outlined" density="compact" class="mb-3" />
            <v-select v-model="paymentForm.method" :items="['bank_transfer', 'cash', 'check', 'other']" label="Method" variant="outlined" density="compact" class="mb-3" />
            <v-text-field v-model="paymentForm.reference" label="Bank / payment reference" variant="outlined" density="compact" class="mb-3" />
            <v-text-field v-if="paymentForm.status === 'failed'" v-model="paymentForm.failure_reason" label="Failure reason" variant="outlined" density="compact" />
          </v-card-text>
          <v-card-actions class="pa-4">
            <v-spacer />
            <v-btn variant="text" @click="paymentForm.show = false">Close</v-btn>
            <v-btn color="primary" variant="flat" @click="savePayment">Save</v-btn>
          </v-card-actions>
        </v-card>
      </v-dialog>

      <v-dialog v-model="painDialog" max-width="480">
        <v-card class="rounded-lg">
          <v-card-title class="text-h6 pa-4">ISO 20022 credit transfer (pain.001)</v-card-title>
          <v-card-text>
            <p class="text-body-2 mb-3">The employer's paying account is used for this file only and is not stored. Pending bank transfers are marked exported.</p>
            <form ref="painForm" method="post" :action="route('payroll.runs.disbursements.pain001', run.id)">
              <input type="hidden" name="_token" :value="$page.props.csrf_token" />
              <v-text-field v-model="pain.iban" name="iban" label="Debtor IBAN" variant="outlined" density="compact" class="mb-2" />
              <v-text-field v-model="pain.account_number" name="account_number" label="or debtor account number" variant="outlined" density="compact" class="mb-2" />
              <v-text-field v-model="pain.bic" name="bic" label="Debtor bank BIC" variant="outlined" density="compact" />
            </form>
          </v-card-text>
          <v-card-actions class="pa-4">
            <v-spacer />
            <v-btn variant="text" @click="painDialog = false">Close</v-btn>
            <v-btn color="primary" variant="flat" :disabled="!pain.iban && !pain.account_number" @click="submitPain">Download</v-btn>
          </v-card-actions>
        </v-card>
      </v-dialog>
    </v-container>
  </SidebarLayout>
</template>

<script>
import { Head, router, useForm } from "@inertiajs/vue3";
import SidebarLayout from "@/layouts/SidebarLayout.vue";
import Pagination from "@/components/Pagination.vue";
import payrollPage from "@/mixins/payrollPage";
import { RUN_STATUS, RUN_TYPE, DISBURSEMENT_STATUS, FREQUENCY } from "@/utils/payroll";

const ROUTES = {
  calculate: "payroll.runs.calculate",
  review: "payroll.runs.review",
  approve: "payroll.runs.approve",
  finalize: "payroll.runs.finalize",
  return: "payroll.runs.return",
  cancel: "payroll.runs.cancel",
};

export default {
  name: "PayrollRunShow",
  components: { SidebarLayout, Head, Pagination },
  mixins: [payrollPage],
  props: {
    run: { type: Object, required: true },
    payslips: { type: Object, required: true },
    exceptions: { type: Array, default: () => [] },
    excludable: { type: Array, default: () => [] },
    disbursements: { type: Array, default: null },
    can: { type: Object, default: () => ({}) },
  },
  data() {
    return {
      tab: "payslips",
      busy: null,
      statusMap: RUN_STATUS,
      typeMap: RUN_TYPE,
      disbursementMap: DISBURSEMENT_STATUS,
      actionDialog: { show: false, action: null, remarks: "", error: null },
      actionTitles: { review: "Mark as reviewed", approve: "Approve run", finalize: "Finalize run", return: "Return to draft", cancel: "Cancel run", reverse: "Reverse run" },
      exclude: useForm({ employment_assignment_id: null, reason: "" }),
      paymentForm: { show: false, id: null, status: "paid", method: null, reference: "", failure_reason: "" },
      painDialog: false,
      pain: { iban: "", account_number: "", bic: "" },
      totalCards: [
        { key: "gross_total", label: "Gross" },
        { key: "employee_deduction_total", label: "Employee deductions" },
        { key: "tax_total", label: "Tax" },
        { key: "net_total", label: "Net pay" },
        { key: "employer_contribution_total", label: "Employer contributions" },
        { key: "employer_cost_total", label: "Employer cost" },
      ],
    };
  },
  computed: {
    errorCount() {
      return this.exceptions.filter((e) => e.severity === "error").length;
    },
    remarksRequired() {
      return ["return", "cancel", "reverse"].includes(this.actionDialog.action);
    },
    frequencyLabel() {
      return FREQUENCY[this.run.pay_group.pay_frequency] || this.run.pay_group.pay_frequency;
    },
  },
  methods: {
    openAction(action) {
      this.actionDialog = { show: true, action, remarks: "", error: null };
    },
    act(action, remarks = null) {
      this.busy = action;
      const options = {
        preserveScroll: true,
        onSuccess: () => (this.actionDialog.show = false),
        onError: (errors) => {
          this.actionDialog.error = errors.remarks || null;
          const message = this.firstError(errors);
          if (message) this.showToast(message, "error");
        },
        onFinish: () => (this.busy = null),
      };
      if (action === "reverse") {
        router.post(route("payroll.runs.reverse", this.run.id), { remarks }, options);
      } else {
        router.put(route(ROUTES[action], this.run.id), { remarks }, options);
      }
    },
    excludeEmployee() {
      this.exclude.put(route("payroll.runs.exclude", this.run.id), {
        preserveScroll: true,
        onSuccess: () => this.exclude.reset(),
        onError: (errors) => this.showToast(this.firstError(errors), "error"),
      });
    },
    includeEmployee(payslip) {
      router.put(route("payroll.runs.include", [this.run.id, payslip.employment_assignment_id]), {}, { preserveScroll: true });
    },
    openPayment(d) {
      this.paymentForm = { show: true, id: d.id, status: d.status === "pending" ? "paid" : d.status, method: d.method, reference: d.reference || "", failure_reason: d.failure_reason || "" };
    },
    savePayment() {
      const { id, show, ...data } = this.paymentForm;
      router.put(route("payroll.runs.disbursements.update", [this.run.id, id]), data, {
        preserveScroll: true,
        onSuccess: () => (this.paymentForm.show = false),
        onError: (errors) => this.showToast(this.firstError(errors), "error"),
      });
    },
    submitPain() {
      this.$refs.painForm.submit();
      this.painDialog = false;
    },
  },
};
</script>
