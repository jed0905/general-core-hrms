<template>
  <SidebarLayout>
    <Head title="Payroll Inputs" />
    <v-container fluid class="pa-6">
      <div class="mb-4">
        <h1 class="text-h5 font-weight-bold">Payroll Inputs</h1>
        <p class="text-body-2 text-medium-emphasis">Time quantities and one-time adjustments per period. Only approved inputs are paid. Approved leave comes from the Leave module automatically.</p>
      </div>

      <v-card variant="outlined" class="rounded-lg mb-4 pa-4">
        <v-autocomplete v-model="selectedPeriod" :items="periodItems" label="Pay group and period" variant="outlined" density="compact" hide-details @update:model-value="selectPeriod" />
      </v-card>

      <template v-if="period && data">
        <v-tabs v-model="tab" color="primary" class="mb-3">
          <v-tab value="time">Time ({{ data.timeInputs.length }})</v-tab>
          <v-tab value="adjustments">Adjustments ({{ data.adjustments.length }})</v-tab>
        </v-tabs>

        <v-window v-model="tab">
          <v-window-item value="time">
            <v-card variant="outlined" class="rounded-lg">
              <div v-if="can.manage" class="pa-3">
                <div class="d-flex ga-2 flex-wrap align-center">
                  <v-autocomplete v-model="timeForm.employee_id" :items="employeeItems" label="Employee" variant="outlined" density="compact" hide-details style="min-width: 260px" />
                  <v-select v-model="timeForm.category" :items="categoryItems" label="Category" variant="outlined" density="compact" hide-details style="min-width: 220px" />
                  <v-text-field v-model="timeForm.quantity" label="Days / hours" variant="outlined" density="compact" hide-details style="max-width: 140px" />
                  <v-btn color="primary" variant="tonal" :loading="timeForm.processing" @click="saveTime">Save</v-btn>
                  <v-spacer />
                  <v-file-input v-model="importFile" label="Import CSV (employee_number,category,quantity)" accept=".csv,.txt" variant="outlined" density="compact" hide-details style="max-width: 380px" />
                  <v-btn variant="outlined" :disabled="!importFile" @click="importCsv">Import</v-btn>
                </div>
                <div v-if="firstError(timeForm.errors)" class="text-error text-caption mt-1">{{ firstError(timeForm.errors) }}</div>
              </div>
              <div v-if="can.approve && selected.length" class="px-3 pb-2"><v-btn color="success" variant="tonal" size="small" @click="approveTime">Approve {{ selected.length }} selected</v-btn></div>
              <v-table density="compact">
                <thead><tr><th v-if="can.approve" style="width: 40px"></th><th>Employee</th><th>Category</th><th class="text-end">Quantity</th><th>Source</th><th>Status</th><th></th></tr></thead>
                <tbody>
                  <tr v-if="!data.timeInputs.length"><td colspan="7" class="text-center text-medium-emphasis pa-6">No time inputs for this period.</td></tr>
                  <tr v-for="t in data.timeInputs" :key="t.id">
                    <td v-if="can.approve"><v-checkbox-btn v-if="t.status === 'pending'" v-model="selected" :value="t.id" density="compact" /></td>
                    <td>{{ employeeName(t.employee) }}</td>
                    <td>{{ categories[t.category] || t.category }}</td>
                    <td class="text-end">{{ Number(t.quantity) }}</td>
                    <td>{{ t.source }}</td>
                    <td><v-chip size="x-small" variant="tonal" :color="statusInfo(statusMap, t.status).color">{{ statusInfo(statusMap, t.status).label }}</v-chip></td>
                    <td class="text-end"><v-btn v-if="can.manage" size="small" variant="text" color="error" icon="mdi-delete-outline" @click="remove(route('payroll.inputs.time.destroy', t.id))" /></td>
                  </tr>
                </tbody>
              </v-table>
            </v-card>
          </v-window-item>

          <v-window-item value="adjustments">
            <v-card variant="outlined" class="rounded-lg">
              <div v-if="can.manage" class="pa-3">
                <div class="d-flex ga-2 flex-wrap">
                  <v-autocomplete v-model="adjustmentForm.employee_id" :items="employeeItems" label="Employee" variant="outlined" density="compact" hide-details style="min-width: 240px" />
                  <v-select v-model="adjustmentForm.pay_component_id" :items="components" :item-title="(c) => `${c.name} (${label(c.line_class)})`" item-value="id" label="Component" variant="outlined" density="compact" hide-details style="min-width: 240px" />
                  <v-text-field v-model="adjustmentForm.amount" label="Amount" variant="outlined" density="compact" hide-details style="max-width: 140px" />
                  <v-text-field v-model="adjustmentForm.currency" label="Currency" variant="outlined" density="compact" hide-details style="max-width: 100px" />
                  <v-text-field v-model="adjustmentForm.remarks" label="Reason" variant="outlined" density="compact" hide-details />
                  <v-btn color="primary" variant="tonal" :loading="adjustmentForm.processing" @click="saveAdjustment">Save</v-btn>
                </div>
                <div v-if="firstError(adjustmentForm.errors)" class="text-error text-caption mt-1">{{ firstError(adjustmentForm.errors) }}</div>
              </div>
              <v-table density="compact">
                <thead><tr><th>Employee</th><th>Component</th><th class="text-end">Amount</th><th>Reason</th><th>Status</th><th></th></tr></thead>
                <tbody>
                  <tr v-if="!data.adjustments.length"><td colspan="6" class="text-center text-medium-emphasis pa-6">No adjustments for this period.</td></tr>
                  <tr v-for="a in data.adjustments" :key="a.id">
                    <td>{{ employeeName(a.employee) }}</td>
                    <td>{{ a.pay_component?.name }}</td>
                    <td class="text-end">{{ money(a.amount, a.currency) }}</td>
                    <td>{{ a.remarks }}</td>
                    <td><v-chip size="x-small" variant="tonal" :color="statusInfo(statusMap, a.status).color">{{ statusInfo(statusMap, a.status).label }}</v-chip></td>
                    <td class="text-end text-no-wrap">
                      <v-btn v-if="can.approve && a.status === 'pending'" size="small" variant="text" color="success" @click="approveAdjustment(a)">Approve</v-btn>
                      <v-btn v-if="can.manage && a.status !== 'applied'" size="small" variant="text" color="error" icon="mdi-delete-outline" @click="remove(route('payroll.inputs.adjustments.destroy', a.id))" />
                    </td>
                  </tr>
                </tbody>
              </v-table>
            </v-card>
          </v-window-item>
        </v-window>
      </template>
    </v-container>
  </SidebarLayout>
</template>

<script>
import { Head, router, useForm } from "@inertiajs/vue3";
import SidebarLayout from "@/layouts/SidebarLayout.vue";
import payrollPage from "@/mixins/payrollPage";
import { INPUT_STATUS } from "@/utils/payroll";

export default {
  name: "PayrollInputIndex",
  components: { SidebarLayout, Head },
  mixins: [payrollPage],
  props: {
    payGroups: { type: Array, default: () => [] },
    periods: { type: Array, default: () => [] },
    period: { type: Object, default: null },
    data: { type: Object, default: null },
    categories: { type: Object, default: () => ({}) },
    components: { type: Array, default: () => [] },
    can: { type: Object, default: () => ({}) },
  },
  data() {
    const group = this.payGroups.find((g) => g.id === this.period?.pay_group_id);
    return {
      tab: "time",
      statusMap: INPUT_STATUS,
      selectedPeriod: this.period?.id || null,
      selected: [],
      importFile: null,
      timeForm: useForm({ employee_id: null, category: null, quantity: "", remarks: "" }),
      adjustmentForm: useForm({ employee_id: null, pay_component_id: null, amount: "", currency: group?.pay_currency || "", remarks: "" }),
    };
  },
  computed: {
    periodItems() {
      return this.periods.map((p) => {
        const group = this.payGroups.find((g) => g.id === p.pay_group_id);
        return { value: p.id, title: `${group?.name} · ${this.date(p.period_start)} – ${this.date(p.period_end)} (pay ${this.date(p.pay_date)})` };
      });
    },
    employeeItems() {
      return (this.data?.employees || []).map((e) => ({ value: e.id, title: `${this.employeeName(e)} · ${e.employee_number}` }));
    },
    categoryItems() {
      return Object.entries(this.categories).map(([value, title]) => ({ value, title }));
    },
  },
  methods: {
    selectPeriod(id) {
      router.get(route("payroll.inputs.index"), { payroll_period_id: id }, { preserveScroll: true });
    },
    options(form) {
      return {
        preserveScroll: true,
        onSuccess: () => form && form.reset("quantity", "amount", "remarks"),
        onError: (errors) => this.showToast(this.firstError(errors), "error"),
      };
    },
    saveTime() {
      this.timeForm.post(route("payroll.inputs.time.store", this.period.id), this.options(this.timeForm));
    },
    importCsv() {
      router.post(route("payroll.inputs.time.import", this.period.id), { file: Array.isArray(this.importFile) ? this.importFile[0] : this.importFile }, { forceFormData: true, preserveScroll: true, onSuccess: () => (this.importFile = null) });
    },
    approveTime() {
      router.put(route("payroll.inputs.time.approve", this.period.id), { ids: this.selected }, { preserveScroll: true, onSuccess: () => (this.selected = []) });
    },
    saveAdjustment() {
      this.adjustmentForm.post(route("payroll.inputs.adjustments.store", this.period.id), this.options(this.adjustmentForm));
    },
    approveAdjustment(a) {
      router.put(route("payroll.inputs.adjustments.approve", a.id), {}, this.options(null));
    },
    remove(url) {
      router.delete(url, this.options(null));
    },
  },
};
</script>
