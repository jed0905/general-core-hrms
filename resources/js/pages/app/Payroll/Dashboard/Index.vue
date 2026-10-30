<template>
  <SidebarLayout>
    <Head title="Payroll Dashboard" />
    <v-container fluid class="pa-6">
      <div class="mb-4">
        <h1 class="text-h5 font-weight-bold">Payroll</h1>
        <p class="text-body-2 text-medium-emphasis">Work waiting for you and recently finalized runs. Totals are shown per currency and never combined.</p>
      </div>

      <v-row class="mb-2">
        <v-col v-for="q in queueCards" :key="q.key" cols="6" md="3" lg="2">
          <v-card variant="outlined" class="rounded-lg pa-3" :href="q.href || undefined" @click.prevent="q.href && go(q.href)">
            <div class="d-flex align-center ga-2">
              <v-icon :icon="q.icon" :color="summary.queues[q.key] ? q.color : 'grey'" />
              <span class="text-h5 font-weight-bold">{{ summary.queues[q.key] }}</span>
            </div>
            <div class="text-caption text-medium-emphasis">{{ q.label }}</div>
          </v-card>
        </v-col>
      </v-row>

      <v-card variant="outlined" class="rounded-lg">
        <v-card-title class="text-subtitle-1 font-weight-bold">Recently finalized runs</v-card-title>
        <v-table density="comfortable">
          <thead>
            <tr><th>Run</th><th>Legal entity</th><th>Pay date</th><th class="text-end">Employees</th><th class="text-end">Gross</th><th class="text-end">Net pay</th><th class="text-end">Employer cost</th></tr>
          </thead>
          <tbody>
            <tr v-if="!summary.recentRuns.length"><td colspan="7" class="text-center text-medium-emphasis pa-6">No finalized runs yet.</td></tr>
            <tr v-for="run in summary.recentRuns" :key="run.id">
              <td><a :href="route('payroll.runs.show', run.id)" class="text-primary text-decoration-none" @click.prevent="go(route('payroll.runs.show', run.id))">{{ run.run_number }}</a>
                <span v-if="run.run_type !== 'regular'" class="text-caption text-medium-emphasis"> ({{ run.run_type }})</span></td>
              <td>{{ run.legal_entity?.name }} <span class="text-caption text-medium-emphasis">{{ run.legal_entity?.country_code }}</span></td>
              <td>{{ date(run.period?.pay_date) }}</td>
              <td class="text-end">{{ run.employee_count }}</td>
              <td class="text-end">{{ money(run.gross_total, run.currency) }}</td>
              <td class="text-end">{{ money(run.net_total, run.currency) }}</td>
              <td class="text-end">{{ money(run.employer_cost_total, run.currency) }}</td>
            </tr>
          </tbody>
        </v-table>
      </v-card>
    </v-container>
  </SidebarLayout>
</template>

<script>
import { Head } from "@inertiajs/vue3";
import SidebarLayout from "@/layouts/SidebarLayout.vue";
import payrollPage from "@/mixins/payrollPage";

export default {
  name: "PayrollDashboard",
  components: { SidebarLayout, Head },
  mixins: [payrollPage],
  props: {
    summary: { type: Object, required: true },
    can: { type: Object, default: () => ({}) },
  },
  computed: {
    queueCards() {
      const runs = this.can.runs ? route("payroll.runs.index") : null;
      const inputs = this.can.inputs ? route("payroll.inputs.index") : null;
      return [
        { key: "to_review", label: "Runs to review", icon: "mdi-eye-check", color: "info", href: runs && route("payroll.runs.index", { status: "calculated" }) },
        { key: "to_approve", label: "Runs to approve", icon: "mdi-check-decagram", color: "warning", href: runs && route("payroll.runs.index", { status: "reviewed" }) },
        { key: "to_finalize", label: "Runs to finalize", icon: "mdi-lock-check", color: "success", href: runs && route("payroll.runs.index", { status: "approved" }) },
        { key: "compensation_pending", label: "Salary changes pending", icon: "mdi-cash-clock", color: "warning", href: null },
        { key: "time_inputs_pending", label: "Time inputs pending", icon: "mdi-clock-alert-outline", color: "warning", href: inputs },
        { key: "adjustments_pending", label: "Adjustments pending", icon: "mdi-cash-plus", color: "warning", href: inputs },
        { key: "rules_to_verify", label: "Statutory rules to verify", icon: "mdi-scale-balance", color: "error", href: this.can.rules ? route("payroll.statutory-rules.index", { verification_status: "draft" }) : null },
      ];
    },
  },
};
</script>
