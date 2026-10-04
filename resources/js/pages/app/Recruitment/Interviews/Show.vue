<template>
  <SidebarLayout>
    <Head :title="`Interview · ${applicantName(interview.application.applicant)}`" />
    <v-container fluid class="pa-6" style="max-width: 1300px">
      <div class="d-flex flex-wrap align-center justify-space-between ga-3 mb-4">
        <div>
          <v-btn v-if="can.viewApplication" variant="text" size="small" prepend-icon="mdi-arrow-left" class="px-0 mb-1" @click="go(route('recruitment.applications.show', interview.application.id))">
            {{ interview.application.application_number }}
          </v-btn>
          <v-btn v-else variant="text" size="small" prepend-icon="mdi-arrow-left" class="px-0 mb-1" @click="go(route('recruitment.interviews.mine'))">My Interviews</v-btn>
          <h1 class="text-h5 font-weight-bold d-flex align-center ga-3">
            {{ interview.type?.name }} · {{ applicantName(interview.application.applicant) }}
            <v-chip size="small" variant="tonal" :color="status.color">{{ status.label }}</v-chip>
          </h1>
          <p class="text-body-2 text-medium-emphasis">
            Round {{ interview.round }} · {{ interview.application.vacancy?.title }} ({{ interview.application.vacancy?.vacancy_number }}) · stage: {{ interview.application.current_stage?.name }}
          </p>
        </div>
        <div v-if="can.update" class="d-flex flex-wrap ga-2">
          <v-btn color="success" prepend-icon="mdi-check" @click="openAction('complete')">Mark completed</v-btn>
          <v-btn variant="outlined" prepend-icon="mdi-calendar-clock" @click="openAction('reschedule')">Reschedule</v-btn>
          <v-btn variant="outlined" prepend-icon="mdi-pencil-outline" @click="editDialog = true">Edit</v-btn>
          <v-btn variant="text" color="error" @click="openAction('cancel')">Cancel interview</v-btn>
        </div>
      </div>

      <v-alert v-if="pageError" type="error" variant="tonal" class="mb-4">{{ pageError }}</v-alert>

      <v-row>
        <v-col cols="12" lg="7">
          <v-card variant="outlined" class="rounded-lg mb-4">
            <v-card-title class="text-subtitle-1 font-weight-bold border-b">Details</v-card-title>
            <v-card-text>
              <v-row density="compact">
                <v-col cols="12" sm="6"><div class="text-caption text-medium-emphasis">When</div>{{ interviewWhen(interview) }} ({{ interview.duration_minutes }} min)</v-col>
                <v-col cols="12" sm="6">
                  <div class="text-caption text-medium-emphasis">Mode</div>
                  <v-icon :icon="modeIcon" size="small" class="mr-1" />{{ interviewModeLabel(interview.mode) }}
                </v-col>
                <v-col v-if="interview.meeting_url" cols="12" sm="6">
                  <div class="text-caption text-medium-emphasis">Meeting link</div>
                  <a :href="interview.meeting_url" target="_blank" rel="noopener noreferrer" class="text-primary">{{ interview.meeting_url }}</a>
                </v-col>
                <v-col v-if="interview.location" cols="12" sm="6"><div class="text-caption text-medium-emphasis">Location</div>{{ interview.location }}</v-col>
                <v-col v-if="interview.instructions" cols="12"><div class="text-caption text-medium-emphasis">Instructions</div><div style="white-space: pre-line">{{ interview.instructions }}</div></v-col>
                <v-col v-if="interview.status === 'completed'" cols="12">
                  <div class="text-caption text-medium-emphasis">Completed</div>
                  {{ formatDateTime(interview.completed_at) }} · {{ interview.completer?.username }}
                  <div v-if="interview.completion_remarks" style="white-space: pre-line">{{ interview.completion_remarks }}</div>
                </v-col>
                <v-col v-if="interview.status === 'cancelled'" cols="12">
                  <div class="text-caption text-medium-emphasis">Cancelled</div>
                  {{ formatDateTime(interview.cancelled_at) }} · {{ interview.canceller?.username }}
                  <div v-if="interview.cancellation_reason">{{ interview.cancellation_reason }}</div>
                </v-col>
              </v-row>
            </v-card-text>
          </v-card>

          <!-- Panelist's own scorecard -->
          <v-card v-if="can.evaluate || myScorecard" variant="outlined" class="rounded-lg mb-4">
            <v-card-title class="d-flex align-center justify-space-between text-subtitle-1 font-weight-bold border-b">
              My scorecard
              <v-chip v-if="myScorecard" size="small" variant="tonal" :color="submitted ? 'success' : 'warning'">{{ submitted ? "Submitted" : "Draft" }}</v-chip>
            </v-card-title>
            <v-card-text>
              <v-alert v-if="submitted" type="success" variant="tonal" density="compact" class="mb-3">
                Submitted {{ formatDateTime(myScorecard.submitted_at) }}. Submitted scorecards can't be changed.
              </v-alert>
              <v-alert v-if="scoreError" type="error" variant="tonal" density="compact" class="mb-3">{{ scoreError }}</v-alert>
              <div v-for="c in scoredCriteria" :key="c.id" class="mb-4">
                <div class="font-weight-medium">{{ c.name }}</div>
                <div v-if="c.description" class="text-caption text-medium-emphasis">{{ c.description }}</div>
                <v-btn-toggle v-model="scoreForm.ratings[c.id]" :disabled="readOnly" color="primary" density="compact" variant="outlined" divided class="mt-1 flex-wrap">
                  <v-btn v-for="(label, value) in ratingScale" :key="value" :value="Number(value)" size="small" :title="label">{{ value }} · {{ label }}</v-btn>
                </v-btn-toggle>
                <v-text-field v-model="scoreForm.notes[c.id]" :readonly="readOnly" placeholder="Comment (optional)" variant="underlined" density="compact" hide-details class="mt-1" />
              </div>
              <v-radio-group v-model="scoreForm.recommendation" :readonly="readOnly" inline label="Overall recommendation" :error-messages="scoreForm.errors.recommendation">
                <v-radio v-for="(r, value) in recommendations" :key="value" :label="r.label" :value="value" :color="r.color" />
              </v-radio-group>
              <v-textarea v-model="scoreForm.comments" :readonly="readOnly" label="Overall comments" rows="3" variant="outlined" density="compact" :error-messages="scoreForm.errors.comments" />
              <div v-if="!readOnly" class="d-flex justify-end ga-2">
                <v-btn variant="tonal" :loading="scoreForm.processing && saving === 'draft'" @click="saveScorecard('draft')">Save draft</v-btn>
                <v-btn color="primary" :loading="scoreForm.processing && saving === 'submit'" @click="saveScorecard('submit')">Submit scorecard</v-btn>
              </div>
              <p v-if="!readOnly" class="text-caption text-medium-emphasis mt-2 text-end">Submitting locks the scorecard: every criterion and a recommendation are required.</p>
            </v-card-text>
          </v-card>
          <v-alert v-else-if="can.isPanelist && interview.status === 'scheduled'" type="info" variant="tonal" class="mb-4">You can write your scorecard once HR marks this interview completed.</v-alert>

          <!-- Submitted scorecards (HR / hiring manager) -->
          <v-card v-if="can.viewEvaluations" variant="outlined" class="rounded-lg mb-4">
            <v-card-title class="d-flex align-center justify-space-between text-subtitle-1 font-weight-bold border-b">
              Scorecards
              <span class="text-body-2 text-medium-emphasis">{{ progress.submitted }} of {{ progress.panelists }} submitted</span>
            </v-card-title>
            <v-card-text v-if="!scorecards.length" class="text-medium-emphasis">No scorecards submitted yet.</v-card-text>
            <v-expansion-panels v-else variant="accordion" flat>
              <v-expansion-panel v-for="e in scorecards" :key="e.id">
                <v-expansion-panel-title>
                  <div class="d-flex flex-wrap align-center ga-2">
                    <span class="font-weight-medium">{{ personName(e.evaluator) }}</span>
                    <v-chip size="x-small" variant="tonal" :color="recommendation(e.recommendation).color">{{ recommendation(e.recommendation).label }}</v-chip>
                    <span class="text-body-2">avg {{ averageRating(e.scores) }} / 5</span>
                  </div>
                </v-expansion-panel-title>
                <v-expansion-panel-text>
                  <div class="text-caption text-medium-emphasis mb-2">Submitted {{ formatDateTime(e.submitted_at) }}</div>
                  <v-table density="compact">
                    <tbody>
                      <tr v-for="sc in e.scores" :key="sc.id">
                        <td>{{ sc.criterion_name }}<div v-if="sc.comments" class="text-caption text-medium-emphasis">{{ sc.comments }}</div></td>
                        <td class="text-end text-no-wrap">{{ sc.rating }} · {{ ratingScale[sc.rating] }}</td>
                      </tr>
                    </tbody>
                  </v-table>
                  <div v-if="e.comments" class="text-body-2 mt-2" style="white-space: pre-line">{{ e.comments }}</div>
                </v-expansion-panel-text>
              </v-expansion-panel>
            </v-expansion-panels>
          </v-card>
        </v-col>

        <v-col cols="12" lg="5">
          <v-card variant="outlined" class="rounded-lg mb-4">
            <v-card-title class="text-subtitle-1 font-weight-bold border-b">Panel</v-card-title>
            <v-list density="compact">
              <v-list-item v-for="p in interview.panelists" :key="p.id" :title="personName(p.employee)" :subtitle="p.employee?.employee_number">
                <template #append><v-chip v-if="p.is_primary" size="x-small" variant="tonal" color="primary">Lead</v-chip></template>
              </v-list-item>
            </v-list>
          </v-card>

          <v-card variant="outlined" class="rounded-lg mb-4">
            <v-card-title class="text-subtitle-1 font-weight-bold border-b">Submitted documents</v-card-title>
            <v-list v-if="interview.application.documents.length" density="compact">
              <v-list-item v-for="d in interview.application.documents" :key="d.id" :title="d.original_name" :subtitle="d.type?.name">
                <template #append>
                  <v-btn :href="route('recruitment.applicants.documents.download', [interview.application.applicant_id, d.id])" icon="mdi-download" size="small" variant="text" />
                </template>
              </v-list-item>
            </v-list>
            <v-card-text v-else class="text-medium-emphasis">No documents.</v-card-text>
          </v-card>

          <v-card variant="outlined" class="rounded-lg">
            <v-card-title class="text-subtitle-1 font-weight-bold border-b">Schedule history</v-card-title>
            <v-card-text>
              <div v-if="!interview.reschedules.length" class="text-medium-emphasis">Never rescheduled.</div>
              <v-timeline v-else density="compact" side="end" align="start">
                <v-timeline-item v-for="r in interview.reschedules" :key="r.id" dot-color="primary" size="small">
                  <div class="text-body-2">{{ interviewWhen({ starts_at: r.previous_starts_at, ends_at: r.previous_ends_at }) }}</div>
                  <div class="text-body-2 font-weight-medium">→ {{ interviewWhen({ starts_at: r.new_starts_at, ends_at: r.new_ends_at }) }}</div>
                  <div class="text-caption text-medium-emphasis">{{ formatDateTime(r.rescheduled_at) }} · {{ r.actor?.username }}</div>
                  <div v-if="r.reason" class="text-body-2">"{{ r.reason }}"</div>
                </v-timeline-item>
              </v-timeline>
            </v-card-text>
          </v-card>
        </v-col>
      </v-row>

      <InterviewFormDialog v-if="can.update" v-model="editDialog" :interview="interview" :interview-types="interviewTypes" :employees="employees" />

      <v-dialog v-model="action.open" max-width="500">
        <v-card>
          <v-card-title class="pa-4">{{ actionTitle }}</v-card-title>
          <v-card-text>
            <template v-if="action.name === 'reschedule'">
              <v-row density="compact">
                <v-col cols="12" md="4"><v-text-field v-model="actionForm.scheduled_date" type="date" label="Date *" variant="outlined" density="compact" :error-messages="actionForm.errors.scheduled_date" /></v-col>
                <v-col cols="6" md="4"><v-text-field v-model="actionForm.start_time" type="time" label="Start *" variant="outlined" density="compact" :error-messages="actionForm.errors.start_time" /></v-col>
                <v-col cols="6" md="4"><v-text-field v-model.number="actionForm.duration_minutes" type="number" min="15" max="480" label="Minutes *" variant="outlined" density="compact" :error-messages="actionForm.errors.duration_minutes" /></v-col>
              </v-row>
              <v-alert v-if="actionForm.errors.panelist_ids" type="error" variant="tonal" density="compact" class="mb-3">{{ actionForm.errors.panelist_ids }}</v-alert>
              <v-textarea v-model="actionForm.reason" label="Reason (optional)" rows="2" variant="outlined" density="compact" />
            </template>
            <v-textarea v-else-if="action.name === 'cancel'" v-model="actionForm.reason" label="Reason (optional)" rows="3" variant="outlined" density="compact" :error-messages="actionForm.errors.reason" />
            <v-textarea v-else v-model="actionForm.remarks" label="Remarks (optional)" rows="3" variant="outlined" density="compact" :error-messages="actionForm.errors.remarks" />
            <v-alert v-if="actionForm.errors.status" type="error" variant="tonal" density="compact">{{ actionForm.errors.status }}</v-alert>
          </v-card-text>
          <v-card-actions class="pa-4">
            <v-spacer />
            <v-btn variant="text" @click="action.open = false">Back</v-btn>
            <v-btn :color="action.name === 'cancel' ? 'error' : 'primary'" :loading="actionForm.processing" @click="runAction">{{ actionTitle }}</v-btn>
          </v-card-actions>
        </v-card>
      </v-dialog>
    </v-container>
  </SidebarLayout>
</template>

<script>
import { Head, router, useForm } from "@inertiajs/vue3";
import SidebarLayout from "@/layouts/SidebarLayout.vue";
import InterviewFormDialog from "@/components/Recruitment/InterviewFormDialog.vue";
import {
  INTERVIEW_MODES,
  INTERVIEW_STATUS,
  RECOMMENDATIONS,
  averageRating,
  applicantName,
  formatDateTime,
  interviewModeLabel,
  interviewWhen,
  localDateParts,
  personName,
  statusInfo,
} from "@/utils/recruitment";

export default {
  name: "InterviewShow",
  components: { SidebarLayout, Head, InterviewFormDialog },
  props: {
    interview: { type: Object, required: true },
    progress: { type: Object, default: () => ({ panelists: 0, submitted: 0, drafts: 0 }) },
    myScorecard: { type: Object, default: null },
    scorecards: { type: Array, default: () => [] },
    criteria: { type: Array, default: () => [] },
    ratingScale: { type: Object, default: () => ({}) },
    interviewTypes: { type: Array, default: () => [] },
    employees: { type: Array, default: () => [] },
    can: { type: Object, default: () => ({}) },
  },
  data() {
    return {
      recommendations: RECOMMENDATIONS,
      editDialog: false,
      saving: null,
      action: { open: false, name: null },
      actionForm: useForm({ scheduled_date: "", start_time: "", duration_minutes: 60, reason: "", remarks: "" }),
      scoreForm: useForm(this.scorecardState()),
    };
  },
  computed: {
    status() {
      return statusInfo(INTERVIEW_STATUS, this.interview.status);
    },
    modeIcon() {
      return INTERVIEW_MODES[this.interview.mode]?.icon || "mdi-calendar";
    },
    submitted() {
      return this.myScorecard?.status === "submitted";
    },
    readOnly() {
      return this.submitted || !this.can.evaluate;
    },
    // Active criteria, plus any already on the scorecard (e.g. deactivated since).
    scoredCriteria() {
      const extra = (this.myScorecard?.scores || [])
        .filter((s) => !this.criteria.some((c) => c.id === s.evaluation_criterion_id))
        .map((s) => ({ id: s.evaluation_criterion_id, name: s.criterion_name }));
      return [...this.criteria, ...extra];
    },
    scoreError() {
      const e = this.scoreForm.errors;
      const key = Object.keys(e).find((k) => k === "evaluation" || k.startsWith("scores"));
      return key ? e[key] : null;
    },
    pageError() {
      const e = this.$page.props.errors || {};
      return this.action.open ? null : e.status || null;
    },
    actionTitle() {
      return { reschedule: "Reschedule interview", cancel: "Cancel interview", complete: "Mark completed" }[this.action.name] || "";
    },
  },
  watch: {
    myScorecard() {
      Object.assign(this.scoreForm, this.scorecardState());
    },
  },
  methods: {
    applicantName,
    formatDateTime,
    interviewWhen,
    interviewModeLabel,
    personName,
    averageRating,
    recommendation(value) {
      return statusInfo(RECOMMENDATIONS, value);
    },
    scorecardState() {
      const s = this.myScorecard;
      const ratings = {};
      const notes = {};
      (s?.scores || []).forEach((sc) => {
        ratings[sc.evaluation_criterion_id] = sc.rating;
        notes[sc.evaluation_criterion_id] = sc.comments || "";
      });
      return { ratings, notes, recommendation: s?.recommendation || null, comments: s?.comments || "" };
    },
    saveScorecard(mode) {
      this.saving = mode;
      const routeName = mode === "submit" ? "recruitment.interviews.evaluation.submit" : "recruitment.interviews.evaluation.save";
      const form = this.scoreForm.transform((d) => ({
        recommendation: d.recommendation,
        comments: d.comments,
        scores: Object.entries(d.ratings)
          .filter(([, rating]) => rating)
          .map(([id, rating]) => ({ evaluation_criterion_id: Number(id), rating, comments: d.notes[id] || null })),
      }));
      const options = { preserveScroll: true, onFinish: () => (this.saving = null) };
      mode === "submit" ? form.post(route(routeName, this.interview.id), options) : form.put(route(routeName, this.interview.id), options);
    },
    openAction(name) {
      const local = localDateParts(this.interview.starts_at);
      this.actionForm.clearErrors();
      Object.assign(this.actionForm, { scheduled_date: local.date, start_time: local.time, duration_minutes: this.interview.duration_minutes, reason: "", remarks: "" });
      this.action = { open: true, name };
    },
    runAction() {
      const name = this.action.name;
      const options = { preserveScroll: true, onSuccess: () => (this.action.open = false) };
      const payload = {
        reschedule: (d) => ({ scheduled_date: d.scheduled_date, start_time: d.start_time, duration_minutes: d.duration_minutes, reason: d.reason || null }),
        cancel: (d) => ({ reason: d.reason || null }),
        complete: (d) => ({ remarks: d.remarks || null }),
      }[name];
      this.actionForm.transform(payload).post(route(`recruitment.interviews.${name}`, this.interview.id), options);
    },
    go(url) {
      router.visit(url);
    },
  },
};
</script>
