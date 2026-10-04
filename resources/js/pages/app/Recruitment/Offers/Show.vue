<template>
  <SidebarLayout>
    <Head :title="offer.offer_number" />
    <v-container fluid class="pa-6" style="max-width: 1300px">
      <div class="d-flex flex-wrap align-center justify-space-between ga-3 mb-4">
        <div>
          <v-btn v-if="can.viewApplication" variant="text" size="small" prepend-icon="mdi-arrow-left" class="px-0 mb-1" @click="go(route('recruitment.applications.show', offer.application_id))">
            {{ offer.application?.application_number }}
          </v-btn>
          <v-btn v-else variant="text" size="small" prepend-icon="mdi-arrow-left" class="px-0 mb-1" @click="go(route('recruitment.offers.index'))">Offers</v-btn>
          <h1 class="text-h5 font-weight-bold d-flex align-center ga-3">
            {{ offer.offer_number }} · {{ applicantName(offer.applicant) }}
            <v-chip size="small" variant="tonal" :color="status.color">{{ status.label }}</v-chip>
          </h1>
          <p class="text-body-2 text-medium-emphasis">{{ offer.position_title }} · {{ offer.vacancy?.title }} ({{ offer.vacancy?.vacancy_number }})</p>
        </div>
        <div class="d-flex flex-wrap ga-2">
          <v-btn v-if="can.update" variant="outlined" prepend-icon="mdi-pencil-outline" @click="editDialog = true">Edit draft</v-btn>
          <v-btn v-if="can.submit" color="primary" prepend-icon="mdi-send-outline" @click="openAction('submit')">Submit for approval</v-btn>
          <v-btn v-if="can.approve" color="success" prepend-icon="mdi-check" @click="openAction('approve')">Approve</v-btn>
          <v-btn v-if="can.reject" color="error" variant="outlined" @click="openAction('reject')">Reject</v-btn>
          <v-btn v-if="can.issue" color="primary" prepend-icon="mdi-email-send-outline" @click="openAction('issue')">Issue offer</v-btn>
          <v-btn v-if="can.respond" color="success" prepend-icon="mdi-handshake-outline" @click="openAction('accept')">Candidate accepted</v-btn>
          <v-btn v-if="can.respond" color="error" variant="outlined" @click="openAction('decline')">Candidate rejected</v-btn>
          <v-btn v-if="can.withdraw" variant="text" color="error" @click="openAction('withdraw')">Withdraw</v-btn>
        </div>
      </div>

      <v-alert v-if="pageError" type="error" variant="tonal" class="mb-4">{{ pageError }}</v-alert>
      <v-alert v-if="offer.status === 'accepted' && conversion" type="success" variant="tonal" class="mb-4">
        Accepted {{ formatDateTime(offer.responded_at) }} · Converted {{ formatDateTime(conversion.converted_at) }}:
        {{ conversion.conversion_type === "new_employee" ? "employee" : "linked to employee" }} {{ conversion.employee_number }}.
      </v-alert>
      <v-alert v-else-if="offer.status === 'accepted'" type="success" variant="tonal" class="mb-4">
        Accepted {{ formatDateTime(offer.responded_at) }}. Convert the candidate to an employee from the application page.
      </v-alert>

      <v-row>
        <v-col cols="12" lg="7">
          <v-card variant="outlined" class="rounded-lg mb-4">
            <v-card-title class="text-subtitle-1 font-weight-bold border-b">Terms</v-card-title>
            <v-card-text>
              <v-row density="compact">
                <v-col cols="12" sm="6"><div class="text-caption text-medium-emphasis">Position</div>{{ offer.position_title }}</v-col>
                <v-col cols="12" sm="6"><div class="text-caption text-medium-emphasis">Department</div>{{ offer.department_name || "—" }}</v-col>
                <v-col cols="12" sm="6"><div class="text-caption text-medium-emphasis">Employment type</div>{{ offer.employment_type || "—" }}</v-col>
                <v-col cols="12" sm="6"><div class="text-caption text-medium-emphasis">Work location</div>{{ offer.work_location || "—" }}</v-col>
                <v-col cols="12" sm="6"><div class="text-caption text-medium-emphasis">Base salary</div>{{ money(offer.base_salary, offer.currency) }} {{ frequencyLabel(offer.salary_frequency) }}</v-col>
                <v-col cols="12" sm="6"><div class="text-caption text-medium-emphasis">Proposed start</div>{{ formatDate(offer.proposed_start_date) }}</v-col>
                <v-col cols="12" sm="6"><div class="text-caption text-medium-emphasis">Offer date</div>{{ offer.offer_date ? formatDate(offer.offer_date) : "Set when issued" }}</v-col>
                <v-col cols="12" sm="6"><div class="text-caption text-medium-emphasis">Expires</div>{{ formatDate(offer.expiry_date) }}</v-col>
                <v-col v-if="offer.benefits" cols="12"><div class="text-caption text-medium-emphasis">Benefits and allowances</div><div style="white-space: pre-line">{{ offer.benefits }}</div></v-col>
                <v-col v-if="offer.remarks" cols="12"><div class="text-caption text-medium-emphasis">Remarks</div><div style="white-space: pre-line">{{ offer.remarks }}</div></v-col>
              </v-row>
              <p v-if="offer.status !== 'draft'" class="text-caption text-medium-emphasis mt-3 mb-0">Terms are locked once an offer is submitted. To change them, withdraw this offer and prepare a new one.</p>
            </v-card-text>
          </v-card>

          <v-card variant="outlined" class="rounded-lg mb-4">
            <v-card-title class="text-subtitle-1 font-weight-bold border-b">Candidate</v-card-title>
            <v-card-text>
              <v-row density="compact">
                <v-col cols="12" sm="6"><div class="text-caption text-medium-emphasis">Name</div>{{ applicantName(offer.applicant) }}</v-col>
                <v-col cols="12" sm="6"><div class="text-caption text-medium-emphasis">Applicant number</div>{{ offer.applicant?.applicant_number }}</v-col>
                <v-col cols="12" sm="6"><div class="text-caption text-medium-emphasis">Email</div>{{ offer.applicant?.email || "—" }}</v-col>
                <v-col cols="12" sm="6"><div class="text-caption text-medium-emphasis">Phone</div>{{ offer.applicant?.phone || "—" }}</v-col>
                <v-col cols="12" sm="6">
                  <div class="text-caption text-medium-emphasis">Response</div>
                  <template v-if="offer.response">{{ offer.response === "accepted" ? "Accepted" : "Rejected" }} · {{ formatDateTime(offer.responded_at) }}</template>
                  <template v-else>—</template>
                </v-col>
                <v-col v-if="offer.response_remarks" cols="12" sm="6"><div class="text-caption text-medium-emphasis">Response remarks</div>{{ offer.response_remarks }}</v-col>
                <v-col v-if="offer.status === 'withdrawn'" cols="12"><div class="text-caption text-medium-emphasis">Withdrawn</div>{{ formatDateTime(offer.withdrawn_at) }}: {{ offer.withdrawal_reason }}</v-col>
              </v-row>
            </v-card-text>
          </v-card>
        </v-col>

        <v-col cols="12" lg="5">
          <v-card variant="outlined" class="rounded-lg mb-4">
            <v-card-title class="text-subtitle-1 font-weight-bold border-b">Approval</v-card-title>
            <v-card-text>
              <div v-if="!offer.approvals.length" class="text-medium-emphasis">The approval chain is fixed when the offer is submitted.</div>
              <v-timeline v-else density="compact" side="end" align="start">
                <v-timeline-item v-for="a in offer.approvals" :key="a.id" :dot-color="approvalColor(a)" size="small">
                  <div class="d-flex align-center ga-2">
                    <span class="font-weight-medium">{{ a.approver_name }}</span>
                    <v-chip size="x-small" variant="tonal" :color="approvalColor(a)">{{ isCurrent(a) ? "Current step" : approvalLabel(a.status) }}</v-chip>
                  </div>
                  <div class="text-caption text-medium-emphasis">Step {{ a.approval_order }} · {{ approverType(a.approver_type) }}<span v-if="!a.is_required"> · optional</span></div>
                  <div v-if="a.acted_at" class="text-caption text-medium-emphasis">{{ formatDateTime(a.acted_at) }} · {{ a.actor?.username }}</div>
                  <div v-if="a.remarks" class="text-body-2">"{{ a.remarks }}"</div>
                </v-timeline-item>
              </v-timeline>
              <div v-if="offer.approvals.length" class="text-caption text-medium-emphasis mt-2">Workflow: {{ offer.approvals[0].workflow_name }}</div>
            </v-card-text>
          </v-card>

          <v-card variant="outlined" class="rounded-lg">
            <v-card-title class="text-subtitle-1 font-weight-bold border-b">History</v-card-title>
            <v-card-text>
              <v-timeline density="compact" side="end" align="start">
                <v-timeline-item v-for="e in [...offer.events].reverse()" :key="e.id" size="x-small" :dot-color="eventColor(e)">
                  <div class="font-weight-medium">{{ eventLabel(e.event) }}</div>
                  <div class="text-caption text-medium-emphasis">{{ formatDateTime(e.occurred_at) }} · {{ e.actor?.username || "system" }}</div>
                  <div v-if="e.remarks" class="text-body-2">"{{ e.remarks }}"</div>
                </v-timeline-item>
              </v-timeline>
            </v-card-text>
          </v-card>
        </v-col>
      </v-row>

      <OfferFormDialog v-if="can.update && options" v-model="editDialog" :offer="offer" :options="options" />

      <v-dialog v-model="action.open" max-width="500">
        <v-card>
          <v-card-title class="pa-4">{{ actionInfo.title }}</v-card-title>
          <v-card-text>
            <p v-if="actionInfo.note" class="text-body-2 mb-3">{{ actionInfo.note }}</p>
            <v-alert v-if="actionError" type="error" variant="tonal" density="compact" class="mb-3">{{ actionError }}</v-alert>
            <v-textarea v-model="actionForm.text" :label="actionInfo.label" rows="3" variant="outlined" density="compact" :error-messages="actionForm.errors.remarks || actionForm.errors.reason" />
          </v-card-text>
          <v-card-actions class="pa-4">
            <v-spacer />
            <v-btn variant="text" @click="action.open = false">Back</v-btn>
            <v-btn :color="actionInfo.color" :loading="actionForm.processing" @click="runAction">{{ actionInfo.title }}</v-btn>
          </v-card-actions>
        </v-card>
      </v-dialog>
    </v-container>
  </SidebarLayout>
</template>

<script>
import { Head, router, useForm } from "@inertiajs/vue3";
import SidebarLayout from "@/layouts/SidebarLayout.vue";
import OfferFormDialog from "@/components/Recruitment/OfferFormDialog.vue";
import {
  APPROVAL_STATUS,
  APPROVER_TYPE_LABELS,
  OFFER_EVENT_LABELS,
  OFFER_STATUS,
  SALARY_FREQUENCIES,
  applicantName,
  formatDate,
  formatDateTime,
  money,
  statusInfo,
} from "@/utils/recruitment";

const ACTIONS = {
  submit: { title: "Submit for approval", route: "submit", field: "remarks", label: "Note for approvers (optional)", color: "primary", note: "The approval chain is fixed from the current workflow and the terms are locked." },
  approve: { title: "Approve", route: "approve", field: "remarks", label: "Remarks (optional)", color: "success" },
  reject: { title: "Reject offer", route: "reject", field: "remarks", label: "Reason *", color: "error", note: "This rejects the offer in approval; it is not the candidate's answer." },
  issue: { title: "Issue offer", route: "issue", field: "remarks", label: "Remarks (optional)", color: "primary", note: "Marks the offer as issued to the candidate today. No email is sent." },
  accept: { title: "Record acceptance", route: "respond", field: "remarks", label: "Remarks (optional)", color: "success", response: "accepted" },
  decline: { title: "Record candidate rejection", route: "respond", field: "remarks", label: "Remarks (optional)", color: "error", response: "declined" },
  withdraw: { title: "Withdraw offer", route: "withdraw", field: "reason", label: "Reason *", color: "error", note: "A withdrawn offer stays on record and can never be accepted." },
};

export default {
  name: "OfferShow",
  components: { SidebarLayout, Head, OfferFormDialog },
  props: {
    offer: { type: Object, required: true },
    options: { type: Object, default: null },
    conversion: { type: Object, default: null },
    can: { type: Object, default: () => ({}) },
  },
  data() {
    return {
      editDialog: false,
      action: { open: false, name: null },
      actionForm: useForm({ text: "" }),
    };
  },
  computed: {
    status() {
      return statusInfo(OFFER_STATUS, this.offer.status);
    },
    actionInfo() {
      return ACTIONS[this.action.name] || {};
    },
    actionError() {
      const e = this.actionForm.errors;
      return e.status || e.approval || e.offer || e.expiry_date || e.response || null;
    },
    pageError() {
      if (this.action.open) return null;
      const e = this.$page.props.errors || {};
      return e.status || e.approval || e.offer || null;
    },
  },
  methods: {
    applicantName,
    formatDate,
    formatDateTime,
    money,
    frequencyLabel(value) {
      return SALARY_FREQUENCIES[value] || value;
    },
    approverType(type) {
      return APPROVER_TYPE_LABELS[type] || type;
    },
    approvalLabel(value) {
      return statusInfo(APPROVAL_STATUS, value).label;
    },
    isCurrent(approval) {
      if (this.offer.status !== "pending_approval" || approval.status !== "pending") return false;
      return this.offer.approvals.find((a) => a.status === "pending")?.id === approval.id;
    },
    approvalColor(approval) {
      return this.isCurrent(approval) ? "primary" : statusInfo(APPROVAL_STATUS, approval.status).color;
    },
    eventLabel(event) {
      return OFFER_EVENT_LABELS[event] || event;
    },
    eventColor(e) {
      return { approval_rejected: "error", candidate_declined: "error", withdrawn: "grey", expired: "grey", candidate_accepted: "success", approved: "success" }[e.event] || "primary";
    },
    openAction(name) {
      this.actionForm.reset();
      this.actionForm.clearErrors();
      this.action = { open: true, name };
    },
    runAction() {
      const info = this.actionInfo;
      this.actionForm
        .transform((d) => ({ [info.field]: d.text || null, ...(info.response ? { response: info.response } : {}) }))
        .post(route(`recruitment.offers.${info.route}`, this.offer.id), { preserveScroll: true, onSuccess: () => (this.action.open = false) });
    },
    go(url) {
      router.visit(url);
    },
  },
};
</script>
