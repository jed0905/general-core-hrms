<template>
  <SidebarLayout>
    <Head title="Onboarding" />
    <v-container fluid class="pa-6">
      <div class="d-flex flex-wrap align-center justify-space-between ga-3 mb-4">
        <div>
          <h1 class="text-h5 font-weight-bold">Onboarding</h1>
          <p class="text-body-2 text-medium-emphasis">New employees' onboarding checklists. Active cases are shown by default.</p>
        </div>
        <v-btn v-if="can.create" color="primary" prepend-icon="mdi-plus" @click="go(route('people.onboarding.create'))">Start onboarding</v-btn>
      </div>

      <v-row class="mb-2">
        <v-col v-for="k in kpis" :key="k.key" cols="6" md="">
          <v-card variant="outlined" class="rounded-lg pa-4" :class="{ 'cursor-pointer': k.filter }" @click="k.filter && applyQuick(k.filter)">
            <div class="text-h5 font-weight-bold" :class="k.color ? `text-${k.color}` : ''">{{ stats[k.key] }}</div>
            <div class="text-caption text-medium-emphasis">{{ k.label }}</div>
          </v-card>
        </v-col>
      </v-row>

      <v-card variant="outlined" class="rounded-lg mb-4 pa-4">
        <v-row density="compact">
          <v-col cols="12" md="3"><v-text-field v-model="form.search" label="Search employee" prepend-inner-icon="mdi-magnify" variant="outlined" density="compact" hide-details clearable @keyup.enter="apply" @click:clear="clear('search')" /></v-col>
          <v-col cols="6" md="2"><v-select v-model="form.status" :items="statusItems" label="Status (active by default)" variant="outlined" density="compact" hide-details clearable @update:model-value="apply" /></v-col>
          <v-col cols="6" md="2"><v-select v-model="form.department_id" :items="departments" item-title="name" item-value="id" label="Department" variant="outlined" density="compact" hide-details clearable @update:model-value="apply" /></v-col>
          <v-col cols="6" md="2"><v-select v-model="form.supervisor_id" :items="supervisorItems" label="Supervisor" variant="outlined" density="compact" hide-details clearable @update:model-value="apply" /></v-col>
          <v-col cols="6" md="3"><v-select v-model="form.template_id" :items="templates" item-title="name" item-value="id" label="Template" variant="outlined" density="compact" hide-details clearable @update:model-value="apply" /></v-col>
          <v-col cols="6" md="3"><v-text-field v-model="form.start_from" type="date" label="Start date from" variant="outlined" density="compact" hide-details clearable @update:model-value="apply" /></v-col>
          <v-col cols="6" md="3"><v-text-field v-model="form.start_to" type="date" label="Start date to" variant="outlined" density="compact" hide-details clearable @update:model-value="apply" /></v-col>
          <v-col cols="12" md="3"><v-checkbox v-model="form.overdue" label="With overdue tasks" :true-value="1" :false-value="null" density="compact" hide-details @update:model-value="apply" /></v-col>
        </v-row>
      </v-card>

      <v-card variant="outlined" class="rounded-lg">
        <v-table density="comfortable">
          <thead>
            <tr><th>Employee</th><th>Department</th><th>Template</th><th>Start</th><th>Status</th><th style="min-width: 180px">Required tasks</th><th>Overdue</th><th class="text-end"></th></tr>
          </thead>
          <tbody>
            <tr v-if="!onboardings.data.length"><td colspan="8" class="text-center text-medium-emphasis pa-6">No onboarding found.</td></tr>
            <tr v-for="o in onboardings.data" :key="o.id">
              <td>{{ personName(o.employee) }}<div class="text-caption text-medium-emphasis">{{ o.employee?.employee_number }}</div></td>
              <td>{{ o.employee?.department?.name || "—" }}</td>
              <td>{{ o.template_name }}</td>
              <td class="text-no-wrap">{{ formatDate(o.start_date) }}</td>
              <td><v-chip size="small" variant="tonal" :color="status(o.status).color">{{ status(o.status).label }}</v-chip></td>
              <td>
                <v-progress-linear :model-value="percent(o.required_done, o.required_total)" color="success" height="8" rounded class="mb-1" />
                <span class="text-caption">{{ o.required_done }} / {{ o.required_total }} required · {{ o.tasks_done }} / {{ o.tasks_total }} all</span>
              </td>
              <td><v-chip v-if="o.overdue_count" size="small" color="error" variant="tonal">{{ o.overdue_count }}</v-chip><span v-else class="text-medium-emphasis">—</span></td>
              <td class="text-end"><v-btn icon="mdi-eye-outline" size="small" variant="text" @click="go(route('people.onboarding.show', o.id))" /></td>
            </tr>
          </tbody>
        </v-table>
        <div class="pa-3"><Pagination :meta="onboardings" /></div>
      </v-card>
    </v-container>
  </SidebarLayout>
</template>

<script>
import { Head, router } from "@inertiajs/vue3";
import SidebarLayout from "@/layouts/SidebarLayout.vue";
import Pagination from "@/components/Pagination.vue";
import { ONBOARDING_STATUS, statusInfo, personName, formatDate, percent } from "@/utils/onboarding";

export default {
  name: "OnboardingIndex",
  components: { SidebarLayout, Head, Pagination },
  props: {
    onboardings: { type: Object, required: true },
    filters: { type: Object, default: () => ({}) },
    stats: { type: Object, default: () => ({}) },
    statuses: { type: Array, default: () => [] },
    templates: { type: Array, default: () => [] },
    departments: { type: Array, default: () => [] },
    supervisors: { type: Array, default: () => [] },
    can: { type: Object, default: () => ({}) },
  },
  data() {
    const f = this.filters;
    return {
      form: {
        search: f.search || "",
        status: f.status || null,
        department_id: f.department_id ? Number(f.department_id) : null,
        supervisor_id: f.supervisor_id ? Number(f.supervisor_id) : null,
        template_id: f.template_id ? Number(f.template_id) : null,
        start_from: f.start_from || null,
        start_to: f.start_to || null,
        overdue: f.overdue ? 1 : null,
      },
      kpis: [
        { key: "pending", label: "Not started", filter: { status: "pending" } },
        { key: "in_progress", label: "In progress", filter: { status: "in_progress" } },
        { key: "completed_this_month", label: "Completed this month", filter: { status: "completed" } },
        { key: "overdue_tasks", label: "Overdue tasks", color: "error", filter: { overdue: 1 } },
        { key: "due_this_week", label: "Tasks due in 7 days" },
      ],
    };
  },
  computed: {
    statusItems() {
      return this.statuses.map((s) => ({ value: s, title: statusInfo(ONBOARDING_STATUS, s).label }));
    },
    supervisorItems() {
      return this.supervisors.map((s) => ({ value: s.id, title: personName(s) }));
    },
  },
  methods: {
    personName,
    formatDate,
    percent,
    status(value) {
      return statusInfo(ONBOARDING_STATUS, value);
    },
    apply() {
      router.get(route("people.onboarding.index"), this.form, { preserveState: true, preserveScroll: true, replace: true });
    },
    applyQuick(filter) {
      this.form = { ...this.form, status: null, overdue: null, ...filter };
      this.apply();
    },
    clear(key) {
      this.form[key] = "";
      this.apply();
    },
    go(url) {
      router.visit(url);
    },
  },
};
</script>
