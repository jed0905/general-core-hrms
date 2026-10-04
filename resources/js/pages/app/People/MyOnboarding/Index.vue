<template>
  <SidebarLayout>
    <Head title="My Onboarding" />
    <v-container fluid class="pa-6" style="max-width: 1100px">
      <h1 class="text-h5 font-weight-bold mb-1">My Onboarding</h1>
      <p class="text-body-2 text-medium-emphasis mb-4">Your onboarding checklist, and onboarding tasks assigned to you for colleagues.</p>

      <v-alert v-if="!hasEmployeeRecord" type="info" variant="tonal">Your account isn't linked to an employee record.</v-alert>
      <v-alert v-if="pageError" type="error" variant="tonal" class="mb-4">{{ pageError }}</v-alert>

      <template v-if="hasEmployeeRecord">
        <v-card v-if="onboarding" variant="outlined" class="rounded-lg mb-6">
          <v-card-title class="d-flex flex-wrap align-center justify-space-between ga-2 border-b">
            <span class="text-subtitle-1 font-weight-bold">{{ onboarding.template_name }}</span>
            <v-chip size="small" variant="tonal" :color="onboardingStatus.color">{{ onboardingStatus.label }}</v-chip>
          </v-card-title>
          <v-card-text>
            <div class="text-body-2 mb-2">Start date {{ formatDate(onboarding.start_date) }}<span v-if="onboarding.target_completion_date"> · target {{ formatDate(onboarding.target_completion_date) }}</span></div>
            <v-progress-linear :model-value="percent(done, tasks.length)" color="success" height="10" rounded class="mb-1" />
            <div class="text-caption text-medium-emphasis mb-4">{{ done }} of {{ tasks.length }} tasks done</div>

            <v-list lines="three" density="compact">
              <v-list-item v-for="t in tasks" :key="t.id" class="px-0">
                <v-list-item-title class="d-flex flex-wrap align-center ga-2">
                  <span class="font-weight-medium">{{ t.title }}</span>
                  <v-chip size="x-small" variant="tonal" :color="taskStatus(t.status).color">{{ taskStatus(t.status).label }}</v-chip>
                  <v-chip v-if="!t.is_required" size="x-small" variant="outlined">optional</v-chip>
                  <v-chip v-if="t.is_overdue" size="x-small" color="error" variant="tonal">overdue</v-chip>
                </v-list-item-title>
                <v-list-item-subtitle>
                  {{ categories[t.category] }} · due {{ formatDate(t.due_date) }} · {{ t.assignee_type === "employee" ? "you" : assigneeTypes[t.assignee_type] }}
                  <span v-if="t.required_document_type"> · needs: {{ t.required_document_type.name }}</span>
                  <span v-if="t.requires_verification && t.status === 'completed'"> · {{ t.verified_at ? "verified by HR" : "awaiting HR verification" }}</span>
                </v-list-item-subtitle>
                <div v-if="t.description" class="text-body-2 mt-1" style="white-space: pre-line">{{ t.description }}</div>
                <template #append>
                  <v-btn v-if="t.can_act" size="small" color="success" variant="tonal" @click="openComplete(t)">Mark done</v-btn>
                </template>
              </v-list-item>
            </v-list>
          </v-card-text>
        </v-card>
        <v-alert v-else type="info" variant="tonal" class="mb-6">You have no onboarding.</v-alert>

        <v-card v-if="assignedTasks.length" variant="outlined" class="rounded-lg">
          <v-card-title class="text-subtitle-1 font-weight-bold border-b">Assigned to me</v-card-title>
          <v-table density="compact">
            <thead><tr><th>Employee</th><th>Task</th><th>Due</th><th>Status</th><th class="text-end"></th></tr></thead>
            <tbody>
              <tr v-for="t in assignedTasks" :key="t.id">
                <td>{{ personName(t.onboarding?.employee) }}<div class="text-caption text-medium-emphasis">starts {{ formatDate(t.onboarding?.start_date) }}</div></td>
                <td>{{ t.title }}<div v-if="t.description" class="text-caption text-medium-emphasis">{{ t.description }}</div></td>
                <td class="text-no-wrap" :class="{ 'text-error font-weight-bold': t.is_overdue }">{{ formatDate(t.due_date) }}</td>
                <td><v-chip size="x-small" variant="tonal" :color="taskStatus(t.status).color">{{ taskStatus(t.status).label }}</v-chip></td>
                <td class="text-end"><v-btn v-if="t.can_act" size="small" color="success" variant="tonal" @click="openComplete(t)">Mark done</v-btn></td>
              </tr>
            </tbody>
          </v-table>
        </v-card>
      </template>

      <v-dialog v-model="dialog.open" max-width="480">
        <v-card v-if="dialog.task">
          <v-card-title class="pa-4">{{ dialog.task.title }}</v-card-title>
          <v-card-text>
            <template v-if="dialog.task.required_document_type_id">
              <p class="text-body-2 mb-2">Upload your <strong>{{ dialog.task.required_document_type?.name }}</strong>. It is stored privately in your HR file.</p>
              <v-file-input v-model="form.file" label="File *" variant="outlined" density="compact" :error-messages="form.errors.file" show-size />
            </template>
            <v-textarea v-model="form.remarks" label="Remarks (optional)" rows="2" variant="outlined" density="compact" :error-messages="form.errors.remarks" />
            <v-alert v-if="form.errors.status" type="error" variant="tonal" density="compact">{{ form.errors.status }}</v-alert>
          </v-card-text>
          <v-card-actions class="pa-4">
            <v-spacer />
            <v-btn variant="text" @click="dialog.open = false">Back</v-btn>
            <v-btn color="success" :loading="form.processing" :disabled="form.processing" @click="submit">Mark done</v-btn>
          </v-card-actions>
        </v-card>
      </v-dialog>
    </v-container>
  </SidebarLayout>
</template>

<script>
import { Head, useForm } from "@inertiajs/vue3";
import SidebarLayout from "@/layouts/SidebarLayout.vue";
import { ONBOARDING_STATUS, TASK_STATUS, CATEGORIES, ASSIGNEE_TYPES, statusInfo, personName, formatDate, percent } from "@/utils/onboarding";

export default {
  name: "MyOnboarding",
  components: { SidebarLayout, Head },
  props: {
    hasEmployeeRecord: { type: Boolean, default: false },
    onboarding: { type: Object, default: null },
    tasks: { type: Array, default: () => [] },
    assignedTasks: { type: Array, default: () => [] },
  },
  data() {
    return {
      categories: CATEGORIES,
      assigneeTypes: ASSIGNEE_TYPES,
      dialog: { open: false, task: null },
      form: useForm({ remarks: "", file: null }),
    };
  },
  computed: {
    onboardingStatus() {
      return statusInfo(ONBOARDING_STATUS, this.onboarding?.status);
    },
    done() {
      return this.tasks.filter((t) => ["completed", "skipped"].includes(t.status)).length;
    },
    pageError() {
      return this.dialog.open ? null : this.$page.props.errors?.status || null;
    },
  },
  methods: {
    personName,
    formatDate,
    percent,
    taskStatus(value) {
      return statusInfo(TASK_STATUS, value);
    },
    openComplete(task) {
      this.form.reset();
      this.form.clearErrors();
      this.dialog = { open: true, task };
    },
    submit() {
      this.form
        .transform((d) => ({ remarks: d.remarks || null, ...(d.file ? { file: Array.isArray(d.file) ? d.file[0] : d.file } : {}) }))
        .post(route("people.onboarding.tasks.complete", this.dialog.task.id), {
          forceFormData: true,
          preserveScroll: true,
          onSuccess: () => (this.dialog.open = false),
        });
    },
  },
};
</script>
