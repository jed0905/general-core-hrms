```vue
<template>
  <v-container fluid class="pa-4 pa-md-6">
    <!-- Header -->
    <div class="mb-6">
      <div class="d-flex align-center justify-space-between flex-wrap ga-3">
        <div>
          <div class="text-h5 text-md-h4 font-weight-bold">
            Good afternoon, {{ employeeName }} 👋
          </div>

          <div class="text-body-2 text-grey-darken-1 mt-1">
            {{ currentDate }}
          </div>
        </div>

        <v-chip
          color="starbucks-green"
          variant="tonal"
          prepend-icon="mdi-account-check"
        >
          Employee Self-Service
        </v-chip>
      </div>
    </div>

    <!-- Today's Workday -->
    <v-row>
      <!-- Today's Schedule -->
      <v-col cols="12" md="4">
        <v-card class="dashboard-card" rounded="xl" elevation="2">
          <v-card-item>
            <template #prepend>
              <v-avatar color="starbucks-green" variant="tonal" size="44">
                <v-icon>mdi-calendar-clock</v-icon>
              </v-avatar>
            </template>

            <v-card-title class="font-weight-bold">
              Today's Schedule
            </v-card-title>

            <v-card-subtitle> Your assigned work schedule </v-card-subtitle>
          </v-card-item>

          <v-card-text>
            <div class="schedule-time">08:00 AM – 05:00 PM</div>

            <div class="d-flex align-center mt-2">
              <v-icon size="18" class="mr-2" color="grey-darken-1">
                mdi-coffee
              </v-icon>

              <span class="text-body-2 text-grey-darken-1">
                Break: 12:00 PM – 01:00 PM
              </span>
            </div>

            <v-divider class="my-4" />

            <div class="d-flex align-center">
              <v-icon size="18" class="mr-2" color="starbucks-green">
                mdi-check-circle
              </v-icon>

              <span class="text-body-2 font-weight-medium">
                Regular Office Schedule
              </span>
            </div>
          </v-card-text>
        </v-card>
      </v-col>

      <!-- Today's Attendance -->
      <v-col cols="12" md="4">
        <v-card class="dashboard-card" rounded="xl" elevation="2">
          <v-card-item>
            <template #prepend>
              <v-avatar color="starbucks-green" variant="tonal" size="44">
                <v-icon>mdi-clock-check-outline</v-icon>
              </v-avatar>
            </template>

            <v-card-title class="font-weight-bold">
              Today's Attendance
            </v-card-title>

            <v-card-subtitle> Sunday, August 30 </v-card-subtitle>
          </v-card-item>

          <v-card-text>
            <div class="d-flex align-center mb-4">
              <v-avatar size="48" color="starbucks-green" class="mr-3">
                <v-icon color="white"> mdi-account-clock </v-icon>
              </v-avatar>

              <div>
                <div class="text-subtitle-1 font-weight-bold">Punched Out</div>

                <div class="text-caption text-grey-darken-1">
                  Last punch: 05:03 PM
                </div>
              </div>
            </div>

            <div class="attendance-summary">
              <div>
                <div class="text-caption text-grey-darken-1">Time Worked</div>

                <div class="text-h5 font-weight-bold">8h 03m</div>
              </div>

              <v-icon size="28" color="starbucks-green">
                mdi-timer-check-outline
              </v-icon>
            </div>

            <v-btn
              block
              variant="outlined"
              color="starbucks-green"
              class="mt-4"
              :href="route('self-service.my-dtr.index')"
            >
              View My Timesheet
            </v-btn>
          </v-card-text>
        </v-card>
      </v-col>

      <!-- Leave Balance -->
      <v-col cols="12" md="4">
        <v-card class="dashboard-card" rounded="xl" elevation="2">
          <v-card-item>
            <template #prepend>
              <v-avatar color="orange" variant="tonal" size="44">
                <v-icon>mdi-beach</v-icon>
              </v-avatar>
            </template>

            <v-card-title class="font-weight-bold">
              Leave Balance
            </v-card-title>

            <v-card-subtitle> Current available credits </v-card-subtitle>
          </v-card-item>

          <v-card-text>
            <div class="leave-balance">
              <div>
                <div class="text-caption text-grey-darken-1">
                  Available Leave
                </div>

                <div class="text-h4 font-weight-bold">
                  12.5
                  <span class="text-body-2 font-weight-medium"> days </span>
                </div>
              </div>

              <v-icon size="40" color="orange"> mdi-calendar-heart </v-icon>
            </div>

            <v-divider class="my-4" />

            <div class="d-flex justify-space-between text-body-2">
              <span class="text-grey-darken-1"> Pending Requests </span>

              <span class="font-weight-bold"> 1 </span>
            </div>

            <v-btn block variant="text" color="starbucks-green" class="mt-2">
              View Leave Details
            </v-btn>
          </v-card-text>
        </v-card>
      </v-col>
    </v-row>

    <!-- Quick Actions -->
    <v-row class="mt-1">
      <v-col cols="12">
        <v-card rounded="xl" elevation="2" class="pa-4">
          <div class="d-flex align-center mb-4">
            <v-icon color="starbucks-green" class="mr-2">
              mdi-lightning-bolt
            </v-icon>

            <div>
              <div class="text-h6 font-weight-bold">Quick Actions</div>

              <div class="text-caption text-grey-darken-1">
                Frequently used employee services
              </div>
            </div>
          </div>

          <v-row>
            <v-col cols="6" sm="3">
              <Link
                :href="route('self-service.my-dtr.index')"
                class="quick-action-link"
              >
                <div class="quick-action">
                  <v-avatar size="48" color="starbucks-green" variant="tonal">
                    <v-icon> mdi-clock-check-outline </v-icon>
                  </v-avatar>

                  <div class="mt-2 font-weight-medium">My Timesheet</div>

                  <div class="text-caption text-grey-darken-1">Attendance</div>
                </div>
              </Link>
            </v-col>

            <v-col cols="6" sm="3">
              <div class="quick-action">
                <v-avatar size="48" color="orange" variant="tonal">
                  <v-icon> mdi-calendar-plus </v-icon>
                </v-avatar>

                <div class="mt-2 font-weight-medium">Apply Leave</div>

                <div class="text-caption text-grey-darken-1">
                  Request time off
                </div>
              </div>
            </v-col>

            <v-col cols="6" sm="3">
              <Link
                :href="route('self-service.my-profile.index')"
                class="quick-action-link"
              >
                <div class="quick-action">
                  <v-avatar size="48" color="blue" variant="tonal">
                    <v-icon> mdi-account-cog-outline </v-icon>
                  </v-avatar>

                  <div class="mt-2 font-weight-medium">My Profile</div>

                  <div class="text-caption text-grey-darken-1">
                    Personal information
                  </div>
                </div>
              </Link>
            </v-col>

            <v-col cols="6" sm="3">
              <div class="quick-action">
                <v-avatar size="48" color="purple" variant="tonal">
                  <v-icon> mdi-file-document-outline </v-icon>
                </v-avatar>

                <div class="mt-2 font-weight-medium">My Requests</div>

                <div class="text-caption text-grey-darken-1">
                  Track requests
                </div>
              </div>
            </v-col>
          </v-row>
        </v-card>
      </v-col>
    </v-row>

    <!-- Lower Section -->
    <v-row class="mt-1">
      <!-- My Requests -->
      <v-col cols="12" md="7">
        <v-card rounded="xl" elevation="2" class="dashboard-card">
          <v-card-item>
            <template #prepend>
              <v-avatar color="blue" variant="tonal" size="44">
                <v-icon> mdi-file-clock-outline </v-icon>
              </v-avatar>
            </template>

            <v-card-title class="font-weight-bold">
              My Recent Requests
            </v-card-title>

            <v-card-subtitle> Track your recent HR requests </v-card-subtitle>
          </v-card-item>

          <v-card-text>
            <div class="request-item">
              <div class="d-flex align-center">
                <v-avatar size="40" color="orange" variant="tonal" class="mr-3">
                  <v-icon> mdi-beach </v-icon>
                </v-avatar>

                <div>
                  <div class="font-weight-medium">Vacation Leave</div>

                  <div class="text-caption text-grey-darken-1">
                    August 24 – August 26, 2026
                  </div>
                </div>
              </div>

              <v-chip size="small" color="orange" variant="tonal">
                Pending
              </v-chip>
            </div>

            <v-divider class="my-3" />

            <div class="request-item">
              <div class="d-flex align-center">
                <v-avatar size="40" color="blue" variant="tonal" class="mr-3">
                  <v-icon> mdi-file-document </v-icon>
                </v-avatar>

                <div>
                  <div class="font-weight-medium">Certificate Request</div>

                  <div class="text-caption text-grey-darken-1">
                    August 18, 2026
                  </div>
                </div>
              </div>

              <v-chip size="small" color="success" variant="tonal">
                Approved
              </v-chip>
            </div>

            <v-btn block variant="text" color="starbucks-green" class="mt-3">
              View All Requests
            </v-btn>
          </v-card-text>
        </v-card>
      </v-col>

      <!-- Upcoming -->
      <v-col cols="12" md="5">
        <v-card rounded="xl" elevation="2" class="dashboard-card">
          <v-card-item>
            <template #prepend>
              <v-avatar color="red" variant="tonal" size="44">
                <v-icon> mdi-calendar-star </v-icon>
              </v-avatar>
            </template>

            <v-card-title class="font-weight-bold"> Upcoming </v-card-title>

            <v-card-subtitle> Important dates </v-card-subtitle>
          </v-card-item>

          <v-card-text>
            <div class="upcoming-item">
              <div class="date-box">
                <div class="text-caption">SEP</div>

                <div class="text-h6 font-weight-bold">03</div>
              </div>

              <div class="ml-3">
                <div class="font-weight-medium">Company Holiday</div>

                <div class="text-caption text-grey-darken-1">
                  Non-working day
                </div>
              </div>
            </div>

            <v-divider class="my-3" />

            <div class="upcoming-item">
              <div class="date-box">
                <div class="text-caption">SEP</div>

                <div class="text-h6 font-weight-bold">15</div>
              </div>

              <div class="ml-3">
                <div class="font-weight-medium">Payroll Date</div>

                <div class="text-caption text-grey-darken-1">
                  Next payroll schedule
                </div>
              </div>
            </div>
          </v-card-text>
        </v-card>
      </v-col>
    </v-row>

    <!-- Announcements -->
    <v-row class="mt-1">
      <v-col cols="12">
        <v-card rounded="xl" elevation="2">
          <v-card-item>
            <template #prepend>
              <v-avatar color="starbucks-green" variant="tonal" size="44">
                <v-icon> mdi-bullhorn-outline </v-icon>
              </v-avatar>
            </template>

            <v-card-title class="font-weight-bold">
              Announcements
            </v-card-title>

            <v-card-subtitle> Company updates and notices </v-card-subtitle>
          </v-card-item>

          <v-card-text>
            <div class="announcement">
              <div>
                <div class="font-weight-medium">HR System Maintenance</div>

                <div class="text-caption text-grey-darken-1">
                  The HRMS will undergo scheduled maintenance this weekend.
                </div>
              </div>

              <div class="text-caption text-grey-darken-1">Aug 28</div>
            </div>
          </v-card-text>
        </v-card>
      </v-col>
    </v-row>
  </v-container>
</template>

<script>
import SidebarLayout from "@/layouts/SidebarLayout.vue";
import { Link } from "@inertiajs/vue3";

export default {
  layout: SidebarLayout,

  components: {
    Link,
  },

  data() {
    return {
      flash_status: null,

      employeeName: "John",

      currentDate: "Sunday, August 30, 2026",
    };
  },

  mounted() {
    const status = this.$page.props.flash?.status || null;

    if (status) {
      this.flash_status = status;
    }
  },

  watch: {
    flash_status(newStatus) {
      if (newStatus) {
        this.showToast("Welcome");
      }
    },
  },
};
</script>

<style scoped>
.dashboard-card {
  height: 100%;
  min-height: 280px;
}

.schedule-time {
  font-size: 1.65rem;
  font-weight: 700;
  letter-spacing: -0.02em;
}

.attendance-summary,
.leave-balance {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 16px;
  background: rgb(var(--v-theme-grey-lighten-4));
  border-radius: 16px;
}

.quick-action-link {
  color: inherit;
  text-decoration: none;
}

.quick-action {
  min-height: 120px;
  padding: 12px;
  border-radius: 16px;
  text-align: center;
  cursor: pointer;
  transition: 0.2s ease;
}

.quick-action:hover {
  background: rgba(0, 0, 0, 0.04);
  transform: translateY(-2px);
}

.request-item,
.upcoming-item,
.announcement {
  display: flex;
  align-items: center;
  justify-content: space-between;
}

.date-box {
  width: 52px;
  min-width: 52px;
  padding: 6px;
  text-align: center;
  border-radius: 12px;
  background: rgba(0, 0, 0, 0.05);
}
</style>
```
