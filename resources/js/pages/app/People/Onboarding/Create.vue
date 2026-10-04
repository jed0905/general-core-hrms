<template>
  <SidebarLayout>
    <Head title="Start onboarding" />
    <v-container fluid class="pa-6" style="max-width: 860px">
      <v-btn variant="text" size="small" prepend-icon="mdi-arrow-left" class="px-0 mb-1" @click="go(route('people.onboarding.index'))">Onboarding</v-btn>
      <h1 class="text-h5 font-weight-bold mb-1">Start onboarding</h1>
      <p class="text-body-2 text-medium-emphasis mb-4">
        Onboarding is for an existing employee. The template's active tasks are copied into this employee's checklist, with assignees and due dates fixed now;
        later template changes won't affect it.
      </p>

      <v-card variant="outlined" class="rounded-lg pa-4">
        <v-autocomplete v-model="form.employee_id" :items="employeeItems" label="Employee *" variant="outlined" density="compact" :error-messages="form.errors.employee_id" class="mb-2" />
        <v-alert v-if="selectedEmployee && activeIds.includes(selectedEmployee.id)" type="warning" variant="tonal" density="compact" class="mb-3">This employee already has an active onboarding.</v-alert>
        <template v-else-if="selectedEmployee && completedIds.includes(selectedEmployee.id)">
          <v-alert type="info" variant="tonal" density="compact" class="mb-2">This employee already completed onboarding before. Starting again is a deliberate re-onboarding.</v-alert>
          <v-checkbox v-model="form.confirm_reonboarding" label="Yes, start a new onboarding for this employee" density="compact" hide-details class="mb-2" />
        </template>

        <v-select v-model="form.onboarding_template_id" :items="templateItems" label="Template *" variant="outlined" density="compact" :error-messages="form.errors.onboarding_template_id" :hint="templateHint" persistent-hint class="mb-3" />

        <v-row density="compact">
          <v-col cols="12" md="6"><v-text-field v-model="form.start_date" type="date" label="Start date *" variant="outlined" density="compact" :error-messages="form.errors.start_date" /></v-col>
          <v-col cols="12" md="6"><v-text-field v-model="form.target_completion_date" type="date" label="Target completion" variant="outlined" density="compact" :error-messages="form.errors.target_completion_date" /></v-col>
        </v-row>
        <v-textarea v-model="form.notes" label="Notes (internal)" rows="2" variant="outlined" density="compact" :error-messages="form.errors.notes" />
        <v-alert v-if="form.errors.application_conversion_id" type="error" variant="tonal" density="compact" class="mb-3">{{ form.errors.application_conversion_id }}</v-alert>

        <div class="d-flex justify-end">
          <v-btn color="primary" :loading="form.processing" :disabled="form.processing" @click="submit">Start onboarding</v-btn>
        </div>
      </v-card>
    </v-container>
  </SidebarLayout>
</template>

<script>
import { Head, router, useForm } from "@inertiajs/vue3";
import SidebarLayout from "@/layouts/SidebarLayout.vue";

export default {
  name: "OnboardingCreate",
  components: { SidebarLayout, Head },
  props: {
    employees: { type: Array, default: () => [] },
    templates: { type: Array, default: () => [] },
    activeEmployeeIds: { type: Array, default: () => [] },
    completedEmployeeIds: { type: Array, default: () => [] },
    prefill: { type: Object, default: () => ({}) },
  },
  data() {
    const employeeId = this.prefill.employee_id ? Number(this.prefill.employee_id) : null;
    const employee = this.employees.find((e) => e.id === employeeId);
    return {
      form: useForm({
        employee_id: employeeId,
        onboarding_template_id: null,
        start_date: employee?.joined_date ? String(employee.joined_date).substring(0, 10) : new Date().toISOString().slice(0, 10),
        target_completion_date: "",
        notes: "",
        application_conversion_id: this.prefill.application_conversion_id ? Number(this.prefill.application_conversion_id) : null,
        confirm_reonboarding: false,
      }),
    };
  },
  computed: {
    activeIds() {
      return this.activeEmployeeIds.map(Number);
    },
    completedIds() {
      return this.completedEmployeeIds.map(Number);
    },
    employeeItems() {
      return this.employees.map((e) => ({ value: e.id, title: `${e.emp_last_name}, ${e.emp_first_name} (${e.employee_number})` }));
    },
    selectedEmployee() {
      return this.employees.find((e) => e.id === this.form.employee_id) || null;
    },
    templateItems() {
      // A template matching the employee's employment status is only a suggestion; HR chooses.
      const status = this.selectedEmployee?.employment_status_id;
      return this.templates.map((t) => ({
        value: t.id,
        title: `${t.name} (${t.tasks_count} tasks)${status && t.employment_status_id === status ? " · suggested" : ""}`,
      }));
    },
    templateHint() {
      return this.templates.find((t) => t.id === this.form.onboarding_template_id)?.description || "";
    },
  },
  methods: {
    submit() {
      this.form.transform((d) => ({ ...d, target_completion_date: d.target_completion_date || null })).post(route("people.onboarding.store"));
    },
    go(url) {
      router.visit(url);
    },
  },
};
</script>
