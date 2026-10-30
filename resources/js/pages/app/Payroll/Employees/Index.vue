<template>
  <SidebarLayout>
    <Head title="Payroll Employees" />
    <v-container fluid class="pa-6">
      <div class="mb-4">
        <h1 class="text-h5 font-weight-bold">Payroll Employees</h1>
        <p class="text-body-2 text-medium-emphasis">Assignments, payroll jurisdiction, compensation history, residency, statutory profile and bank accounts.</p>
      </div>
      <v-card variant="outlined" class="rounded-lg mb-4 pa-4">
        <v-row density="compact">
          <v-col cols="12" md="5">
            <v-text-field v-model="form.search" label="Search name or employee number" prepend-inner-icon="mdi-magnify" variant="outlined" density="compact" hide-details clearable @keyup.enter="apply" @click:clear="() => { form.search = ''; apply(); }" />
          </v-col>
          <v-col cols="12" md="4">
            <v-select v-model="form.pay_group_id" :items="payGroups" item-title="name" item-value="id" label="Pay group" variant="outlined" density="compact" hide-details clearable @update:model-value="apply" />
          </v-col>
          <v-col v-if="canSeeUnassigned" cols="12" md="3">
            <v-checkbox v-model="form.unassigned" label="Without payroll assignment" density="compact" hide-details @update:model-value="apply" />
          </v-col>
        </v-row>
      </v-card>
      <v-card variant="outlined" class="rounded-lg">
        <v-table density="comfortable">
          <thead><tr><th>Employee</th><th>Department</th><th>Current assignment</th><th></th></tr></thead>
          <tbody>
            <tr v-if="!employees.data.length"><td colspan="4" class="text-center text-medium-emphasis pa-6">No employees found.</td></tr>
            <tr v-for="e in employees.data" :key="e.id">
              <td>{{ employeeName(e) }}<div class="text-caption text-medium-emphasis">{{ e.employee_number }}</div></td>
              <td>{{ e.department?.name || '—' }}</td>
              <td>
                <div v-for="a in e.payroll_assignments" :key="a.id">{{ a.legal_entity?.name }} · {{ a.pay_group?.name }} <span class="text-caption text-medium-emphasis">({{ a.payroll_country_code }})</span></div>
                <span v-if="!e.payroll_assignments?.length" class="text-medium-emphasis">None</span>
              </td>
              <td class="text-end"><v-btn size="small" variant="text" :href="route('payroll.employees.show', e.id)" @click.prevent="go(route('payroll.employees.show', e.id))">Open</v-btn></td>
            </tr>
          </tbody>
        </v-table>
        <div class="pa-4"><Pagination :meta="employees" /></div>
      </v-card>
    </v-container>
  </SidebarLayout>
</template>

<script>
import { Head, router } from "@inertiajs/vue3";
import SidebarLayout from "@/layouts/SidebarLayout.vue";
import Pagination from "@/components/Pagination.vue";
import payrollPage from "@/mixins/payrollPage";

export default {
  name: "PayrollEmployeeIndex",
  components: { SidebarLayout, Head, Pagination },
  mixins: [payrollPage],
  props: {
    employees: { type: Object, required: true },
    filters: { type: Object, default: () => ({}) },
    payGroups: { type: Array, default: () => [] },
    canSeeUnassigned: { type: Boolean, default: false },
  },
  data() {
    return {
      form: {
        search: this.filters.search || "",
        pay_group_id: this.filters.pay_group_id ? Number(this.filters.pay_group_id) : null,
        unassigned: !!this.filters.unassigned,
      },
    };
  },
  methods: {
    apply() {
      router.get(route("payroll.employees.index"), { ...this.form, unassigned: this.form.unassigned ? 1 : null }, { preserveState: true, preserveScroll: true, replace: true });
    },
  },
};
</script>
