<template>
  <SidebarLayout>
    <Head :title="template ? `Edit ${template.name}` : 'New onboarding template'" />
    <v-container fluid class="pa-6" style="max-width: 1200px">
      <v-btn variant="text" size="small" prepend-icon="mdi-arrow-left" class="px-0 mb-1" @click="go(route('people.onboarding-templates.index'))">Onboarding Templates</v-btn>
      <h1 class="text-h5 font-weight-bold mb-1">{{ template ? "Edit template" : "New template" }}</h1>
      <p class="text-body-2 text-medium-emphasis mb-4">Changes apply to onboardings started from now on. Existing onboardings keep the tasks they were created with.</p>

      <v-card variant="outlined" class="rounded-lg pa-4 mb-4">
        <v-row density="compact">
          <v-col cols="12" md="6"><v-text-field v-model="form.name" label="Name *" variant="outlined" density="compact" :error-messages="form.errors.name" /></v-col>
          <v-col cols="12" md="4"><v-select v-model="form.employment_status_id" :items="employmentStatuses" item-title="name" item-value="id" label="Suggest for employment status" variant="outlined" density="compact" clearable /></v-col>
          <v-col cols="12" md="2"><v-switch v-model="form.is_active" label="Active" color="success" density="compact" hide-details /></v-col>
          <v-col cols="12"><v-textarea v-model="form.description" label="Description" rows="2" variant="outlined" density="compact" /></v-col>
        </v-row>
      </v-card>

      <div class="d-flex align-center justify-space-between mb-2">
        <h2 class="text-subtitle-1 font-weight-bold">Tasks</h2>
        <v-btn size="small" variant="tonal" prepend-icon="mdi-plus" @click="addRow">Add task</v-btn>
      </div>
      <v-alert v-if="form.errors.tasks" type="error" variant="tonal" density="compact" class="mb-2">{{ form.errors.tasks }}</v-alert>

      <v-card v-for="(t, i) in form.tasks" :key="t._key" variant="outlined" class="rounded-lg pa-4 mb-3" :class="{ 'opacity-60': !t.is_active }">
        <div class="d-flex align-center ga-2 mb-2">
          <span class="text-caption text-medium-emphasis">#{{ i + 1 }}</span>
          <v-spacer />
          <v-btn icon="mdi-arrow-up" size="x-small" variant="text" :disabled="i === 0" @click="move(i, -1)" />
          <v-btn icon="mdi-arrow-down" size="x-small" variant="text" :disabled="i === form.tasks.length - 1" @click="move(i, 1)" />
          <v-btn icon="mdi-delete-outline" size="x-small" variant="text" color="error" @click="form.tasks.splice(i, 1)" />
        </div>
        <v-row density="compact">
          <v-col cols="12" md="6"><v-text-field v-model="t.title" label="Title *" variant="outlined" density="compact" :error-messages="err(i, 'title')" /></v-col>
          <v-col cols="6" md="3"><v-select v-model="t.category" :items="categoryItems" label="When *" variant="outlined" density="compact" :error-messages="err(i, 'category')" /></v-col>
          <v-col cols="6" md="3"><v-select v-model="t.assignee_type" :items="assigneeItems" label="Assigned to *" variant="outlined" density="compact" :error-messages="err(i, 'assignee_type')" /></v-col>
          <v-col v-if="t.assignee_type === 'specific_employee'" cols="12" md="6"><v-autocomplete v-model="t.assignee_employee_id" :items="employeeItems" label="Designated employee *" variant="outlined" density="compact" :error-messages="err(i, 'assignee_employee_id')" /></v-col>
          <v-col cols="6" md="3"><v-text-field v-model.number="t.due_offset_days" type="number" label="Due (days) *" hint="Negative = before" persistent-hint variant="outlined" density="compact" :error-messages="err(i, 'due_offset_days')" /></v-col>
          <v-col cols="6" md="3"><v-select v-model="t.due_relative_to" :items="relativeItems" label="Counted from" variant="outlined" density="compact" /></v-col>
          <v-col cols="12" md="6"><v-select v-model="t.required_document_type_id" :items="documentTypes" item-title="name" item-value="id" label="Needs document (optional)" variant="outlined" density="compact" clearable :error-messages="err(i, 'required_document_type_id')" /></v-col>
          <v-col cols="12"><v-textarea v-model="t.description" label="Instructions" rows="1" auto-grow variant="outlined" density="compact" /></v-col>
        </v-row>
        <div class="d-flex flex-wrap ga-4">
          <v-checkbox v-model="t.is_required" label="Required" density="compact" hide-details />
          <v-checkbox v-model="t.employee_visible" label="Visible to employee" density="compact" hide-details :disabled="t.assignee_type === 'employee'" />
          <v-checkbox v-model="t.requires_verification" label="Needs HR verification" density="compact" hide-details />
          <v-checkbox v-model="t.is_active" label="Active" density="compact" hide-details />
        </div>
      </v-card>

      <div class="d-flex justify-end">
        <v-btn color="primary" :loading="form.processing" :disabled="form.processing" @click="save">{{ template ? "Save template" : "Create template" }}</v-btn>
      </div>
    </v-container>
  </SidebarLayout>
</template>

<script>
import { Head, router, useForm } from "@inertiajs/vue3";
import SidebarLayout from "@/layouts/SidebarLayout.vue";
import { CATEGORIES, ASSIGNEE_TYPES } from "@/utils/onboarding";

let key = 0;
const blankTask = () => ({
  _key: ++key, id: null, title: "", description: "", category: "first_day", assignee_type: "employee", assignee_employee_id: null,
  due_relative_to: "start_date", due_offset_days: 0, is_required: true, employee_visible: true, requires_verification: false,
  required_document_type_id: null, is_active: true,
});

export default {
  name: "OnboardingTemplateForm",
  components: { SidebarLayout, Head },
  props: {
    template: { type: Object, default: null },
    employmentStatuses: { type: Array, default: () => [] },
    documentTypes: { type: Array, default: () => [] },
    employees: { type: Array, default: () => [] },
  },
  data() {
    const t = this.template;
    return {
      form: useForm({
        name: t?.name || "",
        description: t?.description || "",
        employment_status_id: t?.employment_status_id || null,
        is_active: t ? t.is_active : true,
        tasks: t ? t.tasks.map((task) => ({ ...blankTask(), ...task, description: task.description || "" })) : [blankTask()],
      }),
      categoryItems: Object.entries(CATEGORIES).map(([value, title]) => ({ value, title })),
      assigneeItems: Object.entries(ASSIGNEE_TYPES).map(([value, title]) => ({ value, title })),
      relativeItems: [{ value: "start_date", title: "Start date" }, { value: "created", title: "Onboarding creation" }],
    };
  },
  computed: {
    employeeItems() {
      return this.employees.map((e) => ({ value: e.id, title: `${e.emp_last_name}, ${e.emp_first_name} (${e.employee_number})` }));
    },
  },
  methods: {
    err(i, field) {
      return this.form.errors[`tasks.${i}.${field}`];
    },
    addRow() {
      this.form.tasks.push(blankTask());
    },
    move(i, delta) {
      const rows = this.form.tasks;
      [rows[i], rows[i + delta]] = [rows[i + delta], rows[i]];
    },
    save() {
      const form = this.form.transform((d) => ({
        ...d,
        tasks: d.tasks.map(({ _key, created_at, updated_at, onboarding_template_id, sort_order, ...row }) => ({
          ...row,
          employee_visible: row.assignee_type === "employee" ? true : row.employee_visible,
        })),
      }));
      this.template ? form.put(route("people.onboarding-templates.update", this.template.id), { preserveScroll: true }) : form.post(route("people.onboarding-templates.store"));
    },
    go(url) {
      router.visit(url);
    },
  },
};
</script>
