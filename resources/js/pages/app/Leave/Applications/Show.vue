<template>
  <SidebarLayout>
    <Head title="Leave Application" />

    <v-container fluid class="pa-4 pa-sm-6">
      <div
        class="d-flex flex-column flex-sm-row align-sm-center justify-space-between ga-4 mb-6"
      >
        <div>
          <v-btn
            variant="text"
            size="small"
            prepend-icon="mdi-arrow-left"
            class="mb-2 px-0"
            @click="goBack"
            >Back</v-btn
          >
          <h1 class="text-h5 font-weight-bold">
            {{ application.leave_type?.name }} Leave
          </h1>
          <p class="text-body-2 text-medium-emphasis">
            {{ employeeName(application.employee) }}
            <span v-if="application.employee?.department">
              · {{ application.employee.department.name }}</span
            >
          </p>
        </div>

        <div class="d-flex flex-wrap align-center ga-2">
          <v-chip :color="statusColor(application.status)" variant="tonal">
            {{ application.status }}
          </v-chip>
          <v-btn
            v-if="can.approve"
            color="success"
            variant="flat"
            prepend-icon="mdi-check-circle-outline"
            @click="openAction('approve')"
            >Approve</v-btn
          >
          <v-btn
            v-if="can.return"
            color="warning"
            variant="outlined"
            prepend-icon="mdi-undo-variant"
            @click="openAction('return')"
            >Return</v-btn
          >
          <v-btn
            v-if="can.reject"
            color="error"
            variant="outlined"
            prepend-icon="mdi-close-circle-outline"
            @click="openAction('reject')"
            >Reject</v-btn
          >
          <v-btn
            v-if="can.cancel"
            color="error"
            variant="text"
            prepend-icon="mdi-cancel"
            @click="cancelDialog = true"
            >Cancel Application</v-btn
          >
        </div>
      </div>

      <v-row>
        <v-col cols="12" md="7">
          <v-card variant="outlined" class="rounded-lg mb-4">
            <v-card-title class="text-subtitle-1 font-weight-bold"
              >Request</v-card-title
            >
            <v-card-text>
              <v-row density="compact">
                <v-col cols="6">
                  <div class="text-caption text-medium-emphasis">Duration</div>
                  <div class="font-weight-medium">
                    {{ application.total_days }} day(s) ({{
                      application.total_hours
                    }}
                    hrs)
                  </div>
                </v-col>
                <v-col cols="6">
                  <div class="text-caption text-medium-emphasis">Submitted</div>
                  <div class="font-weight-medium">
                    {{ formatDateTime(application.submitted_at) }}
                  </div>
                </v-col>
                <v-col cols="12">
                  <div class="text-caption text-medium-emphasis">Reason</div>
                  <div class="text-body-2">
                    {{ application.reason || "No reason provided." }}
                  </div>
                </v-col>
              </v-row>

              <v-table density="compact" class="mt-4">
                <thead>
                  <tr>
                    <th>Date</th>
                    <th>Duration</th>
                    <th>Hours</th>
                    <th>Paid</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="d in application.dates" :key="d.id">
                    <td>{{ formatDate(d.leave_date) }}</td>
                    <td>{{ durationLabel(d.duration_type) }}</td>
                    <td>{{ d.hours }}</td>
                    <td>{{ d.is_paid ? "Yes" : "No" }}</td>
                  </tr>
                </tbody>
              </v-table>
            </v-card-text>
          </v-card>

          <v-card variant="outlined" class="rounded-lg mb-4">
            <v-card-title class="text-subtitle-1 font-weight-bold"
              >Attachments</v-card-title
            >
            <v-card-text>
              <p
                v-if="!application.attachments?.length"
                class="text-body-2 text-medium-emphasis"
              >
                No attachments.
              </p>
              <v-list v-else density="compact" class="py-0">
                <v-list-item
                  v-for="a in application.attachments"
                  :key="a.id"
                  :href="attachmentUrl(a)"
                  prepend-icon="mdi-paperclip"
                  :title="a.file_name"
                  :subtitle="`${a.file_size} KB`"
                />
              </v-list>
            </v-card-text>
          </v-card>

          <v-card variant="outlined" class="rounded-lg">
            <v-card-title class="text-subtitle-1 font-weight-bold"
              >Comments</v-card-title
            >
            <v-card-text>
              <p
                v-if="!application.comments?.length"
                class="text-body-2 text-medium-emphasis mb-3"
              >
                No comments yet.
              </p>
              <div
                v-for="c in application.comments"
                :key="c.id"
                class="mb-3 pb-3 border-b"
              >
                <div class="text-caption text-medium-emphasis">
                  {{ employeeName(c.commenter) }} ·
                  {{ formatDateTime(c.created_at) }}
                </div>
                <div class="text-body-2" style="white-space: pre-line">
                  {{ c.comment }}
                </div>
              </div>

              <template v-if="can.comment">
                <v-textarea
                  v-model="commentForm.comment"
                  label="Add a comment"
                  variant="outlined"
                  density="compact"
                  rows="2"
                  :error-messages="commentForm.errors.comment"
                />
                <div class="text-end">
                  <v-btn
                    color="primary"
                    size="small"
                    :loading="commentForm.processing"
                    :disabled="!commentForm.comment.trim()"
                    @click="submitComment"
                    >Post Comment</v-btn
                  >
                </div>
              </template>
            </v-card-text>
          </v-card>
        </v-col>

        <v-col cols="12" md="5">
          <v-card variant="outlined" class="rounded-lg mb-4">
            <v-card-title class="text-subtitle-1 font-weight-bold"
              >Approvals</v-card-title
            >
            <v-card-text>
              <p
                v-if="!application.approvals?.length"
                class="text-body-2 text-medium-emphasis"
              >
                No approval steps.
              </p>
              <div
                v-for="step in application.approvals"
                :key="step.id"
                class="d-flex align-start ga-3 mb-3"
              >
                <v-avatar
                  size="28"
                  :color="approvalColor(step.status)"
                  variant="tonal"
                  class="text-caption font-weight-bold"
                  >{{ step.approval_order }}</v-avatar
                >
                <div class="flex-grow-1">
                  <div class="font-weight-medium">
                    {{ step.approver_name || "Approver" }}
                    <v-chip
                      size="x-small"
                      :color="approvalColor(step.status)"
                      variant="tonal"
                      class="ml-1"
                      >{{ step.status }}</v-chip
                    >
                  </div>
                  <div class="text-caption text-medium-emphasis">
                    {{ approverTypeLabel(step.approver_type) }}
                    <span v-if="step.acted_at">
                      · {{ formatDateTime(step.acted_at) }}</span
                    >
                  </div>
                  <div v-if="step.remarks" class="text-body-2 mt-1">
                    {{ step.remarks }}
                  </div>
                </div>
              </div>
            </v-card-text>
          </v-card>

          <v-card variant="outlined" class="rounded-lg">
            <v-card-title class="text-subtitle-1 font-weight-bold"
              >History</v-card-title
            >
            <v-card-text>
              <v-timeline
                v-if="application.status_histories?.length"
                density="compact"
                side="end"
                truncate-line="both"
              >
                <v-timeline-item
                  v-for="h in application.status_histories"
                  :key="h.id"
                  :dot-color="statusColor(h.status)"
                  size="x-small"
                >
                  <div class="font-weight-medium text-capitalize">
                    {{ h.status }}
                  </div>
                  <div class="text-caption text-medium-emphasis">
                    {{ employeeName(h.actor) }} ·
                    {{ formatDateTime(h.acted_at) }}
                  </div>
                  <div v-if="h.remarks" class="text-body-2">
                    {{ h.remarks }}
                  </div>
                </v-timeline-item>
              </v-timeline>
              <p v-else class="text-body-2 text-medium-emphasis">
                No history recorded.
              </p>
            </v-card-text>
          </v-card>
        </v-col>
      </v-row>

      <!-- Approve / Reject / Return -->
      <v-dialog v-model="actionDialog" max-width="500" persistent>
        <v-card>
          <v-card-title class="text-h6 font-weight-bold text-capitalize pa-4">
            {{ actionType }} leave application
          </v-card-title>
          <v-divider />
          <v-card-text class="pa-4">
            <v-textarea
              v-model="actionForm.remarks"
              :label="actionType === 'approve' ? 'Remarks' : 'Remarks *'"
              variant="outlined"
              density="compact"
              rows="3"
              :error-messages="
                actionForm.errors.remarks ||
                actionForm.errors.approval ||
                actionForm.errors.application
              "
            />
          </v-card-text>
          <v-divider />
          <v-card-actions class="pa-3">
            <v-spacer />
            <v-btn
              variant="text"
              :disabled="actionForm.processing"
              @click="actionDialog = false"
              >Cancel</v-btn
            >
            <v-btn
              :color="actionColor"
              variant="flat"
              :loading="actionForm.processing"
              @click="submitAction"
              >Confirm {{ actionType }}</v-btn
            >
          </v-card-actions>
        </v-card>
      </v-dialog>

      <!-- Cancel -->
      <v-dialog v-model="cancelDialog" max-width="420">
        <v-card class="pa-2 rounded-lg">
          <v-card-title class="font-weight-bold"
            >Cancel leave application?</v-card-title
          >
          <v-card-text>
            <template v-if="application.status === 'approved'">
              This leave was already approved. Cancelling returns
              {{ application.total_days }} day(s) to the employee's balance.
            </template>
            <template v-else>This request will be withdrawn.</template>
          </v-card-text>
          <v-card-actions class="justify-end ga-2">
            <v-btn variant="outlined" @click="cancelDialog = false">Keep</v-btn>
            <v-btn color="error" :loading="cancelling" @click="cancelApplication"
              >Cancel Application</v-btn
            >
          </v-card-actions>
        </v-card>
      </v-dialog>
    </v-container>
  </SidebarLayout>
</template>

<script>
import { Head, router, useForm } from "@inertiajs/vue3";
import SidebarLayout from "@/layouts/SidebarLayout.vue";

const APPROVER_TYPE_LABELS = {
  immediate_supervisor: "Immediate supervisor",
  higher_supervisor: "Supervisor's supervisor",
  specific_employee: "Specific employee",
};

export default {
  name: "LeaveApplicationShow",
  components: { SidebarLayout, Head },
  props: {
    application: { type: Object, required: true },
    // Record-level abilities computed by LeaveApplicationPolicy.
    can: { type: Object, default: () => ({}) },
  },
  data() {
    return {
      actionDialog: false,
      actionType: null,
      cancelDialog: false,
      cancelling: false,
      actionForm: useForm({ remarks: "" }),
      commentForm: useForm({ comment: "" }),
    };
  },
  computed: {
    actionColor() {
      return (
        { approve: "success", reject: "error", return: "warning" }[
          this.actionType
        ] || "primary"
      );
    },
  },
  methods: {
    goBack() {
      window.history.length > 1
        ? window.history.back()
        : router.visit(route("leave.applications.index"));
    },
    employeeName(e) {
      return e ? `${e.emp_first_name} ${e.emp_last_name}` : "System";
    },
    statusColor(s) {
      return (
        {
          pending: "warning",
          approved: "success",
          rejected: "error",
          returned: "info",
          cancelled: "grey",
        }[s] || "default"
      );
    },
    approvalColor(s) {
      return (
        {
          pending: "warning",
          approved: "success",
          rejected: "error",
          skipped: "grey",
        }[s] || "default"
      );
    },
    approverTypeLabel(type) {
      return APPROVER_TYPE_LABELS[type] ?? type ?? "";
    },
    durationLabel(type) {
      return (
        { full_day: "Full day", half_day: "Half day", hours: "Hours" }[type] ||
        type
      );
    },
    formatDate(d) {
      return d ? new Date(d).toLocaleDateString() : "—";
    },
    formatDateTime(d) {
      return d ? new Date(d).toLocaleString() : "—";
    },
    attachmentUrl(a) {
      return route("leave.applications.attachments.download", {
        leaveApplication: this.application.id,
        attachment: a.id,
      });
    },
    openAction(type) {
      this.actionType = type;
      this.actionForm.reset();
      this.actionForm.clearErrors();
      this.actionDialog = true;
    },
    submitAction() {
      this.actionForm.post(
        route(`leave.approvals.${this.actionType}`, {
          leaveApplication: this.application.id,
        }),
        {
          preserveScroll: true,
          onSuccess: () => (this.actionDialog = false),
        }
      );
    },
    submitComment() {
      this.commentForm.post(
        route("leave.applications.comments.store", {
          leaveApplication: this.application.id,
        }),
        {
          preserveScroll: true,
          onSuccess: () => this.commentForm.reset(),
        }
      );
    },
    cancelApplication() {
      this.cancelling = true;
      router.post(
        route("leave.applications.cancel", {
          leaveApplication: this.application.id,
        }),
        {},
        {
          preserveScroll: true,
          onFinish: () => {
            this.cancelling = false;
            this.cancelDialog = false;
          },
        }
      );
    },
  },
};
</script>
