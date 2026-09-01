<template>
  <SidebarLayout>
    <Head title="Work Shifts" />

    <v-container fluid class="pa-6">
      <!-- Top Action Bar -->
      <div
        class="d-flex align-center justify-space-between flex-wrap ga-3 mb-6"
      >
        <div>
          <h1 class="text-h5 font-weight-bold text-high-emphasis">
            Work Shifts
          </h1>
          <p class="text-body-2 text-medium-emphasis">
            Manage work shift templates, working hours, and time slot schedules.
          </p>
        </div>

        <Link v-if="can('shift.create')" :href="route('time.shifts.create')">
          <v-btn color="primary" prepend-icon="mdi-plus" elevation="0">
            Create Shift
          </v-btn>
        </Link>
      </div>

      <!-- Filters & Search Header -->
      <v-card variant="outlined" class="rounded-lg mb-6 bg-surface">
        <v-card-text class="pa-4">
          <v-row density="compact" align="center">
            <v-col cols="12" sm="6" md="4">
              <v-text-field
                v-model="search"
                density="compact"
                variant="outlined"
                placeholder="Search by name or code..."
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

      <!-- Shift Table Card -->
      <v-card variant="outlined" class="rounded-lg">
        <v-table hover class="bg-transparent">
          <thead>
            <tr>
              <th class="text-left font-weight-bold">Shift Name</th>
              <th class="text-left font-weight-bold">Code</th>
              <th class="text-left font-weight-bold">Working Hours</th>
              <th class="text-left font-weight-bold">Req. Hours</th>
              <th class="text-left font-weight-bold">Attributes</th>
              <th class="text-left font-weight-bold">Status</th>
              <th class="text-end font-weight-bold">Actions</th>
            </tr>
          </thead>

          <tbody>
            <tr v-if="!shifts.data || shifts.data.length === 0">
              <td colspan="7" class="text-center py-8 text-medium-emphasis">
                No work shifts found.
              </td>
            </tr>

            <tr v-for="shift in shifts.data" :key="shift.id">
              <td>
                <div class="font-weight-medium text-high-emphasis">
                  {{ shift.name }}
                </div>
                <div
                  class="text-caption text-medium-emphasis text-truncate"
                  style="max-width: 220px"
                >
                  {{ shift.description || "No description provided" }}
                </div>
              </td>

              <td>
                <v-chip size="small" variant="tonal" class="font-weight-bold">
                  {{ shift.code }}
                </v-chip>
              </td>

              <td>
                <div class="text-body-2 font-weight-medium">
                  {{ formatTime(shift.start_time) }} -
                  {{ formatTime(shift.end_time) }}
                </div>
                <div
                  v-if="shift.break_start && shift.break_end"
                  class="text-caption text-medium-emphasis"
                >
                  Break: {{ formatTime(shift.break_start) }} -
                  {{ formatTime(shift.break_end) }}
                </div>
              </td>

              <td>
                <span class="text-body-2 font-weight-medium">
                  {{
                    shift.required_hours ? `${shift.required_hours} hrs` : "N/A"
                  }}
                </span>
              </td>

              <td>
                <div class="d-flex ga-1 flex-wrap">
                  <v-chip
                    v-if="shift.is_overnight"
                    size="x-small"
                    color="indigo"
                    variant="flat"
                  >
                    Overnight
                  </v-chip>
                  <v-chip
                    v-if="shift.is_flexible"
                    size="x-small"
                    color="teal"
                    variant="flat"
                  >
                    Flexible
                  </v-chip>
                  <span
                    v-if="!shift.is_overnight && !shift.is_flexible"
                    class="text-caption text-medium-emphasis"
                  >
                    Standard
                  </span>
                </div>
              </td>

              <td>
                <v-chip
                  size="small"
                  :color="shift.is_active ? 'success' : 'error'"
                  variant="tonal"
                  class="font-weight-medium"
                >
                  {{ shift.is_active ? "Active" : "Inactive" }}
                </v-chip>
              </td>

              <td class="text-end">
                <div class="d-flex align-center justify-end ga-1">
                  <Link
                    v-if="can('shift.update')"
                    :href="route('time.shifts.edit', shift.id)"
                  >
                    <v-btn
                      icon="mdi-pencil-outline"
                      variant="text"
                      size="small"
                      color="medium-emphasis"
                    />
                  </Link>

                  <v-btn
                    v-if="can('shift.archive') && shift.is_active"
                    icon="mdi-archive-outline"
                    variant="text"
                    size="small"
                    color="error"
                    @click="confirmArchive(shift)"
                  />
                </div>
              </td>
            </tr>
          </tbody>
        </v-table>

        <!-- Pagination Footer -->
        <v-divider />
        <div
          class="d-flex align-center justify-space-between pa-4 flex-wrap ga-2"
        >
          <div class="text-caption text-medium-emphasis">
            Showing {{ shifts.from || 0 }} to {{ shifts.to || 0 }} of
            {{ shifts.total || 0 }} records
          </div>

          <v-pagination
            v-if="shifts.last_page > 1"
            v-model="currentPage"
            :length="shifts.last_page"
            density="comfortable"
            total-visible="5"
            @update:model-value="changePage"
          />
        </div>
      </v-card>

      <!-- Archive Confirmation Dialog -->
      <v-dialog v-model="archiveModal" max-width="450">
        <v-card class="rounded-lg pa-2">
          <v-card-title class="text-h6 font-weight-bold">
            Archive Work Shift?
          </v-card-title>

          <v-card-text class="text-body-2 text-medium-emphasis">
            Are you sure you want to archive
            <strong>{{ selectedShift?.name }}</strong
            >? This shift will be marked inactive and cannot be assigned to new
            schedules.
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
  name: "ShiftIndex",

  components: {
    SidebarLayout,
    Head,
    Link,
  },

  props: {
    shifts: {
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
      statusFilter:
        this.filters.is_active !== undefined
          ? String(this.filters.is_active)
          : null,
      currentPage: this.shifts.current_page || 1,
      archiveModal: false,
      selectedShift: null,
      submitting: false,

      statusOptions: [
        { title: "Active Only", value: "1" },
        { title: "Inactive Only", value: "0" },
      ],
    };
  },

  methods: {
    can(permission) {
      return this.$page.props.auth?.permissions?.includes(permission) ?? true;
    },

    formatTime(timeStr) {
      if (!timeStr) return "--:--";
      return timeStr.substring(0, 5);
    },

    applyFilters() {
      router.get(
        route("time.shifts.index"),
        {
          search: this.search || undefined,
          is_active: this.statusFilter ?? undefined,
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
        route("time.shifts.index"),
        {
          page,
          search: this.search || undefined,
          is_active: this.statusFilter ?? undefined,
        },
        { preserveState: true, replace: true }
      );
    },

    confirmArchive(shift) {
      this.selectedShift = shift;
      this.archiveModal = true;
    },

    executeArchive() {
      if (!this.selectedShift) return;

      this.submitting = true;
      router.delete(route("time.shifts.destroy", this.selectedShift.id), {
        onSuccess: () => {
          this.archiveModal = false;
          this.selectedShift = null;
          this.showToast("Work shift archived successfully", "success");
        },
        onError: () => {
          this.showToast("Failed to archive work shift", "error");
        },
        onFinish: () => {
          this.submitting = false;
        },
      });
    },
  },
};
</script>
