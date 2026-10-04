<template>
  <SidebarLayout>
    <Head :title="vacancy.vacancy_number" />
    <v-container fluid class="pa-6">
      <div class="d-flex flex-wrap align-center justify-space-between ga-3 mb-4">
        <div>
          <v-btn variant="text" size="small" prepend-icon="mdi-arrow-left" class="px-0 mb-1" @click="go(route('recruitment.vacancies.index'))">Vacancies</v-btn>
          <h1 class="text-h5 font-weight-bold d-flex align-center ga-3">
            {{ vacancy.title }}
            <v-chip size="small" variant="tonal" :color="status.color">{{ status.label }}</v-chip>
          </h1>
          <p class="text-body-2 text-medium-emphasis">{{ vacancy.vacancy_number }} · {{ vacancy.department?.name }}</p>
        </div>
        <div class="d-flex flex-wrap ga-2">
          <v-btn v-if="can.update" variant="outlined" prepend-icon="mdi-pencil-outline" @click="go(route('recruitment.vacancies.edit', vacancy.id))">Edit</v-btn>
          <v-btn v-for="a in availableActions" :key="a.action" :color="a.color" :variant="a.variant" :prepend-icon="a.icon" @click="openDialog(a)">{{ a.title }}</v-btn>
        </div>
      </div>

      <v-alert v-if="$page.props.errors?.status" type="error" variant="tonal" class="mb-4">{{ $page.props.errors.status }}</v-alert>
      <v-alert v-if="vacancy.status_reason" type="info" variant="tonal" class="mb-4">Status note: {{ vacancy.status_reason }}</v-alert>

      <!-- Careers site (public portal) -->
      <v-card v-if="publication && (can.publish || publication.published_at)" variant="outlined" class="rounded-lg mb-4">
        <v-card-title class="d-flex flex-wrap align-center ga-2 text-subtitle-1 font-weight-bold border-b">
          Careers site
          <v-chip size="small" variant="tonal" :color="publication.live ? 'success' : publication.published_at ? 'warning' : 'grey'">
            {{ publication.live ? "Live" : publication.published_at ? "Published, not live" : "Not published" }}
          </v-chip>
        </v-card-title>
        <v-card-text>
          <p v-if="!publication.eligible && !publication.published_at" class="text-body-2 text-medium-emphasis mb-2">Only open vacancies for external candidates (visibility "External" or "Both") can be published.</p>
          <p v-else-if="publication.published_at && !publication.live" class="text-body-2 text-medium-emphasis mb-2">Candidates see it only while the vacancy is open and before its closing date.</p>
          <div v-if="publication.url" class="text-body-2 mb-2">Public link: <a :href="publication.url" target="_blank" rel="noopener">{{ publication.url }}</a></div>
          <v-alert v-if="$page.props.errors?.publish" type="error" variant="tonal" density="compact" class="mb-2">{{ $page.props.errors.publish }}</v-alert>
          <div class="d-flex flex-wrap align-center ga-2">
            <v-checkbox v-if="can.publish" v-model="showSalary" label="Show salary range publicly" density="compact" hide-details />
            <v-spacer />
            <v-btn variant="text" prepend-icon="mdi-eye-outline" :href="route('recruitment.vacancies.public-preview', vacancy.id)" target="_blank">Preview</v-btn>
            <template v-if="can.publish">
              <v-btn v-if="publication.published_at" variant="outlined" @click="careers('unpublish-online')">Unpublish</v-btn>
              <v-btn v-if="publication.eligible" color="primary" variant="tonal" @click="careers('publish-online')">{{ publication.published_at ? "Update" : "Publish" }}</v-btn>
            </template>
          </div>
        </v-card-text>
      </v-card>

      <!-- Openings and selection decisions (facts only; selection stays a human decision) -->
      <v-card v-if="selectionSummary" variant="outlined" class="rounded-lg mb-4">
        <v-card-title class="text-subtitle-1 font-weight-bold border-b">Openings and selection</v-card-title>
        <v-card-text class="d-flex flex-wrap ga-6">
          <div><div class="text-h6 font-weight-bold">{{ selectionSummary.openings }}</div><div class="text-caption text-medium-emphasis">Openings</div></div>
          <div><div class="text-h6 font-weight-bold">{{ selectionSummary.filled }}</div><div class="text-caption text-medium-emphasis">Taken by selected candidates</div></div>
          <div><div class="text-h6 font-weight-bold">{{ selectionSummary.remaining }}</div><div class="text-caption text-medium-emphasis">Remaining</div></div>
          <div><div class="text-h6 font-weight-bold">{{ selectionSummary.in_evaluation }}</div><div class="text-caption text-medium-emphasis">Candidates in evaluation</div></div>
          <div><div class="text-h6 font-weight-bold text-success">{{ selectionSummary.selected }}</div><div class="text-caption text-medium-emphasis">Selected</div></div>
          <div><div class="text-h6 font-weight-bold">{{ selectionSummary.not_selected }}</div><div class="text-caption text-medium-emphasis">Not selected</div></div>
        </v-card-text>
      </v-card>

      <!-- Pipeline (ATS workspace) -->
      <v-card v-if="pipeline" variant="outlined" class="rounded-lg mb-4">
        <v-card-title class="d-flex align-center justify-space-between text-subtitle-1 font-weight-bold border-b">
          Pipeline
          <v-btn v-if="can.addApplicant" size="small" color="primary" variant="tonal" prepend-icon="mdi-account-plus-outline" @click="go(route('recruitment.applicants.index'))">Add from applicants</v-btn>
        </v-card-title>
        <v-card-text>
          <div class="d-flex flex-wrap ga-3 mb-4">
            <v-card
              v-for="column in pipelineColumns"
              :key="column.key"
              :variant="String(selectedStage) === String(column.key) ? 'tonal' : 'outlined'"
              :color="String(selectedStage) === String(column.key) ? column.color : undefined"
              class="pa-3 text-center flex-grow-1"
              style="min-width: 130px"
              @click="selectStage(column.key)"
            >
              <div class="text-h5 font-weight-bold">{{ column.count }}</div>
              <div class="text-caption">{{ column.label }}</div>
            </v-card>
          </div>
          <v-table v-if="stageApplications" density="compact">
            <thead><tr><th>Applicant</th><th>Application</th><th>Contact</th><th>Screening</th><th>Selection</th><th>Applied</th><th /></tr></thead>
            <tbody>
              <tr v-if="!stageApplications.data.length"><td colspan="7" class="text-center text-medium-emphasis pa-4">No applications here.</td></tr>
              <tr v-for="a in stageApplications.data" :key="a.id">
                <td>{{ applicantName(a.applicant) }}<div class="text-caption text-medium-emphasis">{{ a.applicant?.applicant_number }}</div></td>
                <td class="text-no-wrap">{{ a.application_number }}</td>
                <td>{{ a.applicant?.email || a.applicant?.phone || "—" }}</td>
                <td>
                  <v-chip v-if="a.screening" size="x-small" variant="tonal" :color="a.screening.result === 'passed' ? 'success' : 'error'">{{ a.screening.result }}</v-chip>
                  <span v-else class="text-medium-emphasis">—</span>
                </td>
                <td>
                  <v-chip v-if="a.current_selection" size="x-small" variant="tonal" :color="selectionInfo(a.current_selection.decision).color">{{ selectionInfo(a.current_selection.decision).label }}</v-chip>
                  <span v-else class="text-medium-emphasis">—</span>
                </td>
                <td class="text-no-wrap">{{ formatDateTime(a.applied_at) }}</td>
                <td class="text-end"><v-btn icon="mdi-eye-outline" size="small" variant="text" @click="go(route('recruitment.applications.show', a.id))" /></td>
              </tr>
            </tbody>
          </v-table>
          <Pagination v-if="stageApplications" :meta="stageApplications" />
        </v-card-text>
      </v-card>

      <v-row>
        <v-col cols="12" lg="8">
          <v-card variant="outlined" class="rounded-lg mb-4">
            <v-card-title class="text-subtitle-1 font-weight-bold border-b">Position</v-card-title>
            <v-card-text>
              <v-row density="compact">
                <v-col v-for="item in details" :key="item.label" cols="12" sm="6" md="4">
                  <div class="text-caption text-medium-emphasis">{{ item.label }}</div>
                  <div class="text-body-1">{{ item.value }}</div>
                </v-col>
              </v-row>
            </v-card-text>
          </v-card>

          <v-card v-for="section in textSections" :key="section.title" variant="outlined" class="rounded-lg mb-4">
            <v-card-title class="text-subtitle-1 font-weight-bold border-b">{{ section.title }}</v-card-title>
            <v-card-text class="text-body-1" style="white-space: pre-line">{{ section.value || "Not provided." }}</v-card-text>
          </v-card>
        </v-col>

        <v-col cols="12" lg="4">
          <v-card variant="outlined" class="rounded-lg mb-4">
            <v-card-title class="text-subtitle-1 font-weight-bold border-b">Openings</v-card-title>
            <v-card-text class="d-flex justify-space-around text-center">
              <div><div class="text-h5 font-weight-bold">{{ vacancy.openings }}</div><div class="text-caption text-medium-emphasis">Authorized</div></div>
              <div><div class="text-h5 font-weight-bold">{{ vacancy.filled_count }}</div><div class="text-caption text-medium-emphasis">Filled</div></div>
              <div><div class="text-h5 font-weight-bold text-primary">{{ vacancy.remaining_openings }}</div><div class="text-caption text-medium-emphasis">Remaining</div></div>
            </v-card-text>
          </v-card>

          <v-card variant="outlined" class="rounded-lg mb-4">
            <v-card-title class="text-subtitle-1 font-weight-bold border-b">Origin</v-card-title>
            <v-card-text>
              <template v-if="vacancy.requisition">
                Requisition
                <a v-if="can.viewRequisition" href="#" class="text-primary" @click.prevent="go(route('recruitment.requisitions.show', vacancy.requisition.id))">{{ vacancy.requisition.requisition_number }}</a>
                <strong v-else>{{ vacancy.requisition.requisition_number }}</strong>
                ({{ vacancy.requisition.positions }} approved position(s))
              </template>
              <span v-else class="text-medium-emphasis">Created directly (no requisition).</span>
            </v-card-text>
          </v-card>

          <v-card variant="outlined" class="rounded-lg">
            <v-card-title class="text-subtitle-1 font-weight-bold border-b">Dates</v-card-title>
            <v-list density="compact">
              <v-list-item title="Opening date" :subtitle="formatDate(vacancy.opening_date)" />
              <v-list-item title="Closing date" :subtitle="formatDate(vacancy.closing_date)" />
              <v-list-item v-if="vacancy.opened_at" title="Opened" :subtitle="formatDateTime(vacancy.opened_at)" />
              <v-list-item v-if="vacancy.closed_at" title="Closed" :subtitle="formatDateTime(vacancy.closed_at)" />
              <v-list-item title="Created" :subtitle="`${formatDateTime(vacancy.created_at)}${vacancy.creator ? ' · ' + vacancy.creator.username : ''}`" />
            </v-list>
          </v-card>
        </v-col>
      </v-row>

      <v-dialog v-model="dialog.open" max-width="480">
        <v-card>
          <v-card-title class="pa-4">{{ dialog.title }}</v-card-title>
          <v-card-text>
            <v-textarea v-model="dialog.reason" :label="dialog.requiresReason ? 'Reason *' : 'Note (optional)'" rows="3" variant="outlined" density="compact" :error-messages="dialog.errors" />
          </v-card-text>
          <v-card-actions class="pa-4">
            <v-spacer />
            <v-btn variant="text" @click="dialog.open = false">Back</v-btn>
            <v-btn :color="dialog.color === 'error' ? 'error' : 'primary'" :loading="dialog.busy" @click="confirm">{{ dialog.title }}</v-btn>
          </v-card-actions>
        </v-card>
      </v-dialog>
    </v-container>
  </SidebarLayout>
</template>

<script>
import { Head, router } from "@inertiajs/vue3";
import SidebarLayout from "@/layouts/SidebarLayout.vue";
import Pagination from "@/components/Pagination.vue";
import { SELECTION_DECISIONS, VACANCY_STATUS, VISIBILITY_LABELS, statusInfo, personName, applicantName, formatDate, formatDateTime } from "@/utils/recruitment";

// Button per action; "to" must be an allowed transition from the current status.
const ACTIONS = [
  { action: "open", to: "open", from: ["draft"], ability: "publish", title: "Open", icon: "mdi-play", color: "primary", variant: "flat" },
  { action: "reopen", to: "open", from: ["on_hold"], ability: "publish", title: "Reopen", icon: "mdi-play", color: "primary", variant: "flat" },
  { action: "hold", to: "on_hold", ability: "publish", title: "Put on hold", icon: "mdi-pause", color: "warning", variant: "outlined", requiresReason: true },
  { action: "fill", to: "filled", ability: "close", title: "Mark filled", icon: "mdi-check-all", color: "success", variant: "outlined" },
  { action: "close", to: "closed", ability: "close", title: "Close", icon: "mdi-lock-outline", color: "info", variant: "outlined" },
  { action: "cancel", to: "cancelled", ability: "close", title: "Cancel vacancy", icon: "mdi-cancel", color: "error", variant: "text", requiresReason: true },
];

export default {
  name: "VacancyShow",
  components: { SidebarLayout, Head, Pagination },
  props: {
    vacancy: { type: Object, required: true },
    transitions: { type: Array, default: () => [] },
    can: { type: Object, default: () => ({}) },
    pipeline: { type: Object, default: null },
    selectedStage: { type: [Number, String], default: null },
    stageApplications: { type: Object, default: null },
    selectionSummary: { type: Object, default: null },
    publication: { type: Object, default: null },
  },
  data() {
    return { showSalary: this.publication?.show_salary_publicly || false, dialog: { open: false, action: null, title: "", reason: "", requiresReason: false, errors: [], busy: false, color: null } };
  },
  computed: {
    pipelineColumns() {
      if (!this.pipeline) return [];
      return [
        ...this.pipeline.stages.map((s) => ({ key: s.id, label: s.name, count: s.count, color: s.stage_type === "shortlisted" ? "success" : "primary" })),
        { key: "rejected", label: "Rejected", count: this.pipeline.rejected, color: "error" },
        { key: "withdrawn", label: "Withdrawn", count: this.pipeline.withdrawn, color: "grey" },
      ];
    },
    status() {
      return statusInfo(VACANCY_STATUS, this.vacancy.status);
    },
    availableActions() {
      return ACTIONS.filter((a) => this.transitions.includes(a.to) && this.can[a.ability] && (!a.from || a.from.includes(this.vacancy.status)));
    },
    details() {
      const v = this.vacancy;
      const salary = v.salary_min || v.salary_max
        ? `${v.salary_currency || ""} ${[v.salary_min, v.salary_max].filter((x) => x !== null).map((x) => Number(x).toLocaleString()).join(" – ")}`.trim()
        : "—";
      return [
        { label: "Job title", value: v.job_title?.job_title || "—" },
        { label: "Department", value: v.department?.name || "—" },
        { label: "Location", value: v.location ? [v.location.address, v.location.city].filter(Boolean).join(", ") : "—" },
        { label: "Employment status", value: v.employment_status?.name || "—" },
        { label: "Hiring manager", value: personName(v.hiring_manager) },
        { label: "Visibility", value: VISIBILITY_LABELS[v.visibility] || v.visibility },
        { label: "Salary range", value: salary },
      ];
    },
    textSections() {
      return [
        { title: "Job description", value: this.vacancy.description },
        { title: "Responsibilities", value: this.vacancy.responsibilities },
        { title: "Qualifications", value: this.vacancy.qualifications },
      ];
    },
  },
  methods: {
    careers(action) {
      router.post(route(`recruitment.vacancies.${action}`, this.vacancy.id), { show_salary_publicly: this.showSalary }, { preserveScroll: true });
    },
    selectionInfo(decision) {
      return statusInfo(SELECTION_DECISIONS, decision);
    },
    formatDate,
    formatDateTime,
    openDialog(a) {
      this.dialog = { open: true, action: a.action, title: a.title, reason: "", requiresReason: Boolean(a.requiresReason), errors: [], busy: false, color: a.color };
    },
    confirm() {
      this.dialog.busy = true;
      router.post(route(`recruitment.vacancies.${this.dialog.action}`, this.vacancy.id), { reason: this.dialog.reason }, {
        preserveScroll: true,
        onSuccess: () => (this.dialog.open = false),
        onError: (errors) => {
          this.dialog.errors = errors.reason ? [errors.reason] : [];
          if (!errors.reason) this.dialog.open = false;
        },
        onFinish: () => (this.dialog.busy = false),
      });
    },
    applicantName,
    selectStage(key) {
      router.get(route("recruitment.vacancies.show", this.vacancy.id), { stage: key }, {
        preserveState: true,
        preserveScroll: true,
        only: ["selectedStage", "stageApplications"],
      });
    },
    go(url) {
      router.visit(url);
    },
  },
};
</script>
