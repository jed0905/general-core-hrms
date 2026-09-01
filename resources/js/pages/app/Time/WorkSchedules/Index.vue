<template>
  <SidebarLayout>
    <Head title="Work Schedules" />

    <v-container fluid class="pa-6">
      <!-- Top Action Bar -->
      <div class="d-flex align-center justify-space-between flex-wrap ga-3 mb-6">
        <div>
          <h1 class="text-h5 font-weight-bold text-high-emphasis">
            Work Schedules
          </h1>
          <p class="text-body-2 text-medium-emphasis">
            Manage weekly schedule patterns and assign shifts per day.
          </p>
        </div>

        <Link v-if="can('work_schedule.create')" :href="route('time.work-schedules.create')">
          <v-btn
            color="primary"
            prepend-icon="mdi-plus"
            elevation="0"
          >
            Create Schedule
          </v-btn>
        </Link>
      </div>

      <!-- Filters & Search -->
      <v-card variant="outlined" class="rounded-lg mb-6 bg-surface">
        <v-card-text class="pa-4">
          <v-row density="compact" align="center">
            <v-col cols="12" sm="6" md="4">
              <v-text-field
                v-model="search"
                density="compact"
                variant="outlined"
                placeholder="Search schedule name or code..."
                prepend-inner-icon="mdi-magnify"
                hide-details
                clearable
                @keyup.enter="applyFilters"
                @click:clear="clearSearch"
              />
            </v-col>

            <v-col cols="12" sm="4" md="3">
              <v-select
                v-model="statusFilter"
                density="compact"
                variant="outlined"
                label="Status"
                :items="statusOptions"
                item-title="title"
                item-value="value"
                hide-details
                clearable
                @update:model-value="applyFilters"
              />
            </v-col>

            <v-col cols="12" sm="2" md="2">
              <v-btn
                variant="outlined"
                color="medium-emphasis"
                block
                @click="applyFilters"
              >
                Filter
              </v-btn>
            </v-col>
          </v-row>
        </v-card-text>
      </v-card>

      <!-- Schedules Table Card -->
      <v-card variant="outlined" class="rounded-lg">
        <v-table hover class="bg-transparent">
          <thead>
            <tr>
              <th class="text-left font-weight-bold">Schedule Name</th>
              <th class="text-left font-weight-bold">Code</th>
              <th class="text-left font-weight-bold">Weekly Pattern (Sun-Sat)</th>
              <th class="text-left font-weight-bold">Status</th>
              <th class="text-end font-weight-bold">Actions</th>
            </tr>
          </thead>

          <tbody>
            <tr v-if="!schedules.data || schedules.data.length === 0">
              <td colspan="5" class="text-center py-8 text-medium-emphasis">
                No work schedules found.
              </td>
            </tr>

            <tr v-for="schedule in schedules.data" :key="schedule.id">
              <td>
                <div class="font-weight-medium text-high-emphasis">
                  {{ schedule.name }}
                </div>
                <div class="text-caption text-medium-emphasis text-truncate" style="max-width: 250px;">
                  {{ schedule.description || 'No description provided' }}
                </div>
              </td>

              <td>
                <v-chip size="small" variant="tonal" class="font-weight-bold">
                  {{ schedule.code || 'N/A' }}
                </v-chip>
              </td>

              <td>
                <div class="d-flex ga-1 align-center py-2">
                  <v-tooltip
                    v-for="(dayName, index) in weekDays"
                    :key="index"
                    location="top"
                  >
                    <template #activator="{ props }">
                      <v-avatar
                        v-bind="props"
                        size="24"
                        :color="getDayShift(schedule, index) ? 'primary' : 'surface-variant'"
                        :variant="getDayShift(schedule, index) ? 'flat' : 'tonal'"
                        class="text-caption font-weight-bold"
                      >
                        <span style="font-size: 10px;">{{ dayName }}</span>
                      </v-avatar>
                    </template>
                    <span>
                      {{ dayName }}: {{ getDayShift(schedule, index) ? getDayShift(schedule, index).name : 'Rest Day' }}
                    </span>
                  </v-tooltip>
                </div>
              </td>

              <td>
                <v-chip
                  size="small"
                  :color="schedule.status === 'active' ? 'success' : 'error'"
                  variant="tonal"
                  class="font-weight-medium text-capitalize"
                >
                  {{ schedule.status }}
                </v-chip>
              </td>

              <td class="text-end">
                <div class="d-flex align-center justify-end ga-1">
                  <Link
                    v-if="can('work_schedule.update')"
                    :href="route('time.work-schedules.edit', schedule.id)"
                  >
                    <v-btn
                      icon="mdi-pencil-outline"
                      variant="text"
                      size="small"
                      color="medium-emphasis"
                    />
                  </Link>

                  <v-btn
                    v-if="can('work_schedule.archive') && schedule.status === 'active'"
                    icon="mdi-archive-outline"
                    variant="text"
                    size="small"
                    color="error"
                    @click="confirmArchive(schedule)"
                  />
                </div>
              </td>
            </tr>
          </tbody>
        </v-table>

        <!-- Pagination -->
        <v-divider />
        <div class="d-flex align-center justify-space-between pa-4 flex-wrap ga-2">
          <div class="text-caption text-medium-emphasis">
            Showing {{ schedules.from || 0 }} to {{ schedules.to || 0 }} of {{ schedules.total || 0 }} records
          </div>

          <v-pagination
            v-if="schedules.last_page > 1"
            v-model="currentPage"
            :length="schedules.last_page"
            density="comfortable"
            total-visible="5"
            @update:model-value="changePage"
          />
        </div>
      </v-card>

      <!-- Archive Dialog -->
      <v-dialog v-model="archiveModal" max-width="450">
        <v-card class="rounded-lg pa-2">
          <v-card-title class="text-h6 font-weight-bold">
            Archive Work Schedule?
          </v-card-title>

          <v-card-text class="text-body-2 text-medium-emphasis">
            Are you sure you want to archive <strong>{{ selectedSchedule?.name }}</strong>?
          </v-card-text>

          <v-card-actions class="justify-end ga-2">
            <v-btn
              variant="outlined"
              color="medium-emphasis"
              :disabled="submitting"
              @click="archiveModal = false"
            >
              Cancel
            </v-btn>

            <v-btn
              color="error"
              elevation="0"
              :loading="submitting"
              @click="executeArchive"
            >
              Archive
            </v-btn>
          </v-card-actions>
        </v-card>
      </v-dialog>
    </v-container>
  </SidebarLayout>
</template>

<script>
import { Head, Link, router } from "@inertiajs/vue3";
import SidebarLayout from "@/Layouts/SidebarLayout.vue";

export default {
  name: "WorkScheduleIndex",

  components: {
    SidebarLayout,
    Head,
    Link,
  },

  props: {
    schedules: {
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
      search: this.filters.search || "",
      statusFilter: this.filters.status || null,
      currentPage: this.schedules.current_page || 1,
      archiveModal: false,
      selectedSchedule: null,
      submitting: false,

      weekDays: ["S", "M", "T", "W", "T", "F", "S"],

      statusOptions: [
        { title: "Active", value: "active" },
        { title: "Inactive", value: "inactive" },
      ],
    };
  },

  methods: {
    can(permission) {
      return this.$page.props.auth?.permissions?.includes(permission) ?? true;
    },

    showToast(message, type = "success") {
      if (this.$toast) {
        this.$toast[type](message);
      } else if (this.$notify) {
        this.$notify({ type, text: message });
      }
    },

    getDayShift(schedule, dayIndex) {
      if (!schedule.days) return null;
      const day = schedule.days.find(
        (d) => d.day_of_week === dayIndex && d.is_working_day
      );
      return day ? day.shift : null;
    },

    applyFilters() {
      router.get(
        route("time.work-schedules.index"),
        {
          search: this.search || undefined,
          status: this.statusFilter || undefined,
        },
        { preserveState: true, replace: true }
      );
    },

    clearSearch() {
      this.search = "";
      this.applyFilters();
    },

    changePage(page) {
      router.get(
        route("time.work-schedules.index"),
        {
          page,
          search: this.search || undefined,
          status: this.statusFilter || undefined,
        },
        { preserveState: true, replace: true }
      );
    },

    confirmArchive(schedule) {
      this.selectedSchedule = schedule;
      this.archiveModal = true;
    },

    executeArchive() {
      if (!this.selectedSchedule) return;

      this.submitting = true;
      router.delete(
        route("time.work-schedules.destroy", this.selectedSchedule.id),
        {
          onSuccess: () => {
            this.archiveModal = false;
            this.selectedSchedule = null;
            this.showToast("Work schedule archived successfully", "success");
          },
          onError: () => {
            this.showToast("Failed to archive schedule", "error");
          },
          onFinish: () => {
            this.submitting = false;
          },
        }
      );
    },
  },
};
</script>
