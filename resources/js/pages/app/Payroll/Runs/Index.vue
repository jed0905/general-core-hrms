<template>
  <SidebarLayout>
    <Head title="Payroll Runs" />
    <v-container fluid class="pa-6">
      <div class="d-flex align-center justify-space-between mb-4 flex-wrap ga-2">
        <div>
          <h1 class="text-h5 font-weight-bold">Payroll Runs</h1>
          <p class="text-body-2 text-medium-emphasis">Calculate, review, approve and finalize payroll per pay group and period.</p>
        </div>
        <v-btn v-if="can.create" color="primary" prepend-icon="mdi-plus" @click="dialog = true">New run</v-btn>
      </div>

      <v-card variant="outlined" class="rounded-lg mb-4 pa-4">
        <v-row density="compact">
          <v-col cols="12" md="3">
            <v-text-field v-model="form.search" label="Run number" prepend-inner-icon="mdi-magnify" variant="outlined" density="compact" hide-details clearable @keyup.enter="apply" @click:clear="clear('search')" />
          </v-col>
          <v-col cols="12" md="3">
            <v-select v-model="form.status" :items="statusItems" label="Status" variant="outlined" density="compact" hide-details clearable @update:model-value="apply" />
          </v-col>
          <v-col cols="12" md="3">
            <v-select v-model="form.run_type" :items="typeItems" label="Type" variant="outlined" density="compact" hide-details clearable @update:model-value="apply" />
          </v-col>
          <v-col cols="12" md="3">
            <v-select v-model="form.legal_entity_id" :items="entities" item-title="name" item-value="id" label="Legal entity" variant="outlined" density="compact" hide-details clearable @update:model-value="apply" />
          </v-col>
        </v-row>
      </v-card>

      <v-card variant="outlined" class="rounded-lg">
        <v-table density="comfortable">
          <thead>
            <tr>
              <th>Run</th>
              <th>Legal entity / pay group</th>
              <th>Period</th>
              <th>Type</th>
              <th>Status</th>
              <th class="text-end">Employees</th>
              <th class="text-end">Net pay</th>
              <th class="text-end">Employer cost</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="!runs.data.length">
              <td colspan="8" class="text-center text-medium-emphasis pa-6">No payroll runs found.</td>
            </tr>
            <tr v-for="run in runs.data" :key="run.id">
              <td class="text-no-wrap">
                <a :href="route('payroll.runs.show', run.id)" class="text-primary font-weight-medium text-decoration-none" @click.prevent="go(route('payroll.runs.show', run.id))">{{ run.run_number }}</a>
              </td>
              <td>
                {{ run.legal_entity?.name }} <span class="text-caption text-medium-emphasis">({{ run.legal_entity?.country_code }})</span>
                <div class="text-caption text-medium-emphasis">{{ run.pay_group?.name }}</div>
              </td>
              <td class="text-no-wrap">
                {{ date(run.period?.period_start) }} – {{ date(run.period?.period_end) }}
                <div class="text-caption text-medium-emphasis">Pay date {{ date(run.period?.pay_date) }}</div>
              </td>
              <td><v-chip size="small" variant="tonal" :color="statusInfo(typeMap, run.run_type).color">{{ statusInfo(typeMap, run.run_type).label }}</v-chip></td>
              <td><v-chip size="small" variant="tonal" :color="statusInfo(statusMap, run.status).color">{{ statusInfo(statusMap, run.status).label }}</v-chip></td>
              <td class="text-end">{{ run.employee_count }}</td>
              <td class="text-end text-no-wrap">{{ money(run.net_total, run.currency) }}</td>
              <td class="text-end text-no-wrap">{{ money(run.employer_cost_total, run.currency) }}</td>
            </tr>
          </tbody>
        </v-table>
        <div class="pa-4"><Pagination :meta="runs" /></div>
      </v-card>

      <v-dialog v-model="dialog" max-width="560">
        <v-card class="rounded-lg">
          <v-card-title class="text-h6 pa-4">New payroll run</v-card-title>
          <v-card-text>
            <v-select v-model="createForm.run_type" :items="[{ title: 'Regular', value: 'regular' }, { title: 'Supplemental (adjustments only)', value: 'supplemental' }]" label="Run type" variant="outlined" density="compact" :error-messages="createForm.errors.run_type" class="mb-3" />
            <v-autocomplete v-model="createForm.payroll_period_id" :items="periodItems" item-title="title" item-value="value" label="Pay group and period" variant="outlined" density="compact" :error-messages="createForm.errors.payroll_period_id" class="mb-3" />
            <v-text-field v-model="createForm.description" label="Description (optional)" variant="outlined" density="compact" :error-messages="createForm.errors.description" />
          </v-card-text>
          <v-card-actions class="pa-4">
            <v-spacer />
            <v-btn variant="text" @click="dialog = false">Cancel</v-btn>
            <v-btn color="primary" variant="flat" :loading="createForm.processing" @click="create">Create</v-btn>
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
import { RUN_STATUS, RUN_TYPE } from "@/utils/payroll";

export default {
  name: "PayrollRunIndex",
  components: { SidebarLayout, Head, Pagination },
  mixins: [payrollPage],
  props: {
    runs: { type: Object, required: true },
    filters: { type: Object, default: () => ({}) },
    statuses: { type: Array, default: () => [] },
    types: { type: Array, default: () => [] },
    entities: { type: Array, default: () => [] },
    openPeriods: { type: Array, default: () => [] },
    can: { type: Object, default: () => ({}) },
  },
  data() {
    return {
      dialog: false,
      statusMap: RUN_STATUS,
      typeMap: RUN_TYPE,
      form: {
        search: this.filters.search || "",
        status: this.filters.status || null,
        run_type: this.filters.run_type || null,
        legal_entity_id: this.filters.legal_entity_id ? Number(this.filters.legal_entity_id) : null,
      },
      createForm: useForm({ run_type: "regular", payroll_period_id: null, description: "" }),
    };
  },
  computed: {
    statusItems() {
      return this.statuses.map((s) => ({ title: RUN_STATUS[s]?.label || s, value: s }));
    },
    typeItems() {
      return this.types.map((t) => ({ title: RUN_TYPE[t]?.label || t, value: t }));
    },
    periodItems() {
      return this.openPeriods.map((p) => ({
        value: p.id,
        title: `${p.pay_group?.name} · ${this.date(p.period_start)} – ${this.date(p.period_end)} (pay ${this.date(p.pay_date)}, ${p.pay_group?.pay_currency})`,
      }));
    },
  },
  methods: {
    apply() {
      router.get(route("payroll.runs.index"), this.form, { preserveState: true, preserveScroll: true, replace: true });
    },
    clear(key) {
      this.form[key] = "";
      this.apply();
    },
    create() {
      this.createForm.post(route("payroll.runs.store"), { onSuccess: () => (this.dialog = false) });
    },
  },
};
</script>
