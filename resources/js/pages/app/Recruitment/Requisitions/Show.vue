<template>
  <SidebarLayout>
    <Head :title="requisition.requisition_number" />
    <v-container fluid class="pa-6">
      <div class="d-flex flex-wrap align-center justify-space-between ga-3 mb-4">
        <div>
          <v-btn variant="text" size="small" prepend-icon="mdi-arrow-left" class="px-0 mb-1" @click="go(route('recruitment.requisitions.index'))">Job Requisitions</v-btn>
          <h1 class="text-h5 font-weight-bold d-flex align-center ga-3">
            {{ requisition.requisition_number }}
            <v-chip size="small" variant="tonal" :color="status.color">{{ status.label }}</v-chip>
          </h1>
          <p class="text-body-2 text-medium-emphasis">{{ requisition.job_title?.job_title }} · {{ requisition.department?.name }}</p>
        </div>
        <div class="d-flex flex-wrap ga-2">
          <v-btn v-if="can.update" variant="outlined" prepend-icon="mdi-pencil-outline" @click="go(route('recruitment.requisitions.edit', requisition.id))">Edit</v-btn>
          <v-btn v-if="can.submit" color="primary" prepend-icon="mdi-send-outline" @click="openDialog('submit')">Submit for approval</v-btn>
          <v-btn v-if="can.approve" color="success" prepend-icon="mdi-check" @click="openDialog('approve')">Approve</v-btn>
          <v-btn v-if="can.approve" color="error" variant="outlined" prepend-icon="mdi-close" @click="openDialog('reject')">Reject</v-btn>
          <v-btn v-if="can.createVacancy" color="primary" prepend-icon="mdi-briefcase-plus-outline" @click="go(route('recruitment.vacancies.create', { requisition: requisition.id }))">Create vacancy</v-btn>
          <v-btn v-if="can.cancel" color="error" variant="text" @click="openDialog('cancel')">Cancel requisition</v-btn>
        </div>
      </div>

      <v-alert v-if="errorMessage" type="error" variant="tonal" class="mb-4">{{ errorMessage }}</v-alert>

      <v-row>
        <v-col cols="12" lg="7">
          <v-card variant="outlined" class="rounded-lg mb-4">
            <v-card-title class="text-subtitle-1 font-weight-bold border-b">Position</v-card-title>
            <v-card-text>
              <v-row density="compact">
                <v-col v-for="item in details" :key="item.label" cols="12" sm="6">
                  <div class="text-caption text-medium-emphasis">{{ item.label }}</div>
                  <div class="text-body-1">{{ item.value }}</div>
                </v-col>
              </v-row>
            </v-card-text>
          </v-card>

          <v-card variant="outlined" class="rounded-lg mb-4">
            <v-card-title class="text-subtitle-1 font-weight-bold border-b">Justification</v-card-title>
            <v-card-text class="text-body-1" style="white-space: pre-line">{{ requisition.justification }}</v-card-text>
          </v-card>

          <v-card v-if="requisition.vacancies?.length" variant="outlined" class="rounded-lg mb-4">
            <v-card-title class="text-subtitle-1 font-weight-bold border-b">Vacancies ({{ allocatedOpenings }} of {{ requisition.positions }} positions allocated)</v-card-title>
            <v-list density="compact">
              <v-list-item v-for="v in requisition.vacancies" :key="v.id" :title="`${v.vacancy_number} · ${v.title}`" :subtitle="`${v.openings} opening(s)`" @click="go(route('recruitment.vacancies.show', v.id))">
                <template #append>
                  <v-chip size="x-small" variant="tonal" :color="vacancyStatus(v.status).color">{{ vacancyStatus(v.status).label }}</v-chip>
                </template>
              </v-list-item>
            </v-list>
          </v-card>
        </v-col>

        <v-col cols="12" lg="5">
          <v-card variant="outlined" class="rounded-lg mb-4">
            <v-card-title class="text-subtitle-1 font-weight-bold border-b">Approval</v-card-title>
            <v-card-text>
              <div v-if="!requisition.approvals?.length" class="text-medium-emphasis">
                The approval chain is set when the requisition is submitted.
              </div>
              <v-timeline v-else density="compact" side="end" align="start">
                <v-timeline-item v-for="a in requisition.approvals" :key="a.id" :dot-color="approvalStatus(a.status).color" size="small">
                  <div class="d-flex align-center ga-2">
                    <strong>{{ a.approver_name }}</strong>
                    <v-chip size="x-small" variant="tonal" :color="approvalStatus(a.status).color">{{ approvalStatus(a.status).label }}</v-chip>
                    <v-chip v-if="isCurrent(a)" size="x-small" color="primary" variant="outlined">Current step</v-chip>
                  </div>
                  <div class="text-caption text-medium-emphasis">
                    Step {{ a.approval_order }} · {{ approverType(a.approver_type) }}<span v-if="!a.is_required"> · optional</span>
                    <span v-if="a.acted_at"> · {{ formatDateTime(a.acted_at) }}</span>
                  </div>
                  <div v-if="a.remarks" class="text-body-2 mt-1">"{{ a.remarks }}"</div>
                </v-timeline-item>
              </v-timeline>
              <div v-if="requisition.approvals?.length" class="text-caption text-medium-emphasis mt-2">Workflow: {{ requisition.approvals[0].workflow_name }}</div>
            </v-card-text>
          </v-card>

          <v-card variant="outlined" class="rounded-lg">
            <v-card-title class="text-subtitle-1 font-weight-bold border-b">History</v-card-title>
            <v-list density="compact">
              <v-list-item title="Created" :subtitle="`${formatDateTime(requisition.created_at)}${requisition.creator ? ' · ' + requisition.creator.username : ''}`" />
              <v-list-item v-if="requisition.submitted_at" title="Submitted" :subtitle="formatDateTime(requisition.submitted_at)" />
              <v-list-item v-if="requisition.approved_at" title="Approved" :subtitle="formatDateTime(requisition.approved_at)" />
              <v-list-item v-if="requisition.rejected_at" title="Rejected" :subtitle="formatDateTime(requisition.rejected_at)" />
              <v-list-item v-if="requisition.cancelled_at" title="Cancelled" :subtitle="`${formatDateTime(requisition.cancelled_at)} · ${requisition.cancellation_reason || ''}`" />
            </v-list>
          </v-card>
        </v-col>
      </v-row>

      <v-dialog v-model="dialog.open" max-width="520">
        <v-card>
          <v-card-title class="pa-4">{{ dialogTitle }}</v-card-title>
          <v-card-text>
            <p v-if="dialog.action === 'submit'" class="mb-0">The approval chain will be fixed from the current workflow. You can't edit the requisition after submitting.</p>
            <v-textarea v-else v-model="dialog.text" :label="dialogLabel" rows="3" variant="outlined" density="compact" :error-messages="dialog.errors" />
          </v-card-text>
          <v-card-actions class="pa-4">
            <v-spacer />
            <v-btn variant="text" @click="dialog.open = false">Back</v-btn>
            <v-btn :color="dialog.action === 'reject' || dialog.action === 'cancel' ? 'error' : 'primary'" :loading="dialog.busy" @click="confirm">{{ dialogTitle }}</v-btn>
          </v-card-actions>
        </v-card>
      </v-dialog>
    </v-container>
  </SidebarLayout>
</template>

<script>
import { Head, router } from "@inertiajs/vue3";
import SidebarLayout from "@/layouts/SidebarLayout.vue";
import {
  REQUISITION_STATUS, APPROVAL_STATUS, VACANCY_STATUS, REASON_LABELS, APPROVER_TYPE_LABELS,
  statusInfo, personName, formatDate, formatDateTime,
} from "@/utils/recruitment";

const ACTIONS = {
  submit: { title: "Submit for approval", route: "recruitment.requisitions.submit" },
  approve: { title: "Approve", route: "recruitment.requisitions.approve", field: "remarks", label: "Remarks (optional)" },
  reject: { title: "Reject", route: "recruitment.requisitions.reject", field: "remarks", label: "Reason for rejection *" },
  cancel: { title: "Cancel requisition", route: "recruitment.requisitions.cancel", field: "reason", label: "Reason for cancelling *" },
};

export default {
  name: "RequisitionShow",
  components: { SidebarLayout, Head },
  props: {
    requisition: { type: Object, required: true },
    allocatedOpenings: { type: Number, default: 0 },
    can: { type: Object, default: () => ({}) },
  },
  data() {
    return { dialog: { open: false, action: null, text: "", errors: [], busy: false } };
  },
  computed: {
    status() {
      return statusInfo(REQUISITION_STATUS, this.requisition.status);
    },
    errorMessage() {
      const e = this.$page.props.errors || {};
      return e.status || e.approval || null;
    },
    dialogTitle() {
      return ACTIONS[this.dialog.action]?.title || "";
    },
    dialogLabel() {
      return ACTIONS[this.dialog.action]?.label || "";
    },
    details() {
      const r = this.requisition;
      return [
        { label: "Job title", value: r.job_title?.job_title || "—" },
        { label: "Department", value: r.department?.name || "—" },
        { label: "Location", value: r.location ? [r.location.address, r.location.city].filter(Boolean).join(", ") : "—" },
        { label: "Employment status", value: r.employment_status?.name || "—" },
        { label: "Positions", value: r.positions },
        { label: "Reason", value: REASON_LABELS[r.reason] || r.reason },
        ...(r.reason === "replacement" ? [{ label: "Replacing", value: personName(r.replaced_employee) }] : []),
        { label: "Target start date", value: formatDate(r.target_start_date) },
        { label: "Requested by", value: personName(r.requested_by) },
      ];
    },
  },
  methods: {
    formatDateTime,
    approvalStatus(value) {
      return statusInfo(APPROVAL_STATUS, value);
    },
    vacancyStatus(value) {
      return statusInfo(VACANCY_STATUS, value);
    },
    approverType(value) {
      return APPROVER_TYPE_LABELS[value] || value;
    },
    isCurrent(approval) {
      if (this.requisition.status !== "pending_approval" || approval.status !== "pending") return false;
      const firstPending = this.requisition.approvals.find((a) => a.status === "pending");
      return firstPending && firstPending.id === approval.id;
    },
    openDialog(action) {
      this.dialog = { open: true, action, text: "", errors: [], busy: false };
    },
    confirm() {
      const action = ACTIONS[this.dialog.action];
      const data = action.field ? { [action.field]: this.dialog.text } : {};
      this.dialog.busy = true;
      router.post(route(action.route, this.requisition.id), data, {
        preserveScroll: true,
        onSuccess: () => (this.dialog.open = false),
        onError: (errors) => {
          this.dialog.errors = action.field && errors[action.field] ? [errors[action.field]] : [];
          if (!this.dialog.errors.length) this.dialog.open = false;
        },
        onFinish: () => (this.dialog.busy = false),
      });
    },
    go(url) {
      router.visit(url);
    },
  },
};
</script>
