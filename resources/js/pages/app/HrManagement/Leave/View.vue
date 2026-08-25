<template>
  <LeaveManagementTabs v-model:activeTab="activeTab" />
  <v-row>
    <v-col cols="12">
      <v-card class="employee-info-card" elevation="2">
        <v-card-text class="pa-6">
          <v-row align="center">
            <v-col cols="12" md="2" class="text-center">
              <div class="avatar-container">
                <v-avatar size="120" class="mx-auto d-block mb-4 avatar-glow">
                  <v-img
                    :src="'/images/default-avatar.png'"
                    alt="Profile Image"
                    cover
                  >
                    <template v-slot:placeholder>
                      <v-avatar color="primary-lighten-4" size="120">
                        <v-icon
                          size="60"
                          color="starbucks-green"
                          icon="mdi-account"
                        ></v-icon>
                      </v-avatar>
                    </template>
                  </v-img>
                </v-avatar>
              </div>
            </v-col>
            <v-col cols="12" md="5">
              <div class="info-item mb-3">
                <div class="info-label">
                  <v-icon
                    icon="mdi-account"
                    size="16"
                    class="mr-2"
                    color="primary"
                  ></v-icon>
                  Name
                </div>
                <div class="info-value">
                  {{ leaveApplication.employee.personal_information.firstname }}
                  {{
                    leaveApplication.employee.personal_information.middlename ??
                    ""
                  }}
                  {{ leaveApplication.employee.personal_information.lastname }}
                  {{
                    leaveApplication.employee.personal_information.suffix ?? ""
                  }}
                </div>
              </div>
              <div class="info-item mb-3">
                <div class="info-label">
                  <v-icon
                    icon="mdi-identifier"
                    size="16"
                    class="mr-2"
                    color="primary"
                  ></v-icon>
                  Employee ID
                </div>
                <div class="info-value">
                  {{ leaveApplication.employee.employee_number }}
                </div>
              </div>
              <div class="info-item">
                <div class="info-label">
                  <v-icon
                    icon="mdi-briefcase"
                    size="16"
                    class="mr-2"
                    color="primary"
                  ></v-icon>
                  Position
                </div>
                <div class="info-value">
                  {{
                    leaveApplication.employee.position?.government_position.name
                  }}
                </div>
              </div>
            </v-col>
            <v-col cols="12" md="5">
              <div class="info-item">
                <div class="info-label">
                  <v-icon
                    icon="mdi-sitemap"
                    size="16"
                    class="mr-2"
                    color="primary"
                  ></v-icon>
                  Operating Unit
                </div>
                <div class="info-value">
                  {{ leaveApplication.employee.operating_unit?.name }}
                </div>
              </div>
              <div class="info-item mb-3">
                <div class="info-label">
                  <v-icon
                    icon="mdi-domain"
                    size="16"
                    class="mr-2"
                    color="primary"
                  ></v-icon>
                  Department
                </div>
                <div class="info-value">
                  {{ leaveApplication.employee.department?.name }}
                </div>
              </div>
              <div class="info-item mb-3">
                <div class="info-label">
                  <v-icon
                    icon="mdi-domain"
                    size="16"
                    class="mr-2"
                    color="primary"
                  />
                  Designation(s)
                </div>

                <div class="info-value" style="white-space: pre-line">
                  {{
                    (leaveApplication.employee.employee_designations || [])
                      .filter(
                        (d) =>
                          !d.end_date ||
                          d.end_date >= new Date().toISOString().split("T")[0]
                      )
                      .map((d) => d.designation?.name)
                      .filter(Boolean)
                      .join("\n") || "None"
                  }}
                </div>
              </div>
            </v-col>
          </v-row>
        </v-card-text>
      </v-card>
    </v-col>
    <v-col cols="12">
      <v-card class="leave-application-card" elevation="2">
        <v-card-text class="pa-6">
          <div class="d-flex align-center justify-space-between mb-6">
            <div class="text-h6 font-weight-bold primary--text">
              <v-icon icon="mdi-calendar-text" class="mr-3"></v-icon>
              Leave Application
            </div>
            <v-btn
              variant="elevated"
              color="starbucks-green"
              prepend-icon="mdi-printer"
              class="rounded-pill print-btn"
              min-width="140"
              @click="printLeave(leaveApplication.print_link)"
            >
              Print Leave Application
            </v-btn>
          </div>

          <div class="leave-type-section mb-6">
            <div class="text-h6 font-weight-bold primary--text mb-2">
              <v-icon icon="mdi-calendar-star" class="mr-2"></v-icon>
              {{ leaveApplication.leave.name }}
            </div>

            <div
              v-if="leaveApplication.leave.name === 'Others'"
              class="text-h6 font-weight-medium secondary--text"
            >
              <v-icon icon="mdi-calendar-plus" class="mr-2"></v-icon>
              {{
                leaveApplication.special_leave_credit?.special_leave?.name ?? ""
              }}
            </div>
          </div>

          <v-divider class="mb-6"></v-divider>

          <v-row>
            <v-col cols="12" md="6">
              <v-card class="details-card hoverable-card" elevation="1">
                <v-card-text class="pa-5">
                  <div class="text-h6 font-weight-bold mb-4 primary--text">
                    <v-icon icon="mdi-calendar-multiple" class="mr-2"></v-icon>
                    Leave Details
                  </div>

                  <div class="detail-items">
                    <div class="detail-item">
                      <div class="detail-icon">
                        <v-icon
                          icon="mdi-calendar-start"
                          color="success"
                        ></v-icon>
                      </div>
                      <div class="detail-content">
                        <div class="detail-label">Start Date</div>
                        <div class="detail-value">
                          {{
                            new Date(leaveApplication.from).toLocaleDateString(
                              "en-US",
                              { month: "long", day: "numeric", year: "numeric" }
                            )
                          }}
                        </div>
                      </div>
                    </div>

                    <div class="detail-item">
                      <div class="detail-icon">
                        <v-icon icon="mdi-calendar-end" color="error"></v-icon>
                      </div>
                      <div class="detail-content">
                        <div class="detail-label">End Date</div>
                        <div class="detail-value">
                          {{
                            new Date(leaveApplication.to).toLocaleDateString(
                              "en-US",
                              { month: "long", day: "numeric", year: "numeric" }
                            )
                          }}
                        </div>
                      </div>
                    </div>

                    <div class="detail-item">
                      <div class="detail-icon">
                        <v-icon
                          icon="mdi-calendar-clock"
                          color="primary"
                        ></v-icon>
                      </div>
                      <div class="detail-content">
                        <div class="detail-label">Duration</div>
                        <div class="detail-value">
                          {{ leaveApplication.no_of_days }}
                          {{
                            leaveApplication.no_of_days === 1 ? "day" : "days"
                          }}
                        </div>
                      </div>
                    </div>
                  </div>
                </v-card-text>
              </v-card>
            </v-col>

            <v-col cols="12" md="6">
              <v-card class="details-card hoverable-card" elevation="1">
                <v-card-text class="pa-5">
                  <div class="text-h6 font-weight-bold mb-4 primary--text">
                    <v-icon icon="mdi-information" class="mr-2"></v-icon>
                    Additional Information
                  </div>

                  <div
                    class="additional-info"
                  >
                    <div
                      v-if="leaveApplication.location_within_philippines"
                      class="info-item"
                    >
                      <div class="info-icon">
                        <v-icon icon="mdi-map-marker" color="success"></v-icon>
                      </div>
                      <div class="info-content">
                        <div class="info-label">
                          Location Within Philippines
                        </div>
                        <div class="info-value">
                          {{ leaveApplication.location_within_philippines }}
                        </div>
                      </div>
                    </div>

                    <div
                      v-if="leaveApplication.location_abroad"
                      class="info-item"
                    >
                      <div class="info-icon">
                        <v-icon icon="mdi-airplane" color="primary"></v-icon>
                      </div>
                      <div class="info-content">
                        <div class="info-label">Location Abroad</div>
                        <div class="info-value">
                          {{ leaveApplication.location_abroad }}
                        </div>
                      </div>
                    </div>
                  </div>

                  <div
                    class="additional-info"
                  >
                    <div v-if="leaveApplication.illness" class="info-item">
                      <div class="info-icon">
                        <v-icon icon="mdi-medical-bag" color="error"></v-icon>
                      </div>
                      <div class="info-content">
                        <div class="info-label">Illness</div>
                        <div class="info-value">
                          {{ leaveApplication.illness }}
                        </div>
                      </div>
                    </div>

                    <div
                      v-if="leaveApplication.sick_leave_type"
                      class="info-item"
                    >
                      <div class="info-icon">
                        <v-icon
                          icon="mdi-hospital-box"
                          color="warning"
                        ></v-icon>
                      </div>
                      <div class="info-content">
                        <div class="info-label">Sick Leave Type</div>
                        <div class="info-value">
                          {{ leaveApplication.sick_leave_type }}
                        </div>
                      </div>
                    </div>
                  </div>

                  <div
                    v-if="
                      !leaveApplication.location_within_philippines &&
                      !leaveApplication.location_abroad &&
                      !leaveApplication.illness &&
                      !leaveApplication.sick_leave_type
                    "
                    class="no-additional-info"
                  >
                    <v-icon
                      icon="mdi-information-outline"
                      size="48"
                      color="grey-lighten-1"
                    ></v-icon>
                    <div class="text-body-2 grey--text">
                      No additional information provided
                    </div>
                  </div>
                </v-card-text>
              </v-card>
            </v-col>
          </v-row>

          <v-divider class="my-6"></v-divider>

          <div class="status-section">
            <div
              class="status-card"
              :class="'status-' + leaveApplication.current_status?.status"
            >
              <div class="status-icon">
                <v-icon
                  :color="
                    getStatusColor(leaveApplication.current_status?.status)
                  "
                  size="32"
                  >{{
                    getStatusIcon(leaveApplication.current_status?.status)
                  }}</v-icon
                >
              </div>
              <div class="status-content">
                <div class="status-label">Application Status</div>
                <div
                  class="status-value"
                  :class="
                    getStatusColor(leaveApplication.current_status?.status) +
                    '--text'
                  "
                >
                  {{
                    leaveApplication.current_status?.status
                      .charAt(0)
                      .toUpperCase() +
                    leaveApplication.current_status?.status.slice(1)
                  }}
                </div>
              </div>
            </div>
          </div>

          <!-- Action Buttons for Non-Approved Status -->
          <div
            v-if="
              leaveApplication.current_status?.status !== 'approved' &&
              leaveApplication.current_status?.status !== 'expired'
            "
            class="action-buttons-section"
          >
            <v-divider class="mb-6"></v-divider>
            <div class="text-h6 font-weight-bold mb-4 primary--text">
              <v-icon icon="mdi-cog" class="mr-2"></v-icon>
              Actions
            </div>

            <div class="action-buttons">
              <!-- <v-btn
                v-if="leaveApplication.current_status?.status != 'cancelled' || leaveApplication.current_status?.status != 'disapproved'"
                color="error"
                variant="elevated"
                prepend-icon="mdi-close-circle"
                class="action-btn"
                size="large"
                @click="cancelLeaveApplication(leaveApplication)"
                :loading="loading && confirmAction === 'Cancel'"
              >
                Cancel Leave
              </v-btn> -->
            </div>
          </div>
        </v-card-text>
      </v-card>
    </v-col>
  </v-row>



  <!-- Floating Action Button -->
  <v-btn
    icon="mdi-arrow-left"
    size="large"
    color="starbucks-green"
    @click="goToIndex()"
    class="floating-back-btn"
    elevation="4"
  >
  </v-btn>

  <!-- Cancel Confirmation Dialog -->
  <v-dialog v-model="confirmDialog" max-width="500" persistent>
    <v-card class="confirmation-dialog">
      <v-card-title class="text-h5 font-weight-bold">
        <v-icon :color="getActionButtonColor(confirmAction)" class="mr-2">
          {{ getActionIcon(confirmAction) }}
        </v-icon>
        Confirm Action
      </v-card-title>

      <v-card-text class="pt-4">
        <div class="text-body-1 mb-4">
          Are you sure you want to
          <strong>{{ confirmAction?.toLowerCase() }}</strong> this leave
          application?
        </div>

        <div v-if="selectedLeave" class="leave-summary">
          <v-card variant="outlined" class="pa-3">
            <div class="text-subtitle-2 font-weight-bold mb-2">
              Leave Details:
            </div>
            <div class="text-body-2">
              <div><strong>Type:</strong> {{ selectedLeave.leave?.name }}</div>
              <div>
                <strong>Duration:</strong> {{ selectedLeave.no_of_days }}
                {{ selectedLeave.no_of_days === 1 ? "day" : "days" }}
              </div>
              <div>
                <strong>Period:</strong>
                {{ new Date(selectedLeave.from).toLocaleDateString() }} -
                {{ new Date(selectedLeave.to).toLocaleDateString() }}
              </div>
            </div>
          </v-card>
        </div>

        <div class="text-body-2 mt-4 text-warning">
          <v-icon icon="mdi-alert-circle" class="mr-1"></v-icon>
          This action cannot be undone.
        </div>
      </v-card-text>

      <v-card-actions class="pa-4">
        <v-spacer></v-spacer>
        <v-btn
          variant="text"
          @click="confirmDialog = false"
          :disabled="loading"
        >
          No
        </v-btn>
        <v-btn
          :color="getActionButtonColor(confirmAction)"
          variant="elevated"
          @click="handleConfirmAction"
          :loading="loading"
        >
          Yes
        </v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>

</template>
<script>
import SidebarLayout from "@/layouts/SidebarLayout.vue";
import LeaveManagementTabs from "@/components/LeaveManagementTabs.vue";
import { useForm } from "@inertiajs/vue3";

export default {
  layout: SidebarLayout,

  components: {
    LeaveManagementTabs,
  },

  props: {
    leaveApplication: Object,
    errors: Object,
  },

  data() {
    return {
      activeTab: "leave-list",
      leaveApplicationStatusDialog: false,
      confirmDialog: false,
      confirmAction: null,
      selectedLeave: null,
      loading: false,
      validForm: false,

      updateLeaveApplicationStatusForm: useForm({
        row_id: this.leaveApplication.id,
        // employee_id: null,
        status: null,
      }),
    };
  },

  computed: {
    selectedStatus() {
      return this.updateLeaveApplicationStatusForm.status;
    },
  },

  methods: {
    getEmployeeName(employee) {
      if (!employee || !employee.personal_information) return "";
      const { firstname, middlename, lastname, suffix } =
        employee.personal_information;
      return `${firstname} ${middlename || ""} ${lastname} ${
        suffix || ""
      }`.trim();
    },

    getStatusColor(status) {
      const colors = {
        approved: "success",
        rejected: "error",
        pending: "warning",
        cancelled: "grey",
      };
      return colors[status] || "primary";
    },

    getStatusIcon(status) {
      const icons = {
        approved: "mdi-check-circle",
        rejected: "mdi-close-circle",
        pending: "mdi-clock-outline",
        cancelled: "mdi-cancel",
      };
      return icons[status] || "mdi-help-circle";
    },

    getActionButtonColor(action) {
      switch (action) {
        case "Approve":
          return "success";
        case "Reject":
          return "error";
        case "Resubmit":
          return "primary";
        case "Restore":
          return "warning";
        default:
          return "primary";
      }
    },

    getActionIcon(action) {
      switch (action) {
        case "Approve":
          return "mdi-check-circle";
        case "Reject":
          return "mdi-close-circle";
        case "Resubmit":
          return "mdi-refresh";
        case "Restore":
          return "mdi-restore";
        default:
          return "mdi-help-circle";
      }
    },
    closeDialog() {
      this.leaveApplicationStatusDialog = false;
      this.updateLeaveApplicationStatusForm.status = "";
      this.$refs.statusForm?.reset();
    },
    updateLeaveApplicationStatus() {
      this.updateLeaveApplicationStatusForm.post(
        route("hrmanagement.leave.updateLeaveApplicationStatus"),
        {
          onSuccess: () => {
            this.showToast("Leave status updated successfully", "success");
            this.closeDialog();
          },
          onError: () => {
            this.showToast("Failed to update leave status", "error");
            this.closeDialog();
          },
        }
      );
    },

    cancelLeaveApplication(leave) {
      this.confirmAction = "Cancel";
      this.selectedLeave = leave;
      this.confirmDialog = true;
    },

    goToIndex() {
      this.$inertia.visit(route("hrmanagement.leave.leaveList"));
    },

    handleConfirmAction() {
      this.loading = true;

      // Map action types to status values
      const statusMap = {
        Approve: "approved",
        Reject: "rejected",
        Resubmit: "pending",
        Restore: "pending",
      };

      this.updateLeaveApplicationStatusForm.status =
        statusMap[this.confirmAction];

      this.updateLeaveApplicationStatusForm.post(
        route("hrmanagement.leave.updateLeaveApplicationStatus"),
        {
          onSuccess: () => {
            const successMessages = {
              Approve: "Leave application approved successfully",
              Reject: "Leave application rejected",
              Resubmit: "Leave application resubmitted for review",
              Restore: "Leave application restored",
            };

            this.showToast(successMessages[this.confirmAction], "success");
            this.confirmDialog = false;
            this.confirmAction = null;
            this.selectedLeave = null;
            this.loading = false;

            // Refresh the page to show updated status
            this.$inertia.reload();
          },
          onError: () => {
            const errorMessages = {
              Approve: "Failed to approve leave application",
              Reject: "Failed to reject leave application",
              Resubmit: "Failed to resubmit leave application",
              Restore: "Failed to restore leave application",
            };

            this.showToast(errorMessages[this.confirmAction], "error");
            this.confirmDialog = false;
            this.confirmAction = null;
            this.selectedLeave = null;
            this.loading = false;
          },
        }
      );
    },

    printLeave(link) {
      window.open(link, "_blank");
    },
  },
};
</script>

<style scoped>
/* Floating Action Button */
.floating-back-btn {
  position: fixed !important;
  bottom: 24px !important;
  right: 24px !important;
  z-index: 1000 !important;
  border-radius: 50% !important;
  transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1) !important;
}

.floating-back-btn:hover {
  transform: scale(1.1) !important;
  box-shadow: 0 8px 25px rgba(0, 0, 0, 0.25) !important;
}

/* Card Animations */
.hoverable-card {
  transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
  cursor: pointer;
  border-radius: 12px !important;
}

.hoverable-card:hover {
  transform: translateY(-6px);
  box-shadow: 0 12px 30px rgba(0, 0, 0, 0.15);
}

/* Employee Info Card */
.employee-info-card {
  border-radius: 16px !important;
  background: linear-gradient(135deg, #f8f9fa 0%, #ffffff 100%) !important;
  border: 1px solid rgba(0, 0, 0, 0.05) !important;
}

.avatar-container {
  position: relative;
}

.avatar-glow {
  box-shadow: 0 0 20px rgba(0, 98, 65, 1);
  transition: all 0.3s ease;
}

.avatar-glow:hover {
  box-shadow: 0 0 30px rgba(0, 98, 65, 1);
  transform: scale(1.05);
}

.info-item {
  margin-bottom: 16px;
}

.info-label {
  font-size: 0.875rem;
  font-weight: 500;
  color: #666;
  margin-bottom: 4px;
  display: flex;
  align-items: center;
}

.info-value {
  font-size: 1rem;
  font-weight: 600;
  color: #333;
  line-height: 1.4;
}

/* Leave Application Card */
.leave-application-card {
  border-radius: 16px !important;
  background: linear-gradient(135deg, #ffffff 0%, #f8f9fa 100%) !important;
  border: 1px solid rgba(0, 0, 0, 0.05) !important;
}

.print-btn {
  transition: all 0.3s ease;
}

.print-btn:hover {
  transform: translateY(-2px);
  box-shadow: 0 6px 20px rgba(25, 118, 210, 0.3);
}

.leave-type-section {
  background: linear-gradient(
    135deg,
    rgba(25, 118, 210, 0.05) 0%,
    rgba(25, 118, 210, 0.1) 100%
  );
  padding: 20px;
  border-radius: 12px;
  border-left: 4px solid #1976d2;
}

/* Details Cards */
.details-card {
  border-radius: 12px !important;
  background: linear-gradient(135deg, #ffffff 0%, #fafafa 100%) !important;
  border: 1px solid rgba(0, 0, 0, 0.08) !important;
  height: 100%;
}

.detail-items {
  display: flex;
  flex-direction: column;
  gap: 20px;
}

.detail-item {
  display: flex;
  align-items: center;
  padding: 16px;
  background: rgba(25, 118, 210, 0.03);
  border-radius: 8px;
  border-left: 3px solid #1976d2;
  transition: all 0.3s ease;
}

.detail-item:hover {
  background: rgba(25, 118, 210, 0.08);
  transform: translateX(4px);
}

.detail-icon {
  margin-right: 16px;
  padding: 8px;
  background: rgba(255, 255, 255, 0.8);
  border-radius: 50%;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
}

.detail-content {
  flex: 1;
}

.detail-label {
  font-size: 0.875rem;
  font-weight: 500;
  color: #666;
  margin-bottom: 4px;
}

.detail-value {
  font-size: 1rem;
  font-weight: 600;
  color: #333;
}

/* Additional Info */
.additional-info {
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.info-item {
  display: flex;
  align-items: flex-start;
  padding: 12px;
  background: rgba(0, 0, 0, 0.02);
  border-radius: 8px;
  border-left: 3px solid #4caf50;
  transition: all 0.3s ease;
}

.info-item:hover {
  background: rgba(0, 0, 0, 0.05);
  transform: translateX(4px);
}

.info-icon {
  margin-right: 12px;
  padding: 6px;
  background: rgba(255, 255, 255, 0.8);
  border-radius: 50%;
  box-shadow: 0 2px 6px rgba(0, 0, 0, 0.1);
}

.info-content {
  flex: 1;
}

.info-label {
  font-size: 0.875rem;
  font-weight: 500;
  color: #666;
  margin-bottom: 4px;
  margin-right: 5px;
}

.info-value {
  font-size: 0.95rem;
  font-weight: 500;
  color: #333;
  line-height: 1.4;
}

.no-additional-info {
  text-align: center;
  padding: 40px 20px;
  color: #999;
}

/* Status Section */
.status-section {
  display: flex;
  justify-content: center;
  margin-top: 24px;
}

/* Action Buttons Section */
.action-buttons-section {
  margin-top: 32px;
}

.action-buttons {
  display: flex;
  gap: 16px;
  flex-wrap: wrap;
  justify-content: center;
}

.action-btn {
  min-width: 160px;
  border-radius: 8px !important;
  transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
  font-weight: 600;
  text-transform: none;
  letter-spacing: 0.5px;
}

.action-btn:hover {
  transform: translateY(-2px);
  box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
}

.action-btn:active {
  transform: translateY(0);
}

/* Confirmation Dialog */
.confirmation-dialog {
  border-radius: 16px !important;
}

.leave-summary {
  margin: 16px 0;
}

.leave-summary .v-card {
  border-radius: 8px !important;
  background: rgba(0, 0, 0, 0.02) !important;
}

.status-card {
  display: flex;
  align-items: center;
  padding: 20px 32px;
  background: linear-gradient(135deg, #ffffff 0%, #f8f9fa 100%);
  border-radius: 12px;
  border: 2px solid #e0e0e0;
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
  transition: all 0.3s ease;
  min-width: 280px;
}

.status-card:hover {
  transform: translateY(-2px);
  box-shadow: 0 8px 20px rgba(0, 0, 0, 0.15);
}

.status-card.status-approved {
  border-color: #4caf50;
  background: linear-gradient(
    135deg,
    rgba(76, 175, 80, 0.1) 0%,
    rgba(76, 175, 80, 0.05) 100%
  );
}

.status-card.status-rejected {
  border-color: #f44336;
  background: linear-gradient(
    135deg,
    rgba(244, 67, 54, 0.1) 0%,
    rgba(244, 67, 54, 0.05) 100%
  );
}

.status-card.status-pending {
  border-color: #ff9800;
  background: linear-gradient(
    135deg,
    rgba(255, 152, 0, 0.1) 0%,
    rgba(255, 152, 0, 0.05) 100%
  );
}

.status-card.status-cancelled {
  border-color: #9e9e9e;
  background: linear-gradient(
    135deg,
    rgba(158, 158, 158, 0.1) 0%,
    rgba(158, 158, 158, 0.05) 100%
  );
}

.status-icon {
  margin-right: 16px;
  padding: 12px;
  background: rgba(255, 255, 255, 0.9);
  border-radius: 50%;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
}

.status-content {
  flex: 1;
}

.status-label {
  font-size: 0.875rem;
  font-weight: 500;
  color: #666;
  margin-bottom: 4px;
}

.status-value {
  font-size: 1.25rem;
  font-weight: 700;
  text-transform: capitalize;
}

/* Responsive Design */
@media (max-width: 768px) {
  .status-card {
    min-width: auto;
    width: 100%;
    padding: 16px 24px;
  }

  .detail-item {
    padding: 12px;
  }

  .info-item {
    padding: 10px;
  }

  .floating-back-btn {
    bottom: 16px !important;
    right: 16px !important;
  }

  .action-buttons {
    flex-direction: column;
    align-items: center;
  }

  .action-btn {
    width: 100%;
    max-width: 280px;
  }
}

/* Smooth page transitions */
.v-card {
  transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

/* Loading states */
.loading-shimmer {
  background: linear-gradient(90deg, #f0f0f0 25%, #e0e0e0 50%, #f0f0f0 75%);
  background-size: 200% 100%;
  animation: shimmer 1.5s infinite;
}

@keyframes shimmer {
  0% {
    background-position: -200% 0;
  }
  100% {
    background-position: 200% 0;
  }
}
</style>
