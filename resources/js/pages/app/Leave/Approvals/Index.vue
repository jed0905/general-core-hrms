<template>
  <SidebarLayout>
    <Head title="Leave Approvals" />

    <v-container fluid class="pa-6">
      <!-- Header -->
      <v-row class="mb-4" align="center">
        <v-col cols="12" md="8">
          <h1 class="text-h5 font-weight-bold">Leave Approvals</h1>
          <p class="text-body-2 text-medium-emphasis">
            Review and process pending leave applications assigned to you.
          </p>
        </v-col>
      </v-row>

      <!-- Approvals Table Card -->
      <v-card elevation="1" rounded="lg">
        <v-data-table
          :headers="headers"
          :items="approvals.data"
          :loading="processing"
          density="comfortable"
          class="elevation-0"
          hide-default-footer
        >
          <!-- Employee Info Column -->
          <template #item.employee="{ item }">
            <div class="py-2">
              <div class="font-weight-medium">
                {{ item.employee_name || getFullName(item.employee) }}
              </div>
              <div class="text-caption text-medium-emphasis">
                {{ item.employee?.department?.name || "N/A" }}
              </div>
            </div>
          </template>

          <!-- Leave Type Column -->
          <template #item.leave_type="{ item }">
            <v-chip
              size="small"
              variant="tonal"
              color="primary"
              class="font-weight-medium"
            >
              {{ item.leave_type?.code || "LV" }} - {{ item.leave_type?.name }}
            </v-chip>
          </template>

          <!-- Duration Column -->
          <template #item.duration="{ item }">
            <div>
              <span class="font-weight-medium"
                >{{ item.total_days }} day(s)</span
              >
              <div class="text-caption text-medium-emphasis">
                {{ item.total_hours }} hr(s)
              </div>
            </div>
          </template>

          <!-- Dates Column -->
          <template #item.dates="{ item }">
            <span v-if="item.dates && item.dates.length">
              {{ formatDate(item.dates[0].leave_date) }}
              <span
                v-if="item.dates.length > 1"
                class="text-caption text-medium-emphasis"
              >
                (+{{ item.dates.length - 1 }} more)
              </span>
            </span>
            <span v-else>—</span>
          </template>

          <!-- Status Column -->
          <template #item.status="{ item }">
            <v-chip size="x-small" color="warning" variant="flat">
              {{ item.status }}
            </v-chip>
          </template>

          <!-- Actions Column -->
          <template #item.actions="{ item }">
            <div class="d-flex ga-1 justify-end">
              <v-btn
                icon="mdi-eye-outline"
                variant="text"
                size="small"
                color="info"
                @click="openDetailsDialog(item)"
              />

              <v-btn
                v-if="item.can?.approve"
                icon="mdi-check-circle-outline"
                variant="text"
                size="small"
                color="success"
                @click="openActionDialog(item, 'approve')"
              />

              <v-btn
                v-if="item.can?.return"
                icon="mdi-undo-variant"
                variant="text"
                size="small"
                color="warning"
                @click="openActionDialog(item, 'return')"
              />

              <v-btn
                v-if="item.can?.reject"
                icon="mdi-close-circle-outline"
                variant="text"
                size="small"
                color="error"
                @click="openActionDialog(item, 'reject')"
              />
            </div>
          </template>
        </v-data-table>

        <!-- Custom Pagination -->
        <v-divider />
        <div class="d-flex align-center justify-space-between px-4 py-3">
          <span class="text-caption text-medium-emphasis">
            Showing {{ approvals.from || 0 }} to {{ approvals.to || 0 }} of
            {{ approvals.total || 0 }} entries
          </span>
          <v-pagination
            v-if="approvals.last_page > 1"
            v-model="currentPage"
            :length="approvals.last_page"
            density="compact"
            total-visible="5"
            @update:model-value="changePage"
          />
        </div>
      </v-card>

      <!-- Details Dialog -->
      <v-dialog v-model="detailsDialog" max-width="600">
        <v-card v-if="selectedApplication">
          <v-card-title
            class="d-flex justify-space-between align-center py-3 px-4"
          >
            <span class="text-h6 font-weight-bold"
              >Leave Application Details</span
            >
            <v-btn
              icon="mdi-close"
              variant="text"
              size="small"
              @click="detailsDialog = false"
            />
          </v-card-title>
          <v-divider />
          <v-card-text class="pa-4">
            <v-row density="compact">
              <v-col cols="6">
                <div class="text-caption text-medium-emphasis">Employee</div>
                <div class="font-weight-medium">
                  {{ getFullName(selectedApplication.employee) }}
                </div>
              </v-col>
              <v-col cols="6">
                <div class="text-caption text-medium-emphasis">Leave Type</div>
                <div class="font-weight-medium">
                  {{ selectedApplication.leave_type?.name }}
                </div>
              </v-col>
              <v-col cols="6" class="mt-2">
                <div class="text-caption text-medium-emphasis">
                  Total Duration
                </div>
                <div class="font-weight-medium">
                  {{ selectedApplication.total_days }} Day(s) ({{
                    selectedApplication.total_hours
                  }}
                  Hours)
                </div>
              </v-col>
              <v-col cols="6" class="mt-2">
                <div class="text-caption text-medium-emphasis">
                  Submitted At
                </div>
                <div class="font-weight-medium">
                  {{ formatDate(selectedApplication.created_at) }}
                </div>
              </v-col>
              <v-col cols="12" class="mt-2">
                <div class="text-caption text-medium-emphasis">Reason</div>
                <div class="text-body-2 bg-grey-lighten-4 pa-3 rounded mt-1">
                  {{ selectedApplication.reason || "No reason provided." }}
                </div>
              </v-col>
            </v-row>
          </v-card-text>
          <v-divider />
          <v-card-actions class="pa-3">
            <v-btn
              variant="text"
              size="small"
              color="primary"
              prepend-icon="mdi-open-in-new"
              @click="openFullDetails(selectedApplication)"
              >Attachments &amp; history</v-btn
            >
            <v-spacer />
            <v-btn
              variant="outlined"
              size="small"
              @click="detailsDialog = false"
              >Close</v-btn
            >
          </v-card-actions>
        </v-card>
      </v-dialog>

      <!-- Approval Action Dialog (Approve / Reject / Return) -->
      <v-dialog v-model="actionDialog" max-width="500" persistent>
        <v-card v-if="selectedApplication">
          <v-card-title class="text-h6 font-weight-bold text-capitalize pa-4">
            {{ actionType }} Leave Application
          </v-card-title>
          <v-divider />
          <v-card-text class="pa-4">
            <p class="text-body-2 mb-3">
              Are you sure you want to <strong>{{ actionType }}</strong> the
              leave request for
              <strong>{{ getFullName(selectedApplication.employee) }}</strong
              >?
            </p>

            <v-textarea
              v-model="form.remarks"
              label="Remarks"
              placeholder="Provide context or explanation..."
              variant="outlined"
              density="compact"
              rows="3"
              :error-messages="formErrors.remarks"
              :rules="
                actionType !== 'approve'
                  ? [(v) => !!v || 'Remarks are required for this action']
                  : []
              "
            />
          </v-card-text>
          <v-divider />
          <v-card-actions class="pa-3">
            <v-spacer />
            <v-btn
              variant="text"
              size="small"
              :disabled="processing"
              @click="closeActionDialog"
            >
              Cancel
            </v-btn>
            <v-btn
              :color="getActionColor(actionType)"
              variant="flat"
              size="small"
              :loading="processing"
              @click="submitAction"
            >
              Confirm {{ actionType }}
            </v-btn>
          </v-card-actions>
        </v-card>
      </v-dialog>
    </v-container>
  </SidebarLayout>
</template>

<script>
import { Head, router } from "@inertiajs/vue3";
import SidebarLayout from "@/layouts/SidebarLayout.vue";

export default {
  name: "LeaveApprovalsIndex",

  components: {
    SidebarLayout,
    Head,
  },

  props: {
    approvals: {
      type: Object,
      required: true,
    },
    filters: {
      type: Object,
      default: () => ({}),
    },
  },

  data() {
    return {
      currentPage: this.approvals.current_page || 1,
      processing: false,
      detailsDialog: false,
      actionDialog: false,
      selectedApplication: null,
      actionType: null, // 'approve' | 'reject' | 'return'
      form: {
        remarks: "",
      },
      formErrors: {},
      headers: [
        { title: "Employee", key: "employee", sortable: false },
        { title: "Leave Type", key: "leave_type", sortable: false },
        { title: "Duration", key: "duration", sortable: false },
        { title: "Dates", key: "dates", sortable: false },
        { title: "Status", key: "status", sortable: false },
        { title: "Actions", key: "actions", align: "end", sortable: false },
      ],
    };
  },

  watch: {
    "approvals.current_page"(newPage) {
      this.currentPage = newPage;
    },
  },

  methods: {
    openFullDetails(application) {
      router.visit(
        route("leave.applications.show", { leaveApplication: application.id })
      );
    },
    getFullName(employee) {
      if (!employee) return "N/A";
      const name = `${employee.emp_first_name || ""} ${
        employee.emp_last_name || ""
      }`.trim();
      return name || employee.user?.name || `Employee #${employee.id}`;
    },

    formatDate(dateStr) {
      if (!dateStr) return "—";
      return new Date(dateStr).toLocaleDateString("en-US", {
        year: "numeric",
        month: "short",
        day: "numeric",
      });
    },

    getActionColor(type) {
      switch (type) {
        case "approve":
          return "success";
        case "reject":
          return "error";
        case "return":
          return "warning";
        default:
          return "primary";
      }
    },

    openDetailsDialog(application) {
      this.selectedApplication = application;
      this.detailsDialog = true;
    },

    openActionDialog(application, type) {
      this.selectedApplication = application;
      this.actionType = type;
      this.form.remarks = "";
      this.formErrors = {};
      this.actionDialog = true;
    },

    closeActionDialog() {
      this.actionDialog = false;
      this.selectedApplication = null;
      this.actionType = null;
      this.form.remarks = "";
      this.formErrors = {};
    },

    changePage(page) {
      router.get(
        route("leave.approvals.index"),
        { ...this.filters, page },
        { preserveState: true, preserveScroll: true }
      );
    },

    submitAction() {
      if (!this.selectedApplication || !this.actionType) return;

      if (this.actionType !== "approve" && !this.form.remarks.trim()) {
        this.formErrors = { remarks: "Remarks are required for this action." };
        return;
      }

      this.processing = true;
      const routeName = `leave.approvals.${this.actionType}`;

      router.post(
        route(routeName, { leaveApplication: this.selectedApplication.id }),
        { remarks: this.form.remarks },
        {
          preserveScroll: true,
          onSuccess: () => {
            this.closeActionDialog();
            if (this.$root.showToast) {
              this.$root.showToast(
                `Leave application ${this.actionType}d successfully.`,
                "success"
              );
            }
          },
          onError: (errors) => {
            this.formErrors = errors;
          },
          onFinish: () => {
            this.processing = false;
          },
        }
      );
    },
  },
};
</script>