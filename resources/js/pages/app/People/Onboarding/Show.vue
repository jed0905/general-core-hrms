<template>
  <SidebarLayout>
    <Head :title="`Onboarding · ${personName(onboarding.employee)}`" />
    <v-container fluid class="pa-6" style="max-width: 1400px">
      <div class="d-flex flex-wrap align-center justify-space-between ga-3 mb-4">
        <div>
          <v-btn variant="text" size="small" prepend-icon="mdi-arrow-left" class="px-0 mb-1" @click="go(route('people.onboarding.index'))">Onboarding</v-btn>
          <h1 class="text-h5 font-weight-bold d-flex align-center ga-3">
            {{ personName(onboarding.employee) }}
            <v-chip size="small" variant="tonal" :color="status.color">{{ status.label }}</v-chip>
          </h1>
          <p class="text-body-2 text-medium-emphasis">
            {{ onboarding.employee.employee_number }} · {{ onboarding.employee.job_title?.job_title || "—" }} · {{ onboarding.employee.department?.name || "—" }}
            · supervisor {{ personName(onboarding.employee.supervisor) }}
            <v-btn v-if="can.viewEmployee" size="x-small" variant="text" append-icon="mdi-open-in-new" @click="go(route('people.employee.show', onboarding.employee_id))">Employee record</v-btn>
          </p>
        </div>
        <div class="d-flex flex-wrap ga-2">
          <v-btn v-if="can.update" variant="outlined" prepend-icon="mdi-plus" @click="taskDialog = true">Add task</v-btn>
          <v-btn v-if="can.complete" color="success" prepend-icon="mdi-flag-checkered" :disabled="!!completionProblem" @click="openAction('complete')">Complete onboarding</v-btn>
          <v-btn v-if="can.cancel" color="error" variant="text" @click="openAction('cancel')">Cancel onboarding</v-btn>
        </div>
      </div>

      <v-alert v-if="pageError" type="error" variant="tonal" class="mb-4">{{ pageError }}</v-alert>
      <v-alert v-if="onboarding.status === 'completed'" type="success" variant="tonal" class="mb-4">Completed {{ formatDateTime(onboarding.completed_at) }} by {{ onboarding.completer?.username }}.</v-alert>
      <v-alert v-if="onboarding.status === 'cancelled'" type="error" variant="tonal" class="mb-4">Cancelled {{ formatDateTime(onboarding.cancelled_at) }} by {{ onboarding.canceller?.username }}: {{ onboarding.cancellation_reason }}</v-alert>
      <v-alert v-else-if="completionProblem && can.complete" type="info" variant="tonal" density="compact" class="mb-4">{{ completionProblem }}</v-alert>

      <v-row class="mb-2">
        <v-col cols="12" md="4">
          <v-card variant="outlined" class="rounded-lg pa-4">
            <div class="text-caption text-medium-emphasis">Required tasks</div>
            <div class="text-h6 font-weight-bold">{{ progress.required_done }} / {{ progress.required_total }}</div>
            <v-progress-linear :model-value="percent(progress.required_done, progress.required_total)" color="success" height="8" rounded />
          </v-card>
        </v-col>
        <v-col cols="12" md="4">
          <v-card variant="outlined" class="rounded-lg pa-4">
            <div class="text-caption text-medium-emphasis">All tasks</div>
            <div class="text-h6 font-weight-bold">{{ progress.tasks_done }} / {{ progress.tasks_total }}</div>
            <v-progress-linear :model-value="percent(progress.tasks_done, progress.tasks_total)" color="primary" height="8" rounded />
          </v-card>
        </v-col>
        <v-col cols="12" md="4">
          <v-card variant="outlined" class="rounded-lg pa-4">
            <div class="text-caption text-medium-emphasis">Dates</div>
            <div class="text-body-2">Start {{ formatDate(onboarding.start_date) }} · target {{ formatDate(onboarding.target_completion_date) }}</div>
            <div class="text-body-2" :class="progress.overdue_count ? 'text-error' : 'text-medium-emphasis'">{{ progress.overdue_count }} overdue task(s)</div>
            <div class="text-caption text-medium-emphasis">Template: {{ onboarding.template_name }}</div>
          </v-card>
        </v-col>
      </v-row>

      <v-row>
        <v-col cols="12" lg="8">
          <v-card v-for="group in groupedTasks" :key="group.key" variant="outlined" class="rounded-lg mb-4">
            <v-card-title class="text-subtitle-1 font-weight-bold border-b">{{ group.label }}</v-card-title>
            <v-table density="compact">
              <tbody>
                <tr v-for="t in group.tasks" :key="t.id">
                  <td style="width: 45%">
                    <div class="font-weight-medium">
                      {{ t.title }}
                      <v-chip v-if="!t.is_required" size="x-small" variant="outlined" class="ml-1">optional</v-chip>
                      <v-chip v-if="!t.employee_visible" size="x-small" variant="outlined" class="ml-1">HR only</v-chip>
                    </div>
                    <div v-if="t.description" class="text-caption text-medium-emphasis" style="white-space: pre-line">{{ t.description }}</div>
                    <div v-if="t.required_document_type" class="text-caption">
                      Needs document: {{ t.required_document_type.name }}<span v-if="t.document"> · {{ t.document.original_name }}</span>
                      <a v-if="t.document && can.downloadDocuments" :href="route('people.employee.documents.download', [onboarding.employee_id, t.document.id])" class="ml-1 text-primary">download</a>
                    </div>
                    <div v-if="t.completion_remarks" class="text-caption">"{{ t.completion_remarks }}"</div>
                    <div v-if="t.skip_reason" class="text-caption">Skipped: {{ t.skip_reason }}</div>
                  </td>
                  <td class="text-caption">{{ assigneeLabel(t) }}</td>
                  <td class="text-no-wrap text-caption" :class="{ 'text-error font-weight-bold': t.is_overdue }">{{ formatDate(t.due_date) }}</td>
                  <td>
                    <v-chip size="x-small" variant="tonal" :color="taskStatus(t.status).color">{{ taskStatus(t.status).label }}</v-chip>
                    <div v-if="t.completed_at" class="text-caption text-medium-emphasis">{{ t.completer?.username }}</div>
                    <div v-if="t.requires_verification && t.status === 'completed'" class="text-caption" :class="t.verified_at ? 'text-success' : 'text-warning'">
                      {{ t.verified_at ? `Verified · ${t.verifier?.username}` : "Awaiting verification" }}
                    </div>
                  </td>
                  <td class="text-end text-no-wrap">
                    <v-btn v-if="t.can.act && t.status === 'pending'" size="small" variant="text" :loading="busy === t.id" @click="post('start', t)">Start</v-btn>
                    <v-btn v-if="t.can.act" size="small" variant="tonal" color="success" @click="openComplete(t)">Complete</v-btn>
                    <v-btn v-if="t.can.verify" size="small" variant="tonal" color="primary" :loading="busy === t.id" @click="post('verify', t)">Verify</v-btn>
                    <v-btn v-if="t.can.skip" size="small" variant="text" @click="openSkip(t)">Skip</v-btn>
                  </td>
                </tr>
              </tbody>
            </v-table>
          </v-card>
        </v-col>

        <v-col cols="12" lg="4">
          <v-card variant="outlined" class="rounded-lg mb-4">
            <v-card-title class="text-subtitle-1 font-weight-bold border-b">Internal notes</v-card-title>
            <v-card-text>
              <p class="text-caption text-medium-emphasis mb-2">HR only; never shown to the employee.</p>
              <div v-if="onboarding.notes && onboarding.notes.length === 0 && !can.update" class="text-medium-emphasis">No notes.</div>
              <div v-if="can.update" class="mb-3">
                <v-textarea v-model="noteForm.body" label="Add a note" rows="2" variant="outlined" density="compact" :error-messages="noteForm.errors.body" />
                <div class="d-flex justify-end"><v-btn size="small" color="primary" :disabled="!noteForm.body" :loading="noteForm.processing" @click="addNote">Add note</v-btn></div>
              </div>
              <div v-for="n in onboarding.notes" :key="n.id" class="mb-3">
                <div class="text-caption text-medium-emphasis">{{ n.author_name }} · {{ formatDateTime(n.created_at) }}</div>
                <div class="text-body-2" style="white-space: pre-line">{{ n.body }}</div>
              </div>
            </v-card-text>
          </v-card>

          <v-card variant="outlined" class="rounded-lg">
            <v-card-title class="text-subtitle-1 font-weight-bold border-b">History</v-card-title>
            <v-card-text style="max-height: 520px; overflow-y: auto">
              <v-timeline density="compact" side="end" align="start">
                <v-timeline-item v-for="e in [...onboarding.events].reverse()" :key="e.id" size="x-small" :dot-color="eventColor(e.event)">
                  <div class="font-weight-medium text-body-2">{{ eventLabel(e.event) }}<span v-if="e.task"> · {{ e.task.title }}</span></div>
                  <div class="text-caption text-medium-emphasis">{{ formatDateTime(e.occurred_at) }} · {{ e.actor?.username || "system" }}</div>
                  <div v-if="e.remarks && e.event !== 'task_created'" class="text-caption">{{ e.remarks }}</div>
                </v-timeline-item>
              </v-timeline>
            </v-card-text>
          </v-card>
        </v-col>
      </v-row>

      <!-- Complete a task -->
      <v-dialog v-model="completeDialog.open" max-width="520">
        <v-card v-if="completeDialog.task">
          <v-card-title class="pa-4">Complete: {{ completeDialog.task.title }}</v-card-title>
          <v-card-text>
            <template v-if="completeDialog.task.required_document_type_id">
              <p class="text-body-2 mb-2">This task needs a <strong>{{ completeDialog.task.required_document_type?.name }}</strong> document. It is stored privately in the employee's documents.</p>
              <v-select v-if="matchingDocuments.length" v-model="completeForm.employee_document_id" :items="matchingDocuments" item-title="original_name" item-value="id" label="Use an existing document" variant="outlined" density="compact" clearable />
              <v-file-input v-model="completeForm.file" label="…or upload a file" variant="outlined" density="compact" :error-messages="completeForm.errors.file" show-size />
            </template>
            <v-textarea v-model="completeForm.remarks" label="Remarks" rows="2" variant="outlined" density="compact" :error-messages="completeForm.errors.remarks" />
            <v-alert v-if="completeForm.errors.status" type="error" variant="tonal" density="compact">{{ completeForm.errors.status }}</v-alert>
          </v-card-text>
          <v-card-actions class="pa-4">
            <v-spacer />
            <v-btn variant="text" @click="completeDialog.open = false">Back</v-btn>
            <v-btn color="success" :loading="completeForm.processing" :disabled="completeForm.processing" @click="submitComplete">Mark done</v-btn>
          </v-card-actions>
        </v-card>
      </v-dialog>

      <!-- Skip / complete onboarding / cancel onboarding -->
      <v-dialog v-model="action.open" max-width="480">
        <v-card>
          <v-card-title class="pa-4">{{ actionTitle }}</v-card-title>
          <v-card-text>
            <p v-if="action.name === 'complete'" class="text-body-2">Every required task is done. Completing closes this onboarding; it can't be edited afterwards.</p>
            <p v-if="action.name === 'cancel'" class="text-body-2">Open tasks are cancelled. The onboarding stays in history.</p>
            <v-textarea v-model="actionForm.text" :label="action.name === 'complete' ? 'Remarks (optional)' : 'Reason *'" rows="2" variant="outlined" density="compact" :error-messages="actionForm.errors.reason || actionForm.errors.status" />
          </v-card-text>
          <v-card-actions class="pa-4">
            <v-spacer />
            <v-btn variant="text" @click="action.open = false">Back</v-btn>
            <v-btn :color="action.name === 'complete' ? 'success' : 'error'" :loading="actionForm.processing" :disabled="actionForm.processing" @click="runAction">{{ actionTitle }}</v-btn>
          </v-card-actions>
        </v-card>
      </v-dialog>

      <!-- Add a task -->
      <v-dialog v-model="taskDialog" max-width="620">
        <v-card>
          <v-card-title class="pa-4">Add task</v-card-title>
          <v-card-text>
            <v-text-field v-model="taskForm.title" label="Title *" variant="outlined" density="compact" :error-messages="taskForm.errors.title" />
            <v-textarea v-model="taskForm.description" label="Instructions" rows="2" variant="outlined" density="compact" />
            <v-row density="compact">
              <v-col cols="6"><v-select v-model="taskForm.category" :items="categoryItems" label="When *" variant="outlined" density="compact" /></v-col>
              <v-col cols="6"><v-text-field v-model="taskForm.due_date" type="date" label="Due *" variant="outlined" density="compact" :error-messages="taskForm.errors.due_date" /></v-col>
              <v-col cols="6"><v-select v-model="taskForm.assignee_type" :items="assigneeItems" label="Assigned to *" variant="outlined" density="compact" /></v-col>
              <v-col v-if="taskForm.assignee_type === 'specific_employee'" cols="6"><v-autocomplete v-model="taskForm.assignee_employee_id" :items="employeeItems" label="Employee *" variant="outlined" density="compact" :error-messages="taskForm.errors.assignee_employee_id" /></v-col>
              <v-col cols="6"><v-select v-model="taskForm.required_document_type_id" :items="documentTypes" item-title="name" item-value="id" label="Needs document" variant="outlined" density="compact" clearable /></v-col>
            </v-row>
            <div class="d-flex flex-wrap ga-4">
              <v-checkbox v-model="taskForm.is_required" label="Required" density="compact" hide-details />
              <v-checkbox v-model="taskForm.employee_visible" label="Visible to employee" density="compact" hide-details />
              <v-checkbox v-model="taskForm.requires_verification" label="Needs HR verification" density="compact" hide-details />
            </div>
          </v-card-text>
          <v-card-actions class="pa-4">
            <v-spacer />
            <v-btn variant="text" @click="taskDialog = false">Back</v-btn>
            <v-btn color="primary" :loading="taskForm.processing" @click="addTask">Add task</v-btn>
          </v-card-actions>
        </v-card>
      </v-dialog>
    </v-container>
  </SidebarLayout>
</template>

<script>
import { Head, router, useForm } from "@inertiajs/vue3";
import SidebarLayout from "@/layouts/SidebarLayout.vue";
import { ONBOARDING_STATUS, TASK_STATUS, CATEGORIES, ASSIGNEE_TYPES, EVENT_LABELS, statusInfo, personName, formatDate, formatDateTime, percent, assigneeLabel } from "@/utils/onboarding";

export default {
  name: "OnboardingShow",
  components: { SidebarLayout, Head },
  props: {
    onboarding: { type: Object, required: true },
    progress: { type: Object, default: () => ({}) },
    completionProblem: { type: String, default: null },
    employeeDocuments: { type: Array, default: () => [] },
    documentTypes: { type: Array, default: () => [] },
    employees: { type: Array, default: () => [] },
    can: { type: Object, default: () => ({}) },
  },
  data() {
    return {
      busy: null,
      taskDialog: false,
      completeDialog: { open: false, task: null },
      completeForm: useForm({ remarks: "", file: null, employee_document_id: null }),
      action: { open: false, name: null, task: null },
      actionForm: useForm({ text: "" }),
      noteForm: useForm({ body: "" }),
      taskForm: useForm({
        title: "", description: "", category: "first_week", due_date: String(this.onboarding.start_date).substring(0, 10),
        assignee_type: "hr", assignee_employee_id: null, is_required: true, employee_visible: true, requires_verification: false, required_document_type_id: null,
      }),
      categoryItems: Object.entries(CATEGORIES).map(([value, title]) => ({ value, title })),
      assigneeItems: Object.entries(ASSIGNEE_TYPES).map(([value, title]) => ({ value, title })),
    };
  },
  computed: {
    status() {
      return statusInfo(ONBOARDING_STATUS, this.onboarding.status);
    },
    groupedTasks() {
      return Object.entries(CATEGORIES)
        .map(([key, label]) => ({ key, label, tasks: this.onboarding.tasks.filter((t) => t.category === key) }))
        .filter((g) => g.tasks.length);
    },
    matchingDocuments() {
      const type = this.completeDialog.task?.required_document_type_id;
      return this.employeeDocuments.filter((d) => d.employee_document_type_id === type);
    },
    employeeItems() {
      return this.employees.map((e) => ({ value: e.id, title: `${e.emp_last_name}, ${e.emp_first_name} (${e.employee_number})` }));
    },
    actionTitle() {
      return { complete: "Complete onboarding", cancel: "Cancel onboarding", skip: "Skip task" }[this.action.name] || "";
    },
    pageError() {
      const e = this.$page.props.errors || {};
      return this.completeDialog.open || this.action.open ? null : e.status || e.file || null;
    },
  },
  methods: {
    personName,
    formatDate,
    formatDateTime,
    percent,
    assigneeLabel,
    taskStatus(value) {
      return statusInfo(TASK_STATUS, value);
    },
    eventLabel(event) {
      return EVENT_LABELS[event] || event;
    },
    eventColor(event) {
      return { completed: "success", cancelled: "error", task_completed: "success", task_verified: "primary", task_skipped: "grey" }[event] || "grey";
    },
    post(action, task) {
      this.busy = task.id;
      router.post(route(`people.onboarding.tasks.${action}`, task.id), {}, { preserveScroll: true, onFinish: () => (this.busy = null) });
    },
    openComplete(task) {
      this.completeForm.reset();
      this.completeForm.clearErrors();
      this.completeDialog = { open: true, task };
    },
    submitComplete() {
      this.completeForm
        .transform((d) => ({ remarks: d.remarks || null, employee_document_id: d.employee_document_id, ...(d.file ? { file: Array.isArray(d.file) ? d.file[0] : d.file } : {}) }))
        .post(route("people.onboarding.tasks.complete", this.completeDialog.task.id), {
          forceFormData: true,
          preserveScroll: true,
          onSuccess: () => (this.completeDialog.open = false),
        });
    },
    openSkip(task) {
      this.openAction("skip", task);
    },
    openAction(name, task = null) {
      this.actionForm.reset();
      this.actionForm.clearErrors();
      this.action = { open: true, name, task };
    },
    runAction() {
      const { name, task } = this.action;
      const url = name === "skip" ? route("people.onboarding.tasks.skip", task.id) : route(`people.onboarding.${name}`, this.onboarding.id);
      this.actionForm
        .transform((d) => (name === "complete" ? { remarks: d.text || null } : { reason: d.text }))
        .post(url, { preserveScroll: true, onSuccess: () => (this.action.open = false) });
    },
    addNote() {
      this.noteForm.post(route("people.onboarding.notes.store", this.onboarding.id), { preserveScroll: true, onSuccess: () => this.noteForm.reset() });
    },
    addTask() {
      this.taskForm.post(route("people.onboarding.tasks.store", this.onboarding.id), { preserveScroll: true, onSuccess: () => { this.taskDialog = false; this.taskForm.reset(); } });
    },
    go(url) {
      router.visit(url);
    },
  },
};
</script>
