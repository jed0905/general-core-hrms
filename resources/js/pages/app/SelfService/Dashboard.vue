<template>
  <v-container fluid>
    <v-row>
      <!-- Quick Launch Card -->
      <v-col cols="12" lg="3" md="12">
        <v-card class="pa-4" rounded="xl" elevation="4">
          <div class="d-flex align-center mb-4">
            <v-icon class="mr-2" color="starbucks-green"
              >mdi-lightning-bolt</v-icon
            >
            <h3 class="text-h6 font-weight-medium">Quick Launch</h3>
          </div>

          <div class="d-flex justify-space-around">
            <!-- Unavailable -->
            <!-- <div class="text-center">
              <v-btn
                icon="mdi-account-arrow-right"
                size="large"
                color="primary"
                class="mb-2"
                variant="outlined"
              ></v-btn>
              <div class="text-caption">Apply Leave</div>
            </div> -->

            <!-- Unavialable -->
            <!-- <div class="text-center">
              <v-btn
                icon="mdi-palm-tree"
                size="large"
                color="primary"
                class="mb-2"
                variant="outlined"
              ></v-btn>
              <div class="text-caption">My Leave</div>
            </div> -->

            <Link :href="route('self-service.my-dtr.index')">
              <div class="text-center">
                <v-btn
                  icon="mdi-clock-check"
                  size="large"
                  color="starbucks-green"
                  class="mb-2"
                  variant="outlined"
                ></v-btn>
                <div class="text-caption">My Timesheet</div>
              </div>
            </Link>
            <Link :href="route('self-service.my-profile.index')">
              <div class="text-center">
                <v-btn
                  icon="mdi-account-cog"
                  size="large"
                  color="starbucks-green"
                  class="mb-2"
                  variant="outlined"
                ></v-btn>
                <div class="text-caption">My Profile</div>
              </div>
            </Link>
          </div>
        </v-card>
      </v-col>

      <!-- Time at Work Card -->
      <v-col cols="12" lg="6" md="12">
        <v-card class="pa-4" rounded="xl" elevation="4">
          <div class="d-flex align-center mb-3">
            <v-icon class="mr-2" color="starbucks-green">mdi-clock</v-icon>
            <h3 class="text-h6 font-weight-medium">Time at Work</h3>
          </div>

          <div class="d-flex align-center mb-4">
            <v-avatar size="40" class="mr-3" color="starbucks-green">
              <v-icon color="white" size="20">mdi-account</v-icon>
            </v-avatar>
            <div>
              <!-- Time in/Time Out Actions -->
              <div class="text-subtitle-2 font-weight-medium">Punched Out</div>
              <div class="text-caption text-grey-600">
                Punched Out: Jun 3rd at 03:50 PM (GMT 8)
              </div>
            </div>
          </div>

          <div class="bg-grey-lighten-3 rounded-pill pa-3 mb-3">
            <div class="d-flex align-center justify-space-between">
              <div class="d-flex align-center">
                <!-- Total Time Rendered For Today -->
                <span class="text-h5 font-weight-bold mr-2">0h 0m</span>
                <span class="text-subtitle-2 text-grey-600">Today</span>
              </div>
              <v-icon color="black" size="20" class="mr-5"
                >mdi-timer-sand</v-icon
              >
            </div>
          </div>

          <div class="mb-3">
            <!-- Active Week -->
            <div class="text-subtitle-2 mb-2">This Week (Aug 04 - Aug 10)</div>
            <div class="d-flex align-center">
              <!-- Total Time Rendered For This Week -->
              <span class="text-h6 font-weight-bold mr-2">0h 0m</span>
            </div>
          </div>

          <!-- Weekly Progress Bar -->
          <div class="d-flex justify-space-between">
            <div
              v-for="day in ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun']"
              :key="day"
              class="text-center"
            >
              <div class="text-caption mb-1">{{ day }}</div>
              <div
                class="bg-grey-lighten-2 rounded"
                style="width: 20px; height: 40px"
              ></div>
            </div>
          </div>
        </v-card>
      </v-col>

      <!-- My Actions Card -->
      <!-- Actions done in HRMS like filling of leave application or checking dtr? -->
      <!-- <v-col cols="12" sm="12" md="6">
        <v-card class="pa-4" rounded="xl" elevation="4">
          <div class="d-flex align-center mb-3">
            <v-icon class="mr-2" color="primary">mdi-format-list-bulleted</v-icon>
            <h3 class="text-h6 font-weight-medium">My Actions</h3>
          </div>

          <div class="text-center py-8">
            <v-icon size="80" color="grey-lighten-1" class="mb-3">mdi-clipboard-text</v-icon>
            <div class="text-subtitle-1 text-grey-600">No Pending Actions to Perform</div>
          </div>
        </v-card>
      </v-col> -->

      <!-- Employees on Leave Today Card -->
      <v-col cols="12" lg="3" md="12">
        <v-card class="pa-4" rounded="xl" elevation="4">
          <div class="d-flex align-center mb-3">
            <v-icon class="mr-2" color="primary">mdi-briefcase</v-icon>
            <h3 class="text-h6 font-weight-medium">Employees on Leave Today</h3>
          </div>

          <div class="text-center py-8">
            <v-icon size="80" color="grey-lighten-1" class="mb-3"
              >mdi-clipboard-text</v-icon
            >
            <div class="text-subtitle-1 text-grey-600">
              No Employees are on Leave Today
            </div>
          </div>
        </v-card>
      </v-col>
    </v-row>
  </v-container>
</template>

<script>
import SidebarLayout from "@/layouts/SidebarLayout.vue";

export default {
  layout: SidebarLayout,
  components: {},
  data() {
    return {
      flash_status: null,
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
.v-card {
  height: 100%;
  min-height: 300px;
}
</style>
