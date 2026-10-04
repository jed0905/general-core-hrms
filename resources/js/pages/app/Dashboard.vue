<template>
  <v-container fluid class="pa-4">
    <!-- =========================================================
         HEADER
    ========================================================== -->
    <div class="mb-6">
      <div class="text-h5 font-weight-bold">System Dashboard</div>

      <div class="text-body-2 text-medium-emphasis">
        Overview of your organization's HRMS
      </div>
    </div>

    <!-- =========================================================
         KPI CARDS
    ========================================================== -->
    <v-row dense>
      <v-col
        v-for="(card, index) in kpiCards"
        :key="index"
        cols="6"
        sm="6"
        md="3"
      >
        <v-card class="kpi-card pa-5" :class="card.colorClass" elevation="6">
          <div class="d-flex align-center justify-space-between">
            <div>
              <div class="kpi-value">
                {{ card.value }}
              </div>

              <div class="kpi-title">
                {{ card.title }}
              </div>
            </div>

            <v-icon class="kpi-icon">
              {{ card.icon }}
            </v-icon>
          </div>
        </v-card>
      </v-col>
    </v-row>

    <!-- =========================================================
         ORGANIZATION OVERVIEW
    ========================================================== -->
    <v-row dense class="mt-2">
      <!-- Employees Per Location -->
      <v-col cols="12" md="6">
        <v-card elevation="4" rounded="lg" class="dashboard-card">
          <div class="card-header">
            <div>
              <div class="card-title">Employees by Location</div>

              <div class="card-subtitle">
                Workforce distribution across company locations
              </div>
            </div>

            <v-icon color="primary"> mdi-map-marker-multiple </v-icon>
          </div>

          <div class="chart-container">
            <DonutChart :labels="locationLabels" :values="locationValues" />
          </div>

          <div class="card-footer">
            <span> Total Employees </span>

            <strong>
              {{ totalEmployees }}
            </strong>
          </div>
        </v-card>
      </v-col>

      <!-- Employees Per Department -->
      <v-col cols="12" md="6">
        <v-card elevation="4" rounded="lg" class="dashboard-card">
          <div class="card-header">
            <div>
              <div class="card-title">Employees by Department</div>

              <div class="card-subtitle">
                Workforce distribution by department
              </div>
            </div>

            <v-icon color="primary"> mdi-office-building </v-icon>
          </div>

          <div class="chart-container">
            <DonutChart :labels="departmentLabels" :values="departmentValues" />
          </div>

          <div class="card-footer">
            <span> Total Departments </span>

            <strong>
              {{ kpiData.totalDepartments }}
            </strong>
          </div>
        </v-card>
      </v-col>
    </v-row>

    <!-- =========================================================
         WORKFORCE
    ========================================================== -->
    <v-row dense class="mt-2">
      <!-- Employment Status -->
      <v-col cols="12" md="6">
        <v-card elevation="4" rounded="lg" class="dashboard-card">
          <div class="card-header">
            <div>
              <div class="card-title">Employment Status</div>

              <div class="card-subtitle">
                Current employee distribution by employment status
              </div>
            </div>

            <v-icon color="primary"> mdi-account-badge </v-icon>
          </div>

          <div class="chart-container">
            <DonutChart
              :labels="employmentStatusLabels"
              :values="employmentStatusValues"
            />
          </div>
        </v-card>
      </v-col>

      <!-- Job Titles -->
      <v-col cols="12" md="6">
        <v-card elevation="4" rounded="lg" class="dashboard-card">
          <div class="card-header">
            <div>
              <div class="card-title">Job Titles</div>

              <div class="card-subtitle">
                Distribution of employees by job title
              </div>
            </div>

            <v-icon color="primary"> mdi-briefcase-account </v-icon>
          </div>

          <div class="chart-container">
            <DonutChart :labels="jobTitleLabels" :values="jobTitleValues" />
          </div>
        </v-card>
      </v-col>
    </v-row>

    <!-- =========================================================
         TIME & ATTENDANCE
    ========================================================== -->
    <v-row dense class="mt-2">
      <v-col cols="12" md="12">
        <v-card elevation="4" rounded="lg" class="dashboard-card">
          <div class="card-header">
            <div>
              <div class="card-title">Today's Attendance</div>

              <div class="card-subtitle">Current attendance overview</div>
            </div>

            <v-icon color="primary"> mdi-clock-check </v-icon>
          </div>

          <v-row class="mt-2">
            <v-col
              v-for="item in attendanceCards"
              :key="item.title"
              cols="6"
              sm="3"
            >
              <div class="attendance-stat">
                <v-icon size="28" class="mb-2" :color="item.color">
                  {{ item.icon }}
                </v-icon>

                <div class="attendance-value">
                  {{ item.value }}
                </div>

                <div class="attendance-label">
                  {{ item.title }}
                </div>
              </div>
            </v-col>
          </v-row>

          <div class="mt-4">
            <v-progress-linear
              :model-value="attendancePercentage"
              height="10"
              rounded
            />

            <div class="d-flex justify-space-between mt-2">
              <span class="text-caption text-medium-emphasis">
                Attendance rate
              </span>

              <span class="text-caption font-weight-medium">
                {{ attendancePercentage }}%
              </span>
            </div>
          </div>
        </v-card>
      </v-col>

    </v-row>

    <!-- =========================================================
         LEAVE
    ========================================================== -->
    <v-row dense class="mt-2">
      <!-- Employees on Leave -->
      <v-col cols="12" md="6">
        <v-card elevation="4" rounded="lg" class="dashboard-card">
          <div class="card-header">
            <div>
              <div class="card-title">Employees on Leave Today</div>

              <div class="card-subtitle">
                Current approved leave applications
              </div>
            </div>

            <v-icon color="primary"> mdi-calendar-account </v-icon>
          </div>

          <div class="leave-summary">
            <div class="leave-number">
              {{ leaveSummary.onLeaveToday }}
            </div>

            <div class="text-body-2 text-medium-emphasis">
              Employees currently on leave
            </div>
          </div>

          <v-divider class="my-4" />

          <div class="d-flex justify-space-between">
            <span class="text-body-2"> Pending applications </span>

            <strong>
              {{ leaveSummary.pendingApplications }}
            </strong>
          </div>

          <div class="d-flex justify-space-between mt-2">
            <span class="text-body-2"> Applications this month </span>

            <strong>
              {{ leaveSummary.thisMonth }}
            </strong>
          </div>
        </v-card>
      </v-col>

      <!-- Leave Types -->
      <v-col cols="12" md="6">
        <v-card elevation="4" rounded="lg" class="dashboard-card">
          <div class="card-header">
            <div>
              <div class="card-title">Leave Utilization</div>

              <div class="card-subtitle">Leave applications by leave type</div>
            </div>

            <v-icon color="primary"> mdi-calendar-chart </v-icon>
          </div>

          <div class="chart-container">
            <StackedBarChart
              :labels="leaveSummary.typeLabels"
              :values="leaveSummary.typeValues"
            />
          </div>
        </v-card>
      </v-col>
    </v-row>

    <!-- =========================================================
         SYSTEM SETUP + RECENT ACTIVITY
    ========================================================== -->
    <v-row dense class="mt-2">
      <!-- Setup Progress -->
      <v-col cols="12" md="5">
        <v-card elevation="4" rounded="lg" class="dashboard-card">
          <div class="card-header">
            <div>
              <div class="card-title">HRMS Setup</div>

              <div class="card-subtitle">Organization configuration</div>
            </div>

            <v-icon color="primary"> mdi-cog-outline </v-icon>
          </div>

          <div class="setup-progress">
            <div class="d-flex align-center mb-4">
              <div class="setup-percentage">
                {{ setupProgress.percentage }}%
              </div>

              <div class="ml-4">
                <div class="font-weight-medium">Setup Completion</div>

                <div class="text-caption text-medium-emphasis">
                  {{ setupProgress.completed }}
                  of
                  {{ setupProgress.total }}
                  configured
                </div>
              </div>
            </div>

            <v-progress-linear
              :model-value="setupProgress.percentage"
              height="12"
              rounded
            />

            <div class="setup-items mt-4">
              <div
                v-for="item in setupProgress.items"
                :key="item.name"
                class="setup-item"
              >
                <div class="d-flex align-center">
                  <v-icon
                    size="20"
                    :color="item.completed ? 'success' : 'warning'"
                  >
                    {{
                      item.completed ? "mdi-check-circle" : "mdi-alert-circle"
                    }}
                  </v-icon>

                  <span class="ml-2">
                    {{ item.name }}
                  </span>
                </div>
              </div>
            </div>
          </div>
        </v-card>
      </v-col>

      <!-- Recent Activity -->
      <v-col cols="12" md="7">
        <v-card elevation="4" rounded="lg" class="dashboard-card">
          <div class="card-header">
            <div>
              <div class="card-title">Recent System Activity</div>

              <div class="card-subtitle">Latest administrative activities</div>
            </div>

            <v-icon color="primary"> mdi-history </v-icon>
          </div>

          <div v-if="recentActivities.length" class="activity-list">
            <div
              v-for="activity in recentActivities"
              :key="activity.id"
              class="activity-item"
            >
              <v-avatar size="38" color="grey-lighten-3">
                <v-icon>
                  {{ activity.icon || "mdi-information-outline" }}
                </v-icon>
              </v-avatar>

              <div class="ml-3 flex-grow-1">
                <div class="font-weight-medium">
                  {{ activity.description }}
                </div>

                <div class="text-caption text-medium-emphasis">
                  {{ activity.user }}
                  ·
                  {{ activity.time }}
                </div>
              </div>
            </div>
          </div>

          <div v-else class="empty-state">
            <v-icon size="45" color="grey-lighten-1"> mdi-history </v-icon>

            <div class="mt-2 text-body-2 text-medium-emphasis">
              No recent activity
            </div>
          </div>
        </v-card>
      </v-col>
    </v-row>
  </v-container>
</template>

<script>
import SidebarLayout from "@/layouts/SidebarLayout.vue";

import DonutChart from "@/components/DonutChart.vue";

export default {
  layout: SidebarLayout,

  components: {
    DonutChart,
  },

  props: {
    /*
    |--------------------------------------------------------------------------
    | KPI
    |--------------------------------------------------------------------------
    */

    kpiData: {
      type: Object,
      required: true,
    },

    /*
    |--------------------------------------------------------------------------
    | Organization
    |--------------------------------------------------------------------------
    */

    employeesPerLocation: {
      type: Array,
      required: true,
    },

    employeesPerDepartment: {
      type: Array,
      required: true,
    },

    /*
    |--------------------------------------------------------------------------
    | Workforce
    |--------------------------------------------------------------------------
    */

    employeeStatusCounts: {
      type: Array,
      required: true,
    },

    jobTitleCounts: {
      type: Array,
      required: true,
    },

    /*
    |--------------------------------------------------------------------------
    | Attendance
    |--------------------------------------------------------------------------
    */

    attendanceSummary: {
      type: Object,
      required: true,
    },

    attendanceDevices: {
      type: Array,
      required: true,
    },

    /*
    |--------------------------------------------------------------------------
    | Leave
    |--------------------------------------------------------------------------
    */

    leaveSummary: {
      type: Object,
      required: true,
    },

    /*
    |--------------------------------------------------------------------------
    | System
    |--------------------------------------------------------------------------
    */

    setupProgress: {
      type: Object,
      required: true,
    },

    recentActivities: {
      type: Array,
      required: true,
    },
  },

  computed: {
    /*
    |--------------------------------------------------------------------------
    | KPI
    |--------------------------------------------------------------------------
    */

    kpiCards() {
      return [
        {
          title: "Locations",
          value: this.kpiData.totalLocations,
          icon: "mdi-map-marker-multiple",
          colorClass: "kpi-green",
        },
        {
          title: "Departments",
          value: this.kpiData.totalDepartments,
          icon: "mdi-office-building",
          colorClass: "kpi-blue",
        },
        {
          title: "Active Employees",
          value: this.kpiData.totalActiveEmployees,
          icon: "mdi-account-group",
          colorClass: "kpi-orange",
        },
        {
          title: "Active Users",
          value: this.kpiData.totalActiveUsers,
          icon: "mdi-account-check",
          colorClass: "kpi-purple",
        },
      ];
    },

    /*
    |--------------------------------------------------------------------------
    | Location Chart
    |--------------------------------------------------------------------------
    */

    locationLabels() {
      return this.employeesPerLocation.map((item) => item.location);
    },

    locationValues() {
      return this.employeesPerLocation.map((item) =>
        Number(item.employee_count)
      );
    },

    totalEmployees() {
      return this.locationValues.reduce((total, value) => total + value, 0);
    },

    /*
    |--------------------------------------------------------------------------
    | Department Chart
    |--------------------------------------------------------------------------
    */

    departmentLabels() {
      return this.employeesPerDepartment.map((item) => item.department);
    },

    departmentValues() {
      return this.employeesPerDepartment.map((item) =>
        Number(item.employee_count)
      );
    },

    /*
    |--------------------------------------------------------------------------
    | Employment Status
    |--------------------------------------------------------------------------
    */

    employmentStatusLabels() {
      return this.employeeStatusCounts.map((item) => item.status);
    },

    employmentStatusValues() {
      return this.employeeStatusCounts.map((item) =>
        Number(item.employee_count)
      );
    },

    /*
    |--------------------------------------------------------------------------
    | Job Titles
    |--------------------------------------------------------------------------
    */

    jobTitleLabels() {
      return this.jobTitleCounts.map((item) => item.job_title);
    },

    jobTitleValues() {
      return this.jobTitleCounts.map((item) => Number(item.employee_count));
    },

    /*
    |--------------------------------------------------------------------------
    | Attendance
    |--------------------------------------------------------------------------
    */

    attendanceCards() {
      return [
        {
          title: "Present",
          value: this.attendanceSummary.present,
          icon: "mdi-account-check",
          color: "success",
        },
        {
          title: "Late",
          value: this.attendanceSummary.late,
          icon: "mdi-clock-alert",
          color: "warning",
        },
        {
          title: "Absent",
          value: this.attendanceSummary.absent,
          icon: "mdi-account-remove",
          color: "error",
        },
        {
          title: "On Leave",
          value: this.attendanceSummary.onLeave,
          icon: "mdi-calendar-account",
          color: "info",
        },
      ];
    },

    attendancePercentage() {
      const total =
        this.attendanceSummary.present +
        this.attendanceSummary.late +
        this.attendanceSummary.absent +
        this.attendanceSummary.onLeave;

      if (!total) {
        return 0;
      }

      return Math.round(
        ((this.attendanceSummary.present + this.attendanceSummary.late) /
          total) *
          100
      );
    },
  },
};
</script>

<style scoped>
/*
|--------------------------------------------------------------------------
| General
|--------------------------------------------------------------------------
*/

.dashboard-card {
  height: 100%;
  min-height: 300px;
  padding: 20px;
}

.card-header {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  margin-bottom: 16px;
}

.card-title {
  font-size: 1rem;
  font-weight: 600;
}

.card-subtitle {
  margin-top: 3px;
  font-size: 0.78rem;
  color: rgba(0, 0, 0, 0.55);
}

/*
|--------------------------------------------------------------------------
| KPI
|--------------------------------------------------------------------------
*/

.kpi-card {
  border-radius: 16px;
  color: #fff;
  overflow: hidden;

  transition: transform 0.25s ease, box-shadow 0.25s ease;
}

.kpi-card:hover {
  transform: translateY(-4px);
}

.kpi-value {
  font-size: 2rem;
  font-weight: 700;
  line-height: 1.2;
}

.kpi-title {
  font-size: 0.9rem;
  opacity: 0.9;
  margin-top: 3px;
}

.kpi-icon {
  font-size: 52px;
  opacity: 0.25;
}

.kpi-green {
  background: linear-gradient(135deg, #43a047, #66bb6a);
}

.kpi-blue {
  background: linear-gradient(135deg, #1e88e5, #42a5f5);
}

.kpi-orange {
  background: linear-gradient(135deg, #fb8c00, #ffb74d);
}

.kpi-purple {
  background: linear-gradient(135deg, #8e24aa, #ba68c8);
}

/*
|--------------------------------------------------------------------------
| Charts
|--------------------------------------------------------------------------
*/

.chart-container {
  height: 230px;

  display: flex;
  justify-content: center;
  align-items: center;
}

.card-footer {
  display: flex;
  justify-content: space-between;

  padding-top: 10px;

  font-size: 0.85rem;
}

/*
|--------------------------------------------------------------------------
| Attendance
|--------------------------------------------------------------------------
*/

.attendance-stat {
  text-align: center;
  padding: 8px;
}

.attendance-value {
  font-size: 1.5rem;
  font-weight: 700;
}

.attendance-label {
  font-size: 0.78rem;
  color: rgba(0, 0, 0, 0.55);
}

/*
|--------------------------------------------------------------------------
| Devices
|--------------------------------------------------------------------------
*/

.device-list {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.device-item {
  display: flex;
  align-items: center;
  justify-content: space-between;

  padding: 10px;

  border-radius: 10px;
  background: #f7f7f7;
}

/*
|--------------------------------------------------------------------------
| Leave
|--------------------------------------------------------------------------
*/

.leave-summary {
  text-align: center;
  padding: 25px 0 10px;
}

.leave-number {
  font-size: 3rem;
  font-weight: 700;
}

/*
|--------------------------------------------------------------------------
| Setup
|--------------------------------------------------------------------------
*/

.setup-percentage {
  font-size: 2.4rem;
  font-weight: 700;
}

.setup-items {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 8px;
}

.setup-item {
  font-size: 0.82rem;
}

/*
|--------------------------------------------------------------------------
| Activity
|--------------------------------------------------------------------------
*/

.activity-list {
  display: flex;
  flex-direction: column;
}

.activity-item {
  display: flex;
  align-items: center;

  padding: 11px 0;

  border-bottom: 1px solid #eeeeee;
}

.activity-item:last-child {
  border-bottom: none;
}

/*
|--------------------------------------------------------------------------
| Empty
|--------------------------------------------------------------------------
*/

.empty-state {
  min-height: 180px;

  display: flex;
  flex-direction: column;

  align-items: center;
  justify-content: center;

  text-align: center;
}

/*
|--------------------------------------------------------------------------
| Mobile
|--------------------------------------------------------------------------
*/

@media (max-width: 600px) {
  .dashboard-card {
    padding: 16px;
  }

  .kpi-card {
    padding: 16px !important;
  }

  .kpi-value {
    font-size: 1.5rem;
  }

  .kpi-icon {
    font-size: 40px;
  }

  .setup-items {
    grid-template-columns: 1fr;
  }
}
</style>