<template>
  <SidebarLayout>
    <Head :title="application.application_number" />
    <v-container fluid class="pa-6">
      <div class="d-flex flex-wrap align-center justify-space-between ga-3 mb-4">
        <div>
          <v-btn variant="text" size="small" prepend-icon="mdi-arrow-left" class="px-0 mb-1" @click="go(route('recruitment.vacancies.show', application.vacancy.id))">{{ application.vacancy.title }}</v-btn>
          <h1 class="text-h5 font-weight-bold d-flex align-center ga-3">
            {{ applicantName(application.applicant) }}
            <v-chip size="small" variant="tonal" :color="status.color">{{ status.label }}</v-chip>
          </h1>
          <p class="text-body-2 text-medium-emphasis">{{ application.application_number }} · {{ application.vacancy.vacancy_number }} · applied {{ formatDateTime(application.applied_at) }}</p>
        </div>
        <div class="d-flex flex-wrap ga-2">
          <v-btn v-if="can.advance" color="primary" prepend-icon="mdi-arrow-right" :disabled="!!nextStageBlocker" @click="openDialog('advance')">Move to {{ nextStage?.name }}</v-btn>
          <v-btn v-if="can.shortlist" color="success" prepend-icon="mdi-star-outline" :disabled="screening?.result !== 'passed'" @click="openDialog('shortlist')">Shortlist</v-btn>
          <v-btn v-if="can.reject" color="error" variant="outlined" prepend-icon="mdi-close" @click="openDialog('reject')">Reject</v-btn>
          <v-btn v-if="can.withdraw" variant="text" @click="openDialog('withdraw')">Withdraw</v-btn>
        </div>
      </div>

      <v-alert v-if="pageError" type="error" variant="tonal" class="mb-4">{{ pageError }}</v-alert>
      <v-alert v-if="can.advance && nextStageBlocker" type="info" variant="tonal" density="compact" class="mb-4">{{ nextStageBlocker }}</v-alert>
      <v-alert v-if="application.status === 'rejected'" type="error" variant="tonal" class="mb-4">
        Rejected {{ formatDateTime(application.rejected_at) }}: {{ application.rejection_reason?.name }}<span v-if="application.rejection_remarks"> – {{ application.rejection_remarks }}</span>
      </v-alert>
      <v-alert v-if="application.status === 'withdrawn'" type="info" variant="tonal" class="mb-4">
        Withdrawn {{ formatDateTime(application.withdrawn_at) }}: {{ application.withdrawal_reason }}
      </v-alert>

      <!-- Stage progress -->
      <v-card variant="outlined" class="rounded-lg mb-4 pa-4">
        <div class="d-flex flex-wrap align-center ga-2">
          <template v-for="(s, i) in stages" :key="s.id">
            <v-chip :color="stageColor(s)" :variant="s.id === application.current_vacancy_stage_id ? 'flat' : 'tonal'" size="large">{{ s.name }}</v-chip>
            <v-icon v-if="i < stages.length - 1" icon="mdi-chevron-right" color="medium-emphasis" />
          </template>
        </div>
      </v-card>

      <v-row>
        <v-col cols="12" lg="7">
          <v-card variant="outlined" class="rounded-lg mb-4">
            <v-card-title class="d-flex align-center justify-space-between text-subtitle-1 font-weight-bold border-b">
              Applicant
              <v-btn v-if="can.viewApplicant" size="small" variant="text" append-icon="mdi-open-in-new" @click="go(route('recruitment.applicants.show', application.applicant.id))">Profile</v-btn>
            </v-card-title>
            <v-card-text>
              <v-row density="compact">
                <v-col cols="12" sm="6"><div class="text-caption text-medium-emphasis">Email</div>{{ application.applicant.email || "—" }}</v-col>
                <v-col cols="12" sm="6"><div class="text-caption text-medium-emphasis">Phone</div>{{ application.applicant.phone || "—" }}</v-col>
                <v-col cols="12" sm="6"><div class="text-caption text-medium-emphasis">Applicant number</div>{{ application.applicant.applicant_number }}</v-col>
                <v-col cols="12" sm="6"><div class="text-caption text-medium-emphasis">Source</div>{{ application.source?.name || "—" }}</v-col>
              </v-row>
            </v-card-text>
          </v-card>

          <!-- Screening -->
          <v-card v-if="can.viewScreening || can.screen" variant="outlined" class="rounded-lg mb-4">
            <v-card-title class="text-subtitle-1 font-weight-bold border-b">Screening</v-card-title>
            <v-card-text>
              <div v-if="screening" class="mb-3">
                <v-chip size="small" variant="tonal" :color="screening.result === 'passed' ? 'success' : 'error'">{{ screening.result === "passed" ? "Passed" : "Failed" }}</v-chip>
                <span class="text-body-2 text-medium-emphasis ml-2">{{ formatDate(screening.screened_on) }} · {{ screening.screener?.username }}</span>
                <div v-if="screening.rejection_reason" class="text-body-2 mt-1">Reason: {{ screening.rejection_reason.name }}</div>
                <div v-if="screening.remarks" class="text-body-1 mt-2" style="white-space: pre-line">{{ screening.remarks }}</div>
              </div>
              <div v-else-if="!can.screen" class="text-medium-emphasis">Not screened yet.</div>

              <template v-if="can.screen">
                <v-divider v-if="screening" class="mb-3" />
                <v-radio-group v-model="screenForm.result" inline hide-details class="mb-2">
                  <v-radio label="Pass" value="passed" color="success" />
                  <v-radio label="Fail" value="failed" color="error" />
                </v-radio-group>
                <v-row density="compact">
                  <v-col cols="12" md="4"><v-text-field v-model="screenForm.screened_on" type="date" label="Screening date *" variant="outlined" density="compact" :error-messages="screenForm.errors.screened_on" /></v-col>
                  <v-col v-if="screenForm.result === 'failed'" cols="12" md="8">
                    <v-select v-model="screenForm.rejection_reason_id" :items="rejectionReasons" item-title="name" item-value="id" label="Rejection reason *" variant="outlined" density="compact" :error-messages="screenForm.errors.rejection_reason_id" />
                  </v-col>
                </v-row>
                <v-textarea v-model="screenForm.remarks" label="Screening remarks" rows="2" variant="outlined" density="compact" :error-messages="screenForm.errors.remarks || screenForm.errors.result" />
                <div class="d-flex justify-end">
                  <v-btn :color="screenForm.result === 'failed' ? 'error' : 'primary'" :loading="screenForm.processing" :disabled="!screenForm.result" @click="saveScreening">
                    {{ screenForm.result === "failed" ? "Fail and reject" : screening ? "Update screening" : "Record screening" }}
                  </v-btn>
                </div>
              </template>
            </v-card-text>
          </v-card>

          <!-- Interviews -->
          <v-card v-if="interviews" variant="outlined" class="rounded-lg mb-4">
            <v-card-title class="d-flex align-center justify-space-between text-subtitle-1 font-weight-bold border-b">
              Interviews
              <v-btn v-if="can.scheduleInterview" size="small" color="primary" variant="tonal" prepend-icon="mdi-calendar-plus" @click="interviewDialog = true">Schedule</v-btn>
            </v-card-title>
            <v-list v-if="interviews.length" density="compact" lines="two">
              <v-list-item v-for="i in interviews" :key="i.id" @click="go(route('recruitment.interviews.show', i.id))">
                <v-list-item-title class="d-flex align-center ga-2">
                  <span class="font-weight-medium">Round {{ i.round }} · {{ i.type?.name }}</span>
                  <v-chip size="x-small" variant="tonal" :color="interviewStatus(i.status).color">{{ interviewStatus(i.status).label }}</v-chip>
                </v-list-item-title>
                <v-list-item-subtitle>
                  {{ interviewWhen(i) }} · {{ interviewModeLabel(i.mode) }} · {{ i.panelists.map((p) => personName(p.employee)).join(", ") }}
                  <span v-if="i.status === 'completed'"> · scorecards {{ i.submitted_evaluations_count }} of {{ i.panelists.length }}</span>
                </v-list-item-subtitle>
                <template #append><v-icon icon="mdi-chevron-right" /></template>
              </v-list-item>
            </v-list>
            <v-card-text v-else class="text-medium-emphasis">
              {{ can.scheduleInterview ? "No interviews yet." : "No interviews yet. Interviews are scheduled once the application reaches the Interview stage." }}
            </v-card-text>
          </v-card>

          <!-- Assessments -->
          <v-card v-if="assessments" variant="outlined" class="rounded-lg mb-4">
            <v-card-title class="d-flex align-center justify-space-between text-subtitle-1 font-weight-bold border-b">
              Assessments
              <v-btn v-if="can.createAssessment" size="small" color="primary" variant="tonal" prepend-icon="mdi-plus" @click="openAssessment('create')">Add</v-btn>
            </v-card-title>
            <v-table v-if="assessments.length" density="compact">
              <thead>
                <tr><th>Assessment</th><th>Status</th><th>Result</th><th>Assessor</th><th class="text-end"></th></tr>
              </thead>
              <tbody>
                <tr v-for="a in assessments" :key="a.id">
                  <td>
                    <div class="font-weight-medium">{{ a.type?.name }}</div>
                    <div class="text-caption text-medium-emphasis">{{ a.scheduled_at ? formatDateTime(a.scheduled_at) : "Not scheduled" }}</div>
                    <div v-if="a.remarks" class="text-caption">{{ a.remarks }}</div>
                    <div v-if="a.status === 'cancelled'" class="text-caption text-medium-emphasis">Cancelled {{ formatDateTime(a.cancelled_at) }} · {{ a.canceller?.username }}<span v-if="a.cancellation_reason">: {{ a.cancellation_reason }}</span></div>
                  </td>
                  <td><v-chip size="small" variant="tonal" :color="assessmentStatus(a.status).color">{{ assessmentStatus(a.status).label }}</v-chip></td>
                  <td class="text-no-wrap">{{ assessmentResult(a) }}</td>
                  <td>{{ a.assessor ? personName(a.assessor) : "—" }}</td>
                  <td class="text-end text-no-wrap">
                    <template v-if="a.can_update">
                      <v-btn v-if="a.status === 'scheduled'" size="small" variant="text" @click="startAssessment(a)">Start</v-btn>
                      <v-btn size="small" variant="text" color="success" @click="openAssessment('complete', a)">Complete</v-btn>
                      <v-btn icon="mdi-pencil-outline" size="small" variant="text" title="Edit" @click="openAssessment('edit', a)" />
                      <v-btn icon="mdi-close-circle-outline" size="small" variant="text" title="Cancel" @click="openAssessment('cancel', a)" />
                    </template>
                  </td>
                </tr>
              </tbody>
            </v-table>
            <v-card-text v-else class="text-medium-emphasis">
              {{ can.createAssessment ? "No assessments yet." : "No assessments yet. Assessments are added once the application reaches the Assessment stage." }}
            </v-card-text>
          </v-card>

          <!-- Evaluations (submitted scorecards) -->
          <v-card v-if="evaluations" variant="outlined" class="rounded-lg mb-4">
            <v-card-title class="text-subtitle-1 font-weight-bold border-b">Evaluations</v-card-title>
            <v-card-text v-if="!evaluations.length" class="text-medium-emphasis">No submitted scorecards yet. Panelists submit them from the interview page.</v-card-text>
            <v-expansion-panels v-else variant="accordion" flat>
              <v-expansion-panel v-for="e in evaluations" :key="e.id">
                <v-expansion-panel-title>
                  <div class="d-flex flex-wrap align-center ga-2">
                    <span class="font-weight-medium">{{ personName(e.evaluator) }}</span>
                    <span class="text-medium-emphasis text-body-2">Round {{ e.interview?.round }} · {{ e.interview?.type?.name }}</span>
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
                        <td class="text-end text-no-wrap">{{ sc.rating }} · {{ ratingLabel(sc.rating) }}</td>
                      </tr>
                    </tbody>
                  </v-table>
                  <div v-if="e.comments" class="text-body-2 mt-2" style="white-space: pre-line">{{ e.comments }}</div>
                </v-expansion-panel-text>
              </v-expansion-panel>
            </v-expansion-panels>
          </v-card>

          <!-- Selection (a human decision at the Evaluation stage; no ranking) -->
          <v-card v-if="selection" variant="outlined" class="rounded-lg mb-4">
            <v-card-title class="d-flex align-center justify-space-between text-subtitle-1 font-weight-bold border-b">
              Selection
              <v-chip v-if="selection.current" size="small" variant="tonal" :color="decisionInfo(selection.current.decision).color">{{ decisionInfo(selection.current.decision).label }}</v-chip>
            </v-card-title>
            <v-card-text>
              <div v-if="selection.summary" class="d-flex flex-wrap ga-6 mb-3">
                <div><div class="text-h6 font-weight-bold">{{ selection.summary.completed_interviews }}</div><div class="text-caption text-medium-emphasis">Completed interviews</div></div>
                <div><div class="text-h6 font-weight-bold">{{ selection.summary.submitted_evaluations }}</div><div class="text-caption text-medium-emphasis">Submitted scorecards</div></div>
                <div><div class="text-h6 font-weight-bold">{{ selection.summary.average_rating ?? "—" }}</div><div class="text-caption text-medium-emphasis">Average rating (1–5)</div></div>
                <div>
                  <div class="text-body-2">
                    <span class="text-success">{{ selection.summary.recommend_count }} recommend</span> ·
                    <span>{{ selection.summary.neutral_count }} neutral</span> ·
                    <span class="text-error">{{ selection.summary.do_not_recommend_count }} do not recommend</span>
                  </div>
                  <div class="text-caption text-medium-emphasis">Panel recommendations</div>
                </div>
              </div>
              <div class="text-body-2 text-medium-emphasis mb-3">
                Vacancy openings: {{ selection.vacancy.filled_count }} of {{ selection.vacancy.openings }} taken by selected candidates ({{ selection.vacancy.remaining_openings }} remaining).
              </div>
              <v-alert v-if="selection.eligibilityProblem" type="info" variant="tonal" density="compact" class="mb-3">{{ selection.eligibilityProblem }}</v-alert>
              <v-alert v-else-if="selection.evaluationProblem" type="warning" variant="tonal" density="compact" class="mb-3">{{ selection.evaluationProblem }}</v-alert>
              <v-alert v-if="selectionError" type="error" variant="tonal" density="compact" class="mb-3">{{ selectionError }}</v-alert>

              <div v-if="can.decideSelection" class="mb-3">
                <v-textarea v-model="selectionForm.remarks" label="Decision remarks (required for not selected)" rows="2" variant="outlined" density="compact" :error-messages="selectionForm.errors.remarks" />
                <div class="d-flex justify-end ga-2">
                  <v-btn v-if="selection.current?.decision !== 'not_selected'" variant="outlined" :loading="selectionForm.processing && selectionForm.decision === 'not_selected'" @click="decide('not_selected')">Mark not selected</v-btn>
                  <v-btn
                    v-if="selection.current?.decision !== 'selected'"
                    color="success"
                    prepend-icon="mdi-check-decagram-outline"
                    :disabled="!!selection.evaluationProblem || selection.vacancy.remaining_openings < 1"
                    :loading="selectionForm.processing && selectionForm.decision === 'selected'"
                    @click="decide('selected')"
                  >Select candidate</v-btn>
                </div>
              </div>

              <div v-if="selection.history.length">
                <div class="text-caption text-medium-emphasis mb-1">Decision history</div>
                <v-timeline density="compact" side="end" align="start">
                  <v-timeline-item v-for="h in [...selection.history].reverse()" :key="h.id" :dot-color="decisionInfo(h.decision).color" size="small">
                    <div class="font-weight-medium">{{ decisionInfo(h.decision).label }}<span v-if="h.is_automatic" class="text-medium-emphasis"> (automatic)</span></div>
                    <div class="text-caption text-medium-emphasis">{{ formatDateTime(h.decided_at) }} · {{ h.decider?.username || "system" }} · {{ h.submitted_evaluations }} scorecard(s), avg {{ h.average_rating ?? "—" }}</div>
                    <div v-if="h.remarks" class="text-body-2">"{{ h.remarks }}"</div>
                  </v-timeline-item>
                </v-timeline>
              </div>
              <div v-else class="text-medium-emphasis">No selection decision yet.</div>
            </v-card-text>
          </v-card>

          <!-- Offers -->
          <v-card v-if="offers" variant="outlined" class="rounded-lg mb-4">
            <v-card-title class="d-flex align-center justify-space-between text-subtitle-1 font-weight-bold border-b">
              Offers
              <v-btn v-if="can.createOffer" size="small" color="primary" variant="tonal" prepend-icon="mdi-file-sign" @click="offerDialog = true">Prepare offer</v-btn>
            </v-card-title>
            <v-list v-if="offers.length" density="compact" lines="two">
              <v-list-item v-for="o in [...offers].reverse()" :key="o.id" @click="go(route('recruitment.offers.show', o.id))">
                <v-list-item-title class="d-flex align-center ga-2">
                  <span class="font-weight-medium">{{ o.offer_number }} · {{ o.position_title }}</span>
                  <v-chip size="x-small" variant="tonal" :color="offerStatus(o.status).color">{{ offerStatus(o.status).label }}</v-chip>
                </v-list-item-title>
                <v-list-item-subtitle>{{ money(o.base_salary, o.currency) }} {{ frequencyLabel(o.salary_frequency) }} · start {{ formatDate(o.proposed_start_date) }} · expires {{ formatDate(o.expiry_date) }}</v-list-item-subtitle>
                <template #append><v-icon icon="mdi-chevron-right" /></template>
              </v-list-item>
            </v-list>
            <v-card-text v-else class="text-medium-emphasis">No offers yet. An offer can be prepared once the candidate is selected.</v-card-text>
          </v-card>

          <ConversionPanel v-if="conversion" :conversion="conversion" :application="application" />

          <!-- Documents -->
          <v-card variant="outlined" class="rounded-lg mb-4">
            <v-card-title class="text-subtitle-1 font-weight-bold border-b">Submitted documents</v-card-title>
            <v-list v-if="application.documents.length" density="compact">
              <v-list-item v-for="d in application.documents" :key="d.id" :title="d.original_name" :subtitle="d.type?.name">
                <template #append>
                  <v-btn :href="route('recruitment.applicants.documents.download', [application.applicant.id, d.id])" icon="mdi-download" size="small" variant="text" />
                </template>
              </v-list-item>
            </v-list>
            <v-card-text v-else class="text-medium-emphasis">No documents submitted.</v-card-text>
            <v-card-text v-if="can.attachDocuments && attachableDocuments.length" class="pt-0">
              <div class="d-flex ga-2 align-center">
                <v-select v-model="attachForm.document_ids" :items="attachableDocuments" item-title="original_name" item-value="id" label="Attach the applicant's documents" multiple chips variant="outlined" density="compact" hide-details :error-messages="attachForm.errors.document_ids" />
                <v-btn color="primary" variant="tonal" :disabled="!attachForm.document_ids.length" :loading="attachForm.processing" @click="attach">Attach</v-btn>
              </div>
            </v-card-text>
          </v-card>
        </v-col>

        <v-col cols="12" lg="5">
          <v-card variant="outlined" class="rounded-lg mb-4">
            <v-card-title class="text-subtitle-1 font-weight-bold border-b">Stage history</v-card-title>
            <v-card-text>
              <v-timeline density="compact" side="end" align="start">
                <v-timeline-item v-for="h in application.history" :key="h.id" :dot-color="historyColor(h)" size="small">
                  <div class="font-weight-medium">{{ historyLabel(h) }}</div>
                  <div class="text-caption text-medium-emphasis">{{ formatDateTime(h.acted_at) }} · {{ h.actor?.username || "system" }}</div>
                  <div v-if="h.rejection_reason" class="text-body-2">Reason: {{ h.rejection_reason.name }}</div>
                  <div v-if="h.remarks" class="text-body-2">"{{ h.remarks }}"</div>
                </v-timeline-item>
              </v-timeline>
            </v-card-text>
          </v-card>

          <v-card variant="outlined" class="rounded-lg">
            <v-card-title class="text-subtitle-1 font-weight-bold border-b">Internal notes</v-card-title>
            <v-card-text>
              <p class="text-caption text-medium-emphasis mb-2">Visible to the recruitment team only.</p>
              <div v-if="can.addNote" class="mb-3">
                <v-textarea v-model="noteForm.body" label="Add a note" rows="2" variant="outlined" density="compact" :error-messages="noteForm.errors.body" />
                <div class="d-flex justify-end"><v-btn size="small" color="primary" :disabled="!noteForm.body" :loading="noteForm.processing" @click="addNote">Add note</v-btn></div>
              </div>
              <div v-for="n in application.notes" :key="n.id" class="mb-3">
                <div class="text-caption text-medium-emphasis">{{ n.author_name }} · {{ formatDateTime(n.created_at) }}</div>
                <div class="text-body-2" style="white-space: pre-line">{{ n.body }}</div>
              </div>
              <div v-if="!application.notes.length" class="text-medium-emphasis">No notes yet.</div>
            </v-card-text>
          </v-card>
        </v-col>
      </v-row>

      <v-dialog v-model="dialog.open" max-width="500">
        <v-card>
          <v-card-title class="pa-4">{{ dialogTitle }}</v-card-title>
          <v-card-text>
            <v-select v-if="dialog.action === 'reject'" v-model="dialog.rejection_reason_id" :items="rejectionReasons" item-title="name" item-value="id" label="Rejection reason *" variant="outlined" density="compact" class="mb-3" :error-messages="dialog.errors.rejection_reason_id" />
            <v-textarea v-model="dialog.text" :label="dialog.action === 'withdraw' ? 'Reason *' : 'Remarks'" rows="3" variant="outlined" density="compact" :error-messages="dialog.errors.reason || dialog.errors.remarks" />
          </v-card-text>
          <v-card-actions class="pa-4">
            <v-spacer />
            <v-btn variant="text" @click="dialog.open = false">Back</v-btn>
            <v-btn :color="dialog.action === 'reject' ? 'error' : 'primary'" :loading="dialog.busy" @click="confirm">{{ dialogTitle }}</v-btn>
          </v-card-actions>
        </v-card>
      </v-dialog>
      <OfferFormDialog v-if="can.createOffer && offerOptions" v-model="offerDialog" :application-id="application.id" :options="offerOptions" />

      <InterviewFormDialog v-if="can.scheduleInterview" v-model="interviewDialog" :application-id="application.id" :interview-types="interviewTypes" :employees="employees" />

      <v-dialog v-model="assessmentDialog.open" max-width="560">
        <v-card>
          <v-card-title class="pa-4">{{ assessmentTitle }}</v-card-title>
          <v-card-text>
            <v-alert v-if="assessmentForm.errors.status" type="error" variant="tonal" density="compact" class="mb-3">{{ assessmentForm.errors.status }}</v-alert>
            <template v-if="assessmentDialog.action === 'create' || assessmentDialog.action === 'edit'">
              <v-select v-if="assessmentDialog.action === 'create'" v-model="assessmentForm.assessment_type_id" :items="assessmentTypeItems" label="Assessment type *" variant="outlined" density="compact" :error-messages="assessmentForm.errors.assessment_type_id" />
              <v-text-field v-model="assessmentForm.scheduled_at" type="datetime-local" label="Scheduled for" variant="outlined" density="compact" :error-messages="assessmentForm.errors.scheduled_at" />
              <v-autocomplete v-model="assessmentForm.assessor_employee_id" :items="employees" label="Assessor" variant="outlined" density="compact" clearable :error-messages="assessmentForm.errors.assessor_employee_id" />
              <v-textarea v-model="assessmentForm.remarks" label="Remarks" rows="2" variant="outlined" density="compact" :error-messages="assessmentForm.errors.remarks" />
            </template>
            <template v-else-if="assessmentDialog.action === 'complete'">
              <p class="text-body-2 text-medium-emphasis mb-3">{{ assessmentDialog.item.result_type === "score" ? "Enter the score out of the maximum. Pass/fail is optional." : "Record whether the applicant passed. A score is optional." }}</p>
              <v-row density="compact">
                <v-col cols="6"><v-text-field v-model="assessmentForm.score" type="number" min="0" step="0.01" :label="assessmentDialog.item.result_type === 'score' ? 'Score *' : 'Score'" variant="outlined" density="compact" :error-messages="assessmentForm.errors.score" /></v-col>
                <v-col cols="6"><v-text-field v-model="assessmentForm.maximum_score" type="number" min="0" step="0.01" :label="assessmentDialog.item.result_type === 'score' ? 'Out of *' : 'Out of'" variant="outlined" density="compact" :error-messages="assessmentForm.errors.maximum_score" /></v-col>
              </v-row>
              <v-radio-group v-model="assessmentForm.passed" inline :label="assessmentDialog.item.result_type === 'pass_fail' ? 'Outcome *' : 'Outcome'" :error-messages="assessmentForm.errors.passed">
                <v-radio label="Passed" :value="true" color="success" />
                <v-radio label="Failed" :value="false" color="error" />
                <v-radio v-if="assessmentDialog.item.result_type === 'score'" label="Not recorded" :value="null" />
              </v-radio-group>
              <v-textarea v-model="assessmentForm.remarks" label="Remarks" rows="2" variant="outlined" density="compact" :error-messages="assessmentForm.errors.remarks" />
            </template>
            <template v-else-if="assessmentDialog.action === 'cancel'">
              <v-textarea v-model="assessmentForm.reason" label="Reason (optional)" rows="2" variant="outlined" density="compact" :error-messages="assessmentForm.errors.reason" />
            </template>
          </v-card-text>
          <v-card-actions class="pa-4">
            <v-spacer />
            <v-btn variant="text" @click="assessmentDialog.open = false">Back</v-btn>
            <v-btn :color="assessmentDialog.action === 'cancel' ? 'error' : 'primary'" :loading="assessmentForm.processing" @click="saveAssessment">{{ assessmentTitle }}</v-btn>
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
import OfferFormDialog from "@/components/Recruitment/OfferFormDialog.vue";
import ConversionPanel from "@/components/Recruitment/ConversionPanel.vue";
import {
  APPLICATION_STATUS,
  ASSESSMENT_STATUS,
  HISTORY_ACTION_LABELS,
  INTERVIEW_STATUS,
  OFFER_STATUS,
  SALARY_FREQUENCIES,
  SELECTION_DECISIONS,
  money,
  RATING_LABELS,
  RECOMMENDATIONS,
  averageRating,
  interviewModeLabel,
  interviewWhen,
  localDateParts,
  personName,
  statusInfo,
  applicantName,
  formatDate,
  formatDateTime,
} from "@/utils/recruitment";

export default {
  name: "ApplicationShow",
  components: { SidebarLayout, Head, InterviewFormDialog, OfferFormDialog, ConversionPanel },
  props: {
    application: { type: Object, required: true },
    stages: { type: Array, default: () => [] },
    nextStage: { type: Object, default: null },
    screening: { type: Object, default: null },
    rejectionReasons: { type: Array, default: () => [] },
    attachableDocuments: { type: Array, default: () => [] },
    nextStageBlocker: { type: String, default: null },
    // null when the user may not see that section.
    interviews: { type: Array, default: null },
    assessments: { type: Array, default: null },
    evaluations: { type: Array, default: null },
    interviewTypes: { type: Array, default: () => [] },
    assessmentTypes: { type: Array, default: () => [] },
    employees: { type: Array, default: () => [] },
    selection: { type: Object, default: null },
    offers: { type: Array, default: null },
    offerOptions: { type: Object, default: null },
    conversion: { type: Object, default: null },
    can: { type: Object, default: () => ({}) },
  },
  data() {
    const s = this.screening || {};
    return {
      dialog: { open: false, action: null, text: "", rejection_reason_id: null, errors: {}, busy: false },
      screenForm: useForm({
        result: s.result || null,
        screened_on: s.screened_on || new Date().toISOString().slice(0, 10),
        remarks: s.remarks || "",
        rejection_reason_id: s.rejection_reason_id || null,
      }),
      attachForm: useForm({ document_ids: [] }),
      noteForm: useForm({ body: "" }),
      interviewDialog: false,
      offerDialog: false,
      selectionForm: useForm({ decision: null, remarks: "" }),
      assessmentDialog: { open: false, action: null, item: null },
      assessmentForm: useForm({ assessment_type_id: null, scheduled_at: "", assessor_employee_id: null, remarks: "", score: null, maximum_score: null, passed: null, reason: "" }),
    };
  },
  computed: {
    status() {
      return statusInfo(APPLICATION_STATUS, this.application.status);
    },
    pageError() {
      const e = this.$page.props.errors || {};
      return e.stage || e.status || null;
    },
    selectionError() {
      return this.selectionForm.errors.decision || null;
    },
    assessmentTypeItems() {
      return this.assessmentTypes.map((t) => ({ value: t.id, title: `${t.name} (${t.result_type === "score" ? "score" : "pass/fail"})` }));
    },
    assessmentTitle() {
      return { create: "Add assessment", edit: "Edit assessment", complete: "Record result", cancel: "Cancel assessment" }[this.assessmentDialog.action] || "";
    },
    dialogTitle() {
      return { advance: `Move to ${this.nextStage?.name || "next stage"}`, shortlist: "Shortlist", reject: "Reject application", withdraw: "Withdraw application" }[this.dialog.action] || "";
    },
  },
  methods: {
    applicantName,
    formatDate,
    formatDateTime,
    personName,
    interviewWhen,
    interviewModeLabel,
    averageRating,
    money,
    decisionInfo(value) {
      return statusInfo(SELECTION_DECISIONS, value);
    },
    offerStatus(value) {
      return statusInfo(OFFER_STATUS, value);
    },
    frequencyLabel(value) {
      return SALARY_FREQUENCIES[value] || value;
    },
    decide(decision) {
      this.selectionForm.decision = decision;
      this.selectionForm.post(route("recruitment.applications.selection.store", this.application.id), {
        preserveScroll: true,
        onSuccess: () => this.selectionForm.reset(),
      });
    },
    interviewStatus(value) {
      return statusInfo(INTERVIEW_STATUS, value);
    },
    assessmentStatus(value) {
      return statusInfo(ASSESSMENT_STATUS, value);
    },
    recommendation(value) {
      return statusInfo(RECOMMENDATIONS, value);
    },
    ratingLabel(rating) {
      return RATING_LABELS[rating] || "";
    },
    assessmentResult(a) {
      if (a.status !== "completed") return "—";
      const parts = [];
      if (a.score !== null) parts.push(`${Number(a.score)} / ${Number(a.maximum_score)}`);
      if (a.passed !== null) parts.push(a.passed ? "Passed" : "Failed");
      return parts.join(" · ") || "—";
    },
    openAssessment(action, item = null) {
      const local = item?.scheduled_at ? localDateParts(item.scheduled_at) : null;
      this.assessmentForm.clearErrors();
      Object.assign(this.assessmentForm, {
        assessment_type_id: null,
        scheduled_at: local ? `${local.date}T${local.time}` : "",
        assessor_employee_id: item?.assessor_employee_id ?? null,
        remarks: item?.remarks ?? "",
        score: null,
        maximum_score: null,
        passed: null,
        reason: "",
      });
      this.assessmentDialog = { open: true, action, item };
    },
    saveAssessment() {
      const { action, item } = this.assessmentDialog;
      const options = { preserveScroll: true, onSuccess: () => (this.assessmentDialog.open = false) };
      const form = this.assessmentForm;
      if (action === "create") {
        form.transform((d) => ({ assessment_type_id: d.assessment_type_id, scheduled_at: d.scheduled_at || null, assessor_employee_id: d.assessor_employee_id, remarks: d.remarks }))
          .post(route("recruitment.applications.assessments.store", this.application.id), options);
      } else if (action === "edit") {
        form.transform((d) => ({ scheduled_at: d.scheduled_at || null, assessor_employee_id: d.assessor_employee_id, remarks: d.remarks }))
          .put(route("recruitment.assessments.update", item.id), options);
      } else if (action === "complete") {
        form.transform((d) => ({ score: d.score === "" ? null : d.score, maximum_score: d.maximum_score === "" ? null : d.maximum_score, passed: d.passed, remarks: d.remarks }))
          .post(route("recruitment.assessments.complete", item.id), options);
      } else if (action === "cancel") {
        form.transform((d) => ({ reason: d.reason })).post(route("recruitment.assessments.cancel", item.id), options);
      }
    },
    startAssessment(a) {
      router.post(route("recruitment.assessments.start", a.id), {}, { preserveScroll: true });
    },
    stageColor(stage) {
      const current = this.stages.find((s) => s.id === this.application.current_vacancy_stage_id);
      if (stage.id === current?.id) return this.application.status === "rejected" ? "error" : "primary";
      return current && stage.sort_order < current.sort_order ? "success" : "grey";
    },
    historyLabel(h) {
      const action = HISTORY_ACTION_LABELS[h.action] || h.action;
      if (h.action === "moved" || h.action === "shortlisted") return `${action}: ${h.from_stage_name} → ${h.to_stage_name}`;
      return h.to_stage_name ? `${action} (${h.to_stage_name})` : action;
    },
    historyColor(h) {
      return { rejected: "error", withdrawn: "grey", shortlisted: "success" }[h.action] || "primary";
    },
    openDialog(action) {
      this.dialog = { open: true, action, text: "", rejection_reason_id: null, errors: {}, busy: false };
    },
    confirm() {
      const id = this.application.id;
      const expected = this.application.current_vacancy_stage_id;
      const payload = {
        advance: [route("recruitment.applications.advance", id), { expected_stage_id: expected, remarks: this.dialog.text }],
        shortlist: [route("recruitment.applications.shortlist", id), { expected_stage_id: expected, remarks: this.dialog.text }],
        reject: [route("recruitment.applications.reject", id), { rejection_reason_id: this.dialog.rejection_reason_id, remarks: this.dialog.text }],
        withdraw: [route("recruitment.applications.withdraw", id), { reason: this.dialog.text }],
      }[this.dialog.action];
      this.dialog.busy = true;
      router.post(payload[0], payload[1], {
        preserveScroll: true,
        onSuccess: () => (this.dialog.open = false),
        onError: (errors) => {
          this.dialog.errors = errors;
          if (errors.stage || errors.status) this.dialog.open = false;
        },
        onFinish: () => (this.dialog.busy = false),
      });
    },
    saveScreening() {
      this.screenForm.put(route("recruitment.applications.screening", this.application.id), { preserveScroll: true });
    },
    attach() {
      this.attachForm.post(route("recruitment.applications.documents.attach", this.application.id), {
        preserveScroll: true,
        onSuccess: () => this.attachForm.reset(),
      });
    },
    addNote() {
      this.noteForm.post(route("recruitment.applications.notes.store", this.application.id), {
        preserveScroll: true,
        onSuccess: () => this.noteForm.reset(),
      });
    },
    go(url) {
      router.visit(url);
    },
  },
};
</script>
