<template>
  <MyLeavesTabs v-model:activeTab="activeTab" :tabs="tabs" />

  <PageOnBuild v-if="pageBeingBuilt === true" />

  <FilterWrapper v-if="pageBeingBuilt === false" v-model="isPanelOpen">
    <v-form @submit.prevent="handleFilter()">
      <v-row dense>
        <v-col cols="12" md="3">
          <v-text-field
            variant="outlined"
            density="compact"
            label="Leave From"
            type="date"
            hide-details
            rounded="lg"
            v-model="filterForm.from"
          ></v-text-field>
        </v-col>
        <v-col cols="12" md="3">
          <v-text-field
            variant="outlined"
            density="compact"
            label="Leave To"
            type="date"
            hide-details
            rounded="lg"
            v-model="filterForm.to"
          ></v-text-field>
        </v-col>
        <v-col cols="12" md="3">
          <!-- Multi-select for Leave Status -->
          <div class="multi-select-container">
            <v-select
              v-model="selectedLeaveStatus"
              variant="outlined"
              density="compact"
              label="Show Leave With Status"
              :items="availableLeaveStatus"
              multiple
              chips
              closable-chips
              hide-details
              @update:model-value="handleStatusSelection"
              rounded="lg"
            ></v-select>
          </div>
        </v-col>
        <v-col cols="12" md="3">
          <v-select
            variant="outlined"
            density="compact"
            label="Leave Type"
            hide-details
            rounded="lg"
            :items="leaveTypes"
            item-title="name"
            item-value="id"
            v-model="filterForm.leaveType"
          ></v-select>
        </v-col>
        <v-col cols="12">
          <div class="d-flex align-center justify-end">
            <ButtonMuted name="Reset" class="mr-2" @click="resetFilters()" />
            <ButtonSuccess name="Search" type="submit" />
          </div>
        </v-col>
      </v-row>
    </v-form>
  </FilterWrapper>

  <TableWrapper v-if="pageBeingBuilt === false">
    <v-table>
      <thead>
        <tr>
          <th>Application Date</th>
          <th>Inclusive Dates</th>
          <th>Leave Type</th>
          <th>Status</th>
          <th class="text-center">Action</th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="leave in myLeaveApplications.data" :key="leave.id">
          <td>{{ leave.application_date }}</td>
          <td>
            <span v-if="leave.inclusive_dates.length === 1">
              {{ leave.inclusive_dates[0].date }} -
              {{ leave.inclusive_dates[0].duration }}
            </span>

            <div v-else>
              <span
                @click="leave.showDates = !leave.showDates"
                class="cursor-pointer"
              >
                {{ summarizeDates(leave.inclusive_dates) }}
                <v-icon size="16">
                  {{ leave.showDates ? "mdi-chevron-up" : "mdi-chevron-down" }}
                </v-icon>
              </span>

              <ul v-if="leave.showDates" class="pl-4 mt-2">
                <li v-for="(date, index) in leave.inclusive_dates" :key="index">
                  {{ date.date }} - {{ date.duration }}
                </li>
              </ul>
            </div>
          </td>
          <td>{{ formatLeaveName(leave) }}</td>
          <td class="text-left">
            <v-chip
              :color="getStatusColor(leave.status)"
              text-color="white"
              class="text-capitalize"
              small
            >
              {{ leave.status }}
            </v-chip>
          </td>
          <td class="text-center">
            <div class="d-flex justify-center align-center flex-wrap gap-1">
              <v-btn
                v-if="
                  !['disapproved', 'cancelled'].includes(leave.status) &&
                  !isEarliestDatePastOrToday(leave.inclusive_dates)
                "
                variant="tonal"
                color="gray-darken-1"
                rounded="xl"
                min-width="120"
                class="text-xs text-sm-md px-2 px-sm-4 action-btn"
                @click="openCancelDialog(leave)"
              >
                CANCEL
              </v-btn>

              <a :href="leave.view_link" target="_blank" rel="noopener">
                <v-btn
                  icon="mdi-eye-outline"
                  size="x-small"
                  variant="tonal"
                  color="blue-darken-1"
                  :title="'View Leave Application'"
                ></v-btn>
              </a>
            </div>
          </td>
        </tr>
      </tbody>
    </v-table>
    <Pagination class="mt-4" :meta="myLeaveApplications.meta" />
  </TableWrapper>

  <!-- Cancel Leave Application Dialog -->
  <v-dialog v-model="cancelDialog.show" max-width="500px" persistent>
    <v-card class="pa-4" rounded="lg">
      <!-- Dialog Header -->
      <v-card-title class="text-h6 text-center pa-0 mb-4">
        <v-icon color="warning" size="48" class="mb-2">mdi-alert-circle</v-icon>
        <div>Cancel Leave Application</div>
      </v-card-title>

      <!-- Dialog Content -->
      <v-card-text class="pa-0">
        <div class="text-body-1 mb-4 text-center">
          Are you sure you want to cancel this leave application?
        </div>

        <!-- Leave Details Card -->
        <v-card variant="outlined" class="mb-4" rounded="lg">
          <v-card-text class="py-3">
            <div class="text-subtitle-2 text-primary mb-2">Leave Details:</div>
            <v-row dense>
              <v-col cols="6">
                <div class="text-caption text-grey-600">Leave Type:</div>
                <div class="text-body-2 font-weight-medium">
                  {{ cancelDialog.leaveData?.leave?.name || "N/A" }}
                </div>
              </v-col>
              <v-col cols="6">
                <div class="text-caption text-grey-600">Status:</div>
                <v-chip
                  size="small"
                  :color="getStatusColor(cancelDialog.leaveData?.status)"
                  variant="tonal"
                >
                  {{ cancelDialog.leaveData?.status || "N/A" }}
                </v-chip>
              </v-col>
              <v-col cols="6">
                <div class="text-caption text-grey-600">From:</div>
                <div class="text-body-2">
                  {{ formatDate(cancelDialog.leaveData?.from) }}
                </div>
              </v-col>
              <v-col cols="6">
                <div class="text-caption text-grey-600">To:</div>
                <div class="text-body-2">
                  {{ formatDate(cancelDialog.leaveData?.to) }}
                </div>
              </v-col>
            </v-row>
          </v-card-text>
        </v-card>

        <!-- Reason for Cancellation -->
        <v-textarea
          v-model="cancelLeaveApplicationForm.remarks"
          label="Reason for Cancellation"
          placeholder="Please provide a reason for cancelling this leave application..."
          variant="outlined"
          rows="3"
          hide-details="auto"
          :rules="[(v) => !!v || 'Reason is required']"
          class="mb-3"
          rounded="lg"
        ></v-textarea>

        <!-- Warning Message -->
        <v-alert
          rounded="lg"
          type="warning"
          variant="tonal"
          density="compact"
          class="mb-0"
        >
          <div class="text-caption">
            <strong>Note:</strong> This action cannot be undone. Once the leave
            is cancelled, you will need to notify the HR Office if you wish to
            restore it to pending status.
          </div>
        </v-alert>
      </v-card-text>

      <!-- Dialog Actions -->
      <v-card-actions class="px-0 pt-4 justify-end">
        <!-- Do Not Cancel Button -->
        <v-btn
          color="grey-darken-1"
          variant="outlined"
          rounded="xl"
          min-width="120"
          @click="closeCancelDialog"
          :disabled="cancelDialog.loading"
        >
          NO
        </v-btn>

        <!-- Cancel Leave Button -->
        <v-btn
          color="error"
          variant="elevated"
          rounded="xl"
          min-width="120"
          class="ml-3"
          @click="confirmCancelLeave"
          :loading="cancelDialog.loading"
          :disabled="
            !cancelLeaveApplicationForm.remarks ||
            !cancelLeaveApplicationForm.remarks.trim() ||
            cancelDialog.loading
          "
          ripple
        >
          YES
        </v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>

  <!-- <pre>{{ new Date() }}</pre> -->
</template>
<script>
import SidebarLayout from "@/layouts/SidebarLayout.vue";
import TableWrapper from "@/components/TableWrapper.vue";
import FilterWrapper from "@/components/FilterWrapper.vue";
import ButtonSuccess from "@/components/ButtonSuccess.vue";
import ButtonMuted from "@/components/ButtonMuted.vue";
import MyLeavesTabs from "@/components/MyLeavesTabs.vue";
import PageOnBuild from "@/components/Errors/PageOnBuild.vue";
import Pagination from "@/components/Pagination.vue";
import { useForm } from "@inertiajs/vue3";

export default {
  layout: SidebarLayout,
  components: {
    TableWrapper,
    FilterWrapper,
    ButtonSuccess,
    ButtonMuted,
    MyLeavesTabs,
    PageOnBuild,
    Pagination,
  },
  props: {
    leaveTypes: Object,
    myLeaveApplications: Object,
  },
  data() {
    return {
      activeTab: "index",
      pageBeingBuilt: false,
      isPanelOpen: 0,
      leaveStatus: [
        { name: "Pending", value: "pending" },
        { name: "For Approval", value: "for approval" },
        { name: "For Disapproval", value: "for disapproval" },
        { name: "Certified", value: "certified" },
        { name: "Approved", value: "approved" },
        { name: "Disapproved", value: "disapproved" },
        { name: "Cancelled", value: "cancelled" },
      ],
      selectedLeaveStatus: [],
      cancelDialog: {
        show: false,
        loading: false,
        reason: "",
        leaveData: null,
      },
      cancelLeaveApplicationForm: useForm({
        row_id: null,
        status: "cancelled",
        remarks: null,
      }),

      filterForm: useForm({
        from: null,
        to: null,
        status: [],
        leaveType: null,
      }),
    };
  },

  computed: {
    availableLeaveStatus() {
      return this.leaveStatus;
    },
  },

  methods: {
    parseCustomDate(str) {
      // "FRI, MAR 20, 2026" → "MAR 20, 2026"
      const cleaned = str.split(",").slice(1).join(",").trim();

      return new Date(cleaned);
    },

    isEarliestDatePastOrToday(dates) {
      if (!dates || !dates.length) return false;

      let earliest = this.parseCustomDate(dates[0].date);

      for (const d of dates) {
        const current = this.parseCustomDate(d.date);
        if (current < earliest) {
          earliest = current;
        }
      }

      const today = new Date();
      today.setHours(0, 0, 0, 0);
      earliest.setHours(0, 0, 0, 0);

      return earliest <= today;
    },

    summarizeDates(dates) {
      if (!dates.length) return "N/A";

      if (dates.length === 1) {
        return dates[0].date;
      }

      return `${dates[0].date} → ${dates[dates.length - 1].date} (${
        dates.length
      } days)`;
    },

    formatLeaveName(leave) {
      if (leave.leave_type === "Others") {
        const specialName = leave.special_leave || "";
        return `Others (${this.formatCase(specialName)})`;
      }
      return leave.leave_type || "N/A";
    },

    formatCase(str) {
      if (!str) return "";
      return str.toLowerCase().replace(/\b\w/g, (char) => char.toUpperCase());
    },

    handleFilter() {
      this.filterForm.status = this.selectedLeaveStatus;
      this.filterForm.get(route("self-service.my-leaves.index"), {
        preserveState: true,
        preserveScroll: true,
        only: ["myLeaveApplications"],
      });
    },

    handleStatusSelection(newSelection) {
      this.selectedLeaveStatus = newSelection;
    },
    removeStatus(status) {
      this.selectedLeaveStatus = this.selectedLeaveStatus.filter(
        (s) => s !== status
      );
    },
    resetFilters() {
      this.selectedLeaveStatus = [];
      this.filterForm.from = null;
      this.filterForm.to = null;
      this.filterForm.leaveType = null;
      this.filterForm.status = [];
      this.filterForm.reset();

      // Trigger search with reset filters
      this.handleFilter();
    },
    searchLeaves() {
      // Implement search logic here
      console.log("Searching with selected status:", this.selectedLeaveStatus);
    },

    // Cancel Dialog Methods
    openCancelDialog(leaveData) {
      this.cancelDialog.leaveData = leaveData;
      this.cancelDialog.reason = "";
      this.cancelDialog.show = true;
      this.cancelDialog.loading = false;
      this.cancelLeaveApplicationForm.row_id = leaveData.id;
      this.cancelLeaveApplicationForm.remarks = "";
    },

    closeCancelDialog() {
      this.cancelDialog.show = false;
      this.cancelDialog.loading = false;
      this.cancelDialog.reason = "";
      this.cancelDialog.leaveData = null;
      this.cancelLeaveApplicationForm.reset();
    },

    confirmCancelLeave() {
      this.cancelDialog.loading = true;

      this.cancelLeaveApplicationForm.post(
        route("self-service.my-leaves.cancel"),
        {
          onSuccess: () => {
            this.showToast("Leave application has been cancelled.", "success");
            this.closeCancelDialog();
          },
          onError: (errors) => {
            const errorMessages = Object.values(errors).flat().join(" ");
            this.showToast(`${errorMessages}`, "error");
            this.cancelDialog.loading = false;
          },
          onFinish: () => {
            this.cancelDialog.loading = false;
          },
        }
      );
    },

    canCancelLeave(status) {
      // Only allow cancellation for pending leaves
      const cancellableStatuses = ["pending"];
      return cancellableStatuses.includes(status);
    },

    getStatusColor(status) {
      const statusColors = {
        pending: "orange",
        approved: "green",
        rejected: "red",
        cancelled: "red",
        "Request for cancellation": "red",
      };
      return statusColors[status] || "grey";
    },

    formatDate(dateString) {
      if (!dateString) return "N/A";
      return new Date(dateString).toLocaleDateString("en-US", {
        month: "long",
        day: "numeric",
        year: "numeric",
      });
    },

    printDtr(id) {
      window.open(route("self-service.my-leaves.view", { id: id }), "_blank");
    },
  },
};
</script>

<style scoped>
.multi-select-container {
  position: relative;
}

.selected-items {
  border: 1px solid #e0e0e0;
  border-radius: 4px;
  padding: 8px;
  background-color: #fafafa;
  min-height: 40px;
}

.selected-items .v-chip {
  margin: 2px;
}
</style>
