<template>
  <SidebarLayout>
    <Head title="My Dashboard" />

    <v-container fluid class="pa-4 pa-sm-6">
      <!-- Greeting -->
      <div class="mb-6">
        <h1 class="text-h5 font-weight-bold">
          {{ greeting }}<span v-if="employee">, {{ firstName }}</span>
        </h1>
        <p class="text-body-2 text-medium-emphasis">{{ todayLabel }}</p>
      </div>

      <v-alert v-if="!hasEmployeeRecord" type="info" variant="tonal">
        Your account is not linked to an employee record, so there is no
        personal information to show. Contact HR if this is unexpected.
      </v-alert>

      <template v-else>
        <v-row>
          <!-- Employee summary -->
          <v-col v-if="employee" cols="12" md="4">
            <v-card variant="outlined" class="rounded-lg h-100">
              <v-card-text>
                <div class="d-flex align-center ga-3 mb-4">
                  <v-avatar color="primary" variant="tonal" size="48">
                    <span class="text-h6">{{ initials }}</span>
                  </v-avatar>
                  <div>
                    <div class="text-subtitle-1 font-weight-bold">
                      {{ employee.name }}
                    </div>
                    <div class="text-caption text-medium-emphasis">
                      {{ employee.employee_number }}
                    </div>
                  </div>
                </div>
                <div v-for="row in summaryRows" :key="row.label" class="mb-2">
                  <div class="text-caption text-medium-emphasis">{{ row.label }}</div>
                  <div class="text-body-2 font-weight-medium">{{ row.value || "—" }}</div>
                </div>
              </v-card-text>
              <v-card-actions v-if="can('employee.view_own')">
                <v-btn variant="text" color="primary" size="small" @click="go('people.my-profile.show')">
                  My Profile
                </v-btn>
              </v-card-actions>
            </v-card>
          </v-col>

          <!-- Today's work -->
          <v-col v-if="today || attendance" cols="12" :md="employee ? 4 : 6">
            <v-card variant="outlined" class="rounded-lg h-100">
              <v-card-title class="text-subtitle-1 font-weight-bold">Today</v-card-title>
              <v-card-text>
                <template v-if="today">
                  <div v-if="today.holiday" class="mb-3">
                    <v-chip color="info" variant="tonal" size="small" prepend-icon="mdi-calendar-star">
                      {{ today.holiday.name }}
                    </v-chip>
                  </div>
                  <div v-if="!today.has_schedule" class="text-body-2 text-medium-emphasis mb-3">
                    No work schedule assigned.
                  </div>
                  <div v-else-if="today.is_working_day && today.shift" class="mb-3">
                    <div class="text-caption text-medium-emphasis">{{ today.schedule?.name }}</div>
                    <div class="text-h6 font-weight-bold">
                      {{ time(today.shift.start_time) }} – {{ time(today.shift.end_time) }}
                    </div>
                    <div class="text-caption text-medium-emphasis">
                      {{ today.shift.name }} · {{ today.shift.required_hours }} hrs
                    </div>
                  </div>
                  <div v-else class="text-body-2 mb-3">Rest day</div>
                </template>

                <template v-if="attendance">
                  <v-divider v-if="today" class="mb-3" />
                  <div class="text-caption text-medium-emphasis">Attendance</div>
                  <v-chip :color="statusColor" variant="tonal" size="small" class="mt-1">
                    {{ statusLabel }}
                  </v-chip>
                  <div v-if="attendance.first_in" class="text-body-2 mt-2">
                    In {{ attendance.first_in }}
                    <span v-if="attendance.last_out"> · Out {{ attendance.last_out }}</span>
                  </div>
                  <div v-if="!attendance.has_biometric_id" class="text-caption text-medium-emphasis mt-2">
                    No attendance device ID is registered for you yet.
                  </div>
                </template>
              </v-card-text>
            </v-card>
          </v-col>

          <!-- Next working day / schedule -->
          <v-col v-if="today" cols="12" :md="employee ? 4 : 6">
            <v-card variant="outlined" class="rounded-lg h-100">
              <v-card-title class="text-subtitle-1 font-weight-bold">Work Schedule</v-card-title>
              <v-card-text>
                <div class="text-caption text-medium-emphasis">Current schedule</div>
                <div class="text-body-1 font-weight-medium mb-3">
                  {{ today.schedule?.name || "None assigned" }}
                </div>
                <div class="text-caption text-medium-emphasis">Next working day</div>
                <div v-if="nextWorkingDay" class="text-body-1 font-weight-medium">
                  {{ formatDate(nextWorkingDay.date, { weekday: "long", month: "short", day: "numeric" }) }}
                  <div v-if="nextWorkingDay.shift" class="text-caption text-medium-emphasis">
                    {{ time(nextWorkingDay.shift.start_time) }} – {{ time(nextWorkingDay.shift.end_time) }}
                  </div>
                </div>
                <div v-else class="text-body-2 text-medium-emphasis">None in the next month</div>
              </v-card-text>
              <v-card-actions>
                <v-btn variant="text" color="primary" size="small" @click="go('time.my-schedule.index')">
                  My Work Schedule
                </v-btn>
              </v-card-actions>
            </v-card>
          </v-col>
        </v-row>

        <v-row>
          <!-- Leave balances -->
          <v-col v-if="leaveBalances" cols="12" md="6">
            <v-card variant="outlined" class="rounded-lg h-100">
              <v-card-title class="d-flex align-center justify-space-between">
                <span class="text-subtitle-1 font-weight-bold">Leave Balance</span>
                <v-btn
                  v-if="can('leave.create')"
                  size="small"
                  color="primary"
                  variant="flat"
                  prepend-icon="mdi-plus"
                  @click="go('leave.applications.create')"
                >
                  Apply Leave
                </v-btn>
              </v-card-title>
              <v-card-text>
                <p v-if="!leaveBalances.length" class="text-body-2 text-medium-emphasis">
                  No leave balances have been set up for you yet.
                </p>
                <div v-for="b in leaveBalances" :key="b.id" class="d-flex align-center justify-space-between py-2">
                  <div>
                    <div class="font-weight-medium">{{ b.leave_type?.name }}</div>
                    <div class="text-caption text-medium-emphasis">
                      Pending {{ b.pending }} · Used {{ b.used }}
                    </div>
                  </div>
                  <div class="text-end">
                    <div class="text-h6 font-weight-bold">{{ b.available }}</div>
                    <div class="text-caption text-medium-emphasis">available</div>
                  </div>
                </div>
              </v-card-text>
              <v-card-actions>
                <v-btn variant="text" color="primary" size="small" @click="go('leave.applications.my-balances')">
                  Leave Balance
                </v-btn>
              </v-card-actions>
            </v-card>
          </v-col>

          <!-- Recent leave applications -->
          <v-col v-if="recentLeave" cols="12" md="6">
            <v-card variant="outlined" class="rounded-lg h-100">
              <v-card-title class="text-subtitle-1 font-weight-bold">Recent Leave Applications</v-card-title>
              <v-card-text>
                <p v-if="!recentLeave.length" class="text-body-2 text-medium-emphasis">
                  You have not filed any leave yet.
                </p>
                <div
                  v-for="app in recentLeave"
                  :key="app.id"
                  class="d-flex align-center justify-space-between py-2"
                >
                  <div>
                    <div class="font-weight-medium">{{ app.leave_type?.name }}</div>
                    <div class="text-caption text-medium-emphasis">
                      {{ dateRange(app.dates) }} · {{ app.total_days }} day(s)
                    </div>
                  </div>
                  <v-chip :color="leaveStatusColor(app.status)" size="small" variant="tonal">
                    {{ app.status }}
                  </v-chip>
                </div>
              </v-card-text>
              <v-card-actions>
                <v-btn variant="text" color="primary" size="small" @click="go('leave.applications.index')">
                  My Applications
                </v-btn>
              </v-card-actions>
            </v-card>
          </v-col>

          <!-- Recent attendance -->
          <v-col v-if="attendance" cols="12">
            <v-card variant="outlined" class="rounded-lg">
              <v-card-title class="text-subtitle-1 font-weight-bold">Recent Attendance</v-card-title>
              <v-table density="compact">
                <thead>
                  <tr>
                    <th>Date</th>
                    <th>Time</th>
                    <th>Direction</th>
                    <th>Device</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-if="!attendance.recent.length">
                    <td colspan="4" class="text-center py-4 text-medium-emphasis">No records yet.</td>
                  </tr>
                  <tr v-for="log in attendance.recent" :key="log.id">
                    <td>{{ formatDate(log.auth_date) }}</td>
                    <td>{{ log.auth_time }}</td>
                    <td>{{ log.direction }}</td>
                    <td>{{ log.device_name }}</td>
                  </tr>
                </tbody>
              </v-table>
              <v-card-actions>
                <v-btn variant="text" color="primary" size="small" @click="go('time.my-attendance.index')">
                  My Attendance
                </v-btn>
              </v-card-actions>
            </v-card>
          </v-col>
        </v-row>
      </template>
    </v-container>
  </SidebarLayout>
</template>

<script>
import { Head, router } from "@inertiajs/vue3";
import SidebarLayout from "@/layouts/SidebarLayout.vue";
import permissions from "@/mixins/permissions";

const ATTENDANCE_STATUS = {
  timed_in: { label: "Timed in", color: "success" },
  timed_in_and_out: { label: "Timed in and out", color: "success" },
  on_leave: { label: "On leave", color: "info" },
  holiday: { label: "Holiday", color: "info" },
  rest_day: { label: "Rest day", color: "grey" },
  no_record: { label: "No time-in yet", color: "warning" },
};

export default {
  name: "MyDashboardIndex",
  components: { SidebarLayout, Head },
  mixins: [permissions],
  props: {
    hasEmployeeRecord: { type: Boolean, default: false },
    employee: { type: Object, default: null },
    today: { type: Object, default: null },
    nextWorkingDay: { type: Object, default: null },
    attendance: { type: Object, default: null },
    leaveBalances: { type: Array, default: null },
    recentLeave: { type: Array, default: null },
  },
  computed: {
    firstName() {
      return (this.employee?.name || "").split(" ")[0];
    },
    initials() {
      return (this.employee?.name || "")
        .split(" ")
        .filter(Boolean)
        .slice(0, 2)
        .map((part) => part[0])
        .join("")
        .toUpperCase();
    },
    greeting() {
      const hour = new Date().getHours();
      if (hour < 12) return "Good morning";
      if (hour < 18) return "Good afternoon";
      return "Good evening";
    },
    todayLabel() {
      return new Date().toLocaleDateString(undefined, {
        weekday: "long",
        year: "numeric",
        month: "long",
        day: "numeric",
      });
    },
    summaryRows() {
      return [
        { label: "Job Title", value: this.employee?.job_title },
        { label: "Department", value: this.employee?.department },
        { label: "Location", value: this.employee?.location },
      ];
    },
    statusLabel() {
      return ATTENDANCE_STATUS[this.attendance?.status]?.label ?? this.attendance?.status;
    },
    statusColor() {
      return ATTENDANCE_STATUS[this.attendance?.status]?.color ?? "default";
    },
  },
  methods: {
    go(routeName) {
      router.visit(route(routeName));
    },
    time(value) {
      return value ? String(value).substring(0, 5) : "";
    },
    formatDate(value, options = undefined) {
      return value ? new Date(`${String(value).substring(0, 10)}T00:00:00`).toLocaleDateString(undefined, options) : "";
    },
    dateRange(dates) {
      if (!dates?.length) return "";
      const first = this.formatDate(dates[0].leave_date, { month: "short", day: "numeric" });
      const last = this.formatDate(dates[dates.length - 1].leave_date, { month: "short", day: "numeric" });
      return first === last ? first : `${first} – ${last}`;
    },
    leaveStatusColor(status) {
      return (
        {
          pending: "warning",
          approved: "success",
          rejected: "error",
          returned: "info",
          cancelled: "grey",
        }[status] || "default"
      );
    },
  },
};
</script>
