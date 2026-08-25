<template>
  <LeaveManagementTabs v-model:activeTab="activeTab" />

  <FilterWrapper v-model="isPanelOpen">
    <v-form @submit.prevent="handleFilter()">
      <v-row>
        <v-col cols="12" md="4">
          <v-text-field
            variant="outlined"
            density="compact"
            label="Employee Name"
            hide-details
            rounded="lg"
            v-model="filterForm.search"
            prepend-inner-icon="mdi-magnify"
          ></v-text-field>
        </v-col>
        <v-col cols="12" md="4">
          <v-text-field
            type="date"
            variant="outlined"
            density="compact"
            label="Date To"
            hide-details
            rounded="lg"
            v-model="filterForm.to"
          ></v-text-field>
        </v-col>
        <v-col cols="12" md="4">
          <v-text-field
            type="date"
            variant="outlined"
            density="compact"
            label="Date From"
            hide-details
            rounded="lg"
            v-model="filterForm.from"
          ></v-text-field>
        </v-col>
        <v-col cols="12" md="4">
          <v-select
            variant="outlined"
            density="compact"
            label="Show Leave With Status"
            :items="leaveStatus"
            item-title="name"
            item-value="value"
            clearable
            hide-details
            :disabled="authorization || directSupervisor"
            rounded="lg"
            v-model="filterForm.status"
          ></v-select>
        </v-col>
        <v-col cols="12" md="4">
          <v-select
            variant="outlined"
            density="compact"
            label="Leave Type"
            hide-details
            rounded="lg"
            :items="leaveTypes"
            item-title="name"
            item-value="id"
            clearable
            v-model="filterForm.leaveType"
          ></v-select>
        </v-col>

        <v-col cols="12" md="4">
          <v-select
            variant="outlined"
            density="compact"
            label="Operating Unit"
            hide-details
            rounded="lg"
            :items="operatingUnits"
            item-title="name"
            item-value="id"
            clearable
            :disabled="$page.props.auth.roles[0] !== 'superadmin' && $page.props.auth.roles[0] !== 'hr_director'"
            v-model="filterForm.operatingUnit"
          ></v-select>
        </v-col>

        <v-col cols="12" md="2">
          <v-select
            variant="outlined"
            density="compact"
            label="Direction"
            hide-details
            rounded="lg"
            :items="filterOptions.direction"
            v-model="filterForm.direction"
          ></v-select>
        </v-col>
        <v-col cols="12" md="2">
          <v-select
            variant="outlined"
            density="compact"
            label="Size"
            hide-details
            rounded="lg"
            :items="filterOptions.size"
            v-model="filterForm.size"
          ></v-select>
        </v-col>

        <v-col cols="12">
          <div class="d-flex align-center justify-end">
            <ButtonMuted name="Reset" class="mr-2" @click="resetFilter()" />
            <ButtonSuccess name="Search" type="submit" />
          </div>
        </v-col>
      </v-row>
    </v-form>
  </FilterWrapper>

  <TableWrapper>
    <v-skeleton-loader
      v-if="!leaveApplications"
      type="table"
      class="mx-auto mt-8"
    ></v-skeleton-loader>

    <v-table v-else>
      <thead>
        <tr>
          <th>Application Date</th>
          <th>Inclusive Dates</th>
          <th>Employee Name</th>
          <th>Leave Type</th>
          <th class="text-center">Status</th>
          <th class="text-center">Actions</th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="(leave, i) in leaveApplications.data" :key="i">
          <td>{{ leave.application_date }}</td>
          <td>
            <!-- ✅ SINGLE DATE (no expand) -->
            <span v-if="leave.inclusive_dates.length === 1">
              {{ leave.inclusive_dates[0].date }} -
              {{ leave.inclusive_dates[0].duration }}
            </span>

            <!-- ✅ MULTIPLE DATES (expandable) -->
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
          <td>{{ leave.employee_name || "N/A" }}</td>
          <td>{{ formatLeaveName(leave) }}</td>
          <td class="text-center">
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
            <div
              class="d-flex flex-wrap justify-center align-center gap-2 py-2"
              :class="$vuetify.display.smAndDown ? 'flex-column' : 'flex-row'"
            >
              <!-- Action Buttons -->
              <v-btn
                v-for="action in filteredActions(leave)"
                :key="action.key"
                variant="tonal"
                :color="action.color"
                rounded="xl"
                min-width="120"
                class="text-xs text-sm-md px-2 px-sm-4 action-btn"
                @click="initiateAction(leave, action)"
              >
                {{ action.label }}
              </v-btn>

              <!-- View Details Button -->
              <Link :href="leave.view_link">
                <v-btn
                  icon="mdi-eye-outline"
                  size="x-small"
                  variant="tonal"
                  color="blue-darken-1"
                />
              </Link>
            </div>
          </td>
        </tr>
      </tbody>
    </v-table>

    <Pagination
      class="mt-3"
      :meta="leaveApplications.meta"
      :filters="filterForm.data()"
    />
  </TableWrapper>

  <!-- Confirmation Dialog -->
  <v-dialog v-model="confirmDialog" max-width="500px" persistent>
    <v-card rounded="lg" elevation="3">
      <v-card-title class="text-h5 pa-4 bg-starbucks-green text-white">
        <v-icon icon="mdi-alert-circle" class="mr-2"></v-icon>
        Confirm Action
      </v-card-title>

      <v-card-text class="pa-6 text-body-1">
        Are you sure you want to <strong>{{ actionConfig.verb }}</strong> this
        leave application?

        <div class="text-subtitle-2 mt-2 text-red-darken-2 italics">
          This action cannot be undone.
        </div>

        <div v-if="actionConfig.requireRemarks" class="mt-4">
          <v-textarea
            v-model="updateLeaveApplicationStatusForm.remarks"
            :label="actionConfig.remarksLabel"
            variant="outlined"
            auto-grow
            rows="3"
            rounded="lg"
            :rules="[(v) => !!v || 'Reason is required']"
          ></v-textarea>
        </div>
      </v-card-text>

      <v-card-actions class="pa-4">
        <v-spacer></v-spacer>
        <v-btn
          color="grey-darken-1"
          variant="outlined"
          rounded="xl"
          min-width="100"
          @click="closeConfirmDialog"
          :disabled="loading"
        >
          NO
        </v-btn>

        <v-btn
          :color="availableActions.color || 'starbucks-green'"
          variant="elevated"
          rounded="xl"
          min-width="120"
          class="ml-3"
          @click="confirmLeaveAction"
          :loading="loading"
        >
          YES
        </v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>
  <!-- <pre>{{ leaveApplications }}</pre> -->
</template>

<script>
import SidebarLayout from "@/layouts/SidebarLayout.vue";
import LeaveManagementTabs from "@/components/LeaveManagementTabs.vue";
import FilterWrapper from "@/components/FilterWrapper.vue";
import TableWrapper from "@/components/TableWrapper.vue";
import ButtonSuccess from "@/components/ButtonSuccess.vue";
import ButtonMuted from "@/components/ButtonMuted.vue";
import { useForm, Link } from "@inertiajs/vue3";
import Pagination from "@/components/Pagination.vue";
import { defaultSizes, defaultDirections } from "@/utils/filters";

export default {
  layout: SidebarLayout,
  components: {
    LeaveManagementTabs,
    FilterWrapper,
    TableWrapper,
    ButtonSuccess,
    ButtonMuted,
    Pagination,
    Link,
  },
  props: {
    operatingUnits: [Object, Array],
    leaveTypes: [Object, Array],
    leaveApplications: Object,
  },
  data() {
    return {
      activeTab: "leave-list",
      isPanelOpen: [0],
      confirmDialog: false,
      confirmAction: null,
      selectedLeave: null,
      loading: false,

      leaveStatus: [
        { name: "Pending", value: "pending" },
        { name: "For Approval", value: "for approval" },
        { name: "For Disapproval", value: "for disapproval" },
        { name: "Certified", value: "certified" },
        { name: "Approved", value: "approved" },
        { name: "Disapproved", value: "disapproved" },
        { name: "Cancelled", value: "cancelled" },
      ],

      availableActions: [
        {
          key: "recommend",
          result: "for approval",
          label: "For approval",
          color: "info",
          verb: "recommend for approval",
          requireRemarks: false,
        },
        {
          key: "recommend",
          result: "for disapproval",
          label: "For disapproval",
          color: "warning",
          verb: "recommend for disapproval",
          requireRemarks: true,
          remarksLabel: "Reason for disapproval",
        },
        {
          key: "certify",
          result: "certified",
          label: "Certify",
          color: "primary",
          verb: "certify",
          requireRemarks: false,
        },
        {
          key: "approve",
          result: "approved",
          label: "Approve",
          color: "success",
          verb: "approve",
          requireRemarks: false,
        },
        {
          key: "approve",
          result: "disapproved",
          label: "Disapprove",
          color: "error",
          verb: "disapprove",
          requireRemarks: true,
          remarksLabel: "Reason for disapproval",
        },
        {
          key: "cancel",
          result: "cancelled",
          label: "Cancel",
          color: "gray",
          verb: "cancel",
          requireRemarks: true,
          remarksLabel: "Reason for cancellation",
        },
      ],

      updateLeaveApplicationStatusForm: useForm({
        row_id: null,
        status: null,
        remarks: null,
      }),

      filterForm: useForm({
        search: null,
        from: null,
        to: null,
        leaveType: null,
        status: null,
        operatingUnit: null,
        size: 10,
        direction: "Descending",
        employeeType: null,
      }),

      selectedEmployeeLeaveInformation: [
        {
          id: null,
          leave_type: null,
          from: null,
          to: null,
          status: null,
        },
      ],

      filterOptions: {
        size: defaultSizes,
        direction: defaultDirections,
      },
    };
  },
  computed: {
    filteredActions() {
      return (leave) => {
        return this.availableActions.filter((action) => {
          return leave.permissions[`can${this.capitalize(action.key)}`];
        });
      };
    },

    canViewDetails() {
      const role = this.$page.props.auth?.roles?.[0];
      return [
        "superadmin",
        "hr_director",
        "campus_hr",
        "campus_hr_staff",
      ].includes(role);
    },
  },
  methods: {
    summarizeDates(dates) {
      if (!dates.length) return "N/A";

      if (dates.length === 1) {
        return dates[0].date;
      }

      return `${dates[0].date} → ${dates[dates.length - 1].date} (${
        dates.length
      } days)`;
    },

    getStatusColor(status) {
      switch (status) {
        case "approved":
          return "green";
        case "pending":
          return "orange";
        case "rejected":
          return "red";
        case "for approval":
          return "blue";
        case "for disapproval":
          return "orange";
        case "certified":
          return "purple";
        default:
          return "grey";
      }
    },

    capitalize(str) {
      return str.charAt(0).toUpperCase() + str.slice(1);
    },

    getRowClass(status) {
      const classes = {
        disapproved: "bg-red-50 text-red-700",
        "for disapproval": "bg-orange-50 text-orange-700",
        approved: "bg-green-50 text-green-700",
        "for approval": "bg-blue-50 text-blue-700",
        certified: "bg-purple-50 text-purple-700",
        pending: "bg-yellow-50 text-yellow-700",
      };
      return classes[status] || "";
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

    initiateAction(leave, action) {
      this.confirmAction = action.key;
      this.actionConfig = action;
      this.selectedLeave = leave;
      this.updateLeaveApplicationStatusForm.remarks = null;
      this.confirmDialog = true;
    },

    closeConfirmDialog() {
      this.confirmDialog = false;
      this.confirmAction = null;
      this.selectedLeave = null;
      this.loading = false;
      this.updateLeaveApplicationStatusForm.reset();
    },

    confirmLeaveAction() {
      if (
        this.actionConfig.requireRemarks &&
        !this.updateLeaveApplicationStatusForm.remarks
      ) {
        this.showToast(
          `${this.actionConfig.remarksLabel} is required`,
          "error"
        );
        return;
      }

      this.loading = true;

      this.updateLeaveApplicationStatusForm.row_id = this.selectedLeave.id;

      // 🔥 Use result directly
      this.updateLeaveApplicationStatusForm.status = this.actionConfig.result;

      this.updateLeaveApplicationStatusForm.post(
        route("hrmanagement.leave.updateLeaveApplicationStatus"),
        {
          preserveState: true,
          preserveScroll: true,
          onSuccess: () => {
            this.showToast("Leave status updated successfully", "success");
            this.closeConfirmDialog();
          },
          onError: (errors) => {
            console.error(errors);
            this.showToast("Failed to update leave status", "error");
            this.loading = false;
          },
        }
      );
    },

    handleFilter() {
      this.filterForm.post(route("hrmanagement.leave.leaveList"), {
        preserveState: true,
        preserveScroll: true,
        only: ["leaveApplications"],
      });
    },

    resetFilter() {
      this.filterForm.reset();
      this.filterForm.direction = "Descending";
      this.handleFilter();
    },

    // Kept for backward compatibility or if needed by parent/components
    showToast(message, type) {
      this.$page.props.flash = { message, type };
      // Or use a dedicated toast event/store if available
    },
  },
};
</script>

<style scoped>
.gap-2 {
  gap: 8px;
}
.bg-starbucks-green {
  background-color: #006241 !important;
}

.action-btn {
  transition: background-color 0.2s ease;
}

.action-btn:hover {
  filter: brightness(85%);
}
</style>
