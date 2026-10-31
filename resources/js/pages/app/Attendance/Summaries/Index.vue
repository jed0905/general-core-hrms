<template>
  <SidebarLayout>
    <Head title="Attendance for Payroll" />
    <v-container fluid class="pa-6">
      <div class="mb-4">
        <h1 class="text-h5 font-weight-bold">Attendance for Payroll</h1>
        <p class="text-body-2 text-medium-emphasis">Payroll uses only approved summaries, for pay groups whose time source is attendance. Summaries used by a finalized payroll never change; later corrections are flagged.</p>
      </div>
      <v-card variant="outlined" class="rounded-lg mb-4 pa-4">
        <div class="d-flex ga-2 flex-wrap align-center">
          <v-autocomplete v-model="periodId" :items="periodItems" label="Payroll period" variant="outlined" density="compact" hide-details style="min-width: 420px" @update:model-value="select" />
          <v-btn v-if="can.generate && periodId" variant="outlined" prepend-icon="mdi-calculator" @click="generate">Generate / refresh</v-btn>
          <v-btn v-if="can.approve && selected.length" color="success" prepend-icon="mdi-check" @click="approve">Approve {{ selected.length }}</v-btn>
        </div>
      </v-card>

      <v-card v-if="summaries" variant="outlined" class="rounded-lg">
        <v-table density="compact">
          <thead><tr><th v-if="can.approve" style="width: 40px"></th><th>Employee</th><th>Quantities</th><th>Notes</th><th>Status</th></tr></thead>
          <tbody>
            <tr v-if="!summaries.data.length"><td colspan="5" class="text-center text-medium-emphasis pa-6">No summaries yet. Generate them.</td></tr>
            <tr v-for="s in summaries.data" :key="s.id">
              <td v-if="can.approve"><v-checkbox-btn v-if="s.status === 'draft'" v-model="selected" :value="s.id" density="compact" /></td>
              <td>{{ employeeName(s.employee) }}<div class="text-caption text-medium-emphasis">{{ s.employee?.employee_number }}</div></td>
              <td class="text-caption"><span v-for="(v, k) in s.quantities" :key="k" class="mr-3">{{ label(k) }}: <strong>{{ Number(v) }}</strong></span><span v-if="!Object.keys(s.quantities || {}).length">—</span></td>
              <td class="text-caption">
                <div v-if="s.details?.open_exceptions?.length" class="text-error">Missing punches on {{ s.details.open_exceptions.join(', ') }}</div>
                <div v-if="Object.keys(s.details?.unmapped_minutes || {}).length" class="text-warning">Minutes without a payroll category: {{ Object.entries(s.details.unmapped_minutes).map(([k, v]) => `${k} ${v}`).join('; ') }}</div>
                <div v-if="s.changed_after_payroll" class="text-warning">Attendance changed after payroll used this summary: settle the difference with a payroll adjustment.</div>
              </td>
              <td><v-chip size="x-small" variant="tonal" :color="s.consumed_run_id ? 'grey' : s.status === 'approved' ? 'success' : 'warning'">{{ s.consumed_run_id ? 'Used by payroll' : label(s.status) }}</v-chip>
                <div v-if="s.approver" class="text-caption text-medium-emphasis">{{ s.approver.username }}</div></td>
            </tr>
          </tbody>
        </v-table>
        <div class="pa-4"><Pagination :meta="summaries" /></div>
      </v-card>
    </v-container>
  </SidebarLayout>
</template>

<script>
import { Head, router } from "@inertiajs/vue3";
import SidebarLayout from "@/layouts/SidebarLayout.vue";
import Pagination from "@/components/Pagination.vue";
import attendancePage from "@/mixins/attendancePage";

export default {
  name: "AttendanceSummaries",
  components: { SidebarLayout, Head, Pagination },
  mixins: [attendancePage],
  props: {
    periods: { type: Array, default: () => [] },
    selectedPeriodId: { type: Number, default: null },
    summaries: { type: Object, default: null },
    can: { type: Object, default: () => ({}) },
  },
  data() {
    return { periodId: this.selectedPeriodId, selected: [] };
  },
  computed: {
    periodItems() {
      return this.periods.map((p) => ({ value: p.id, title: `${p.pay_group?.name} · ${this.date(p.period_start)} – ${this.date(p.period_end)} (pay ${this.date(p.pay_date)})` }));
    },
  },
  methods: {
    select() {
      router.get(route("attendance.summaries.index"), { payroll_period_id: this.periodId }, { preserveScroll: true });
    },
    generate() {
      router.post(route("attendance.summaries.generate"), { payroll_period_id: this.periodId }, { preserveScroll: true });
    },
    approve() {
      router.put(route("attendance.summaries.approve"), { ids: this.selected }, { preserveScroll: true, onSuccess: () => (this.selected = []), onError: (e) => this.showToast(this.firstError(e), "error") });
    },
  },
};
</script>
