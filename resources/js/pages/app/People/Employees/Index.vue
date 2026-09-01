<script setup>
import { ref, watch } from "vue";
import { Head, Link, router } from "@inertiajs/vue3";
import SidebarLayout from "@/Layouts/SidebarLayout.vue";

const props = defineProps({
  employees: {
    type: Object,
    required: true,
  },
  filters: {
    type: Object,
    default: () => ({}),
  },
  options: {
    type: Object,
    default: () => ({}),
  },
});

// Local Filter States
const search = ref(props.filters?.search || "");
const departmentId = ref(
  props.filters?.department_id ? Number(props.filters.department_id) : null
);
const locationId = ref(
  props.filters?.location_id ? Number(props.filters.location_id) : null
);
const status = ref(props.filters?.status || null);

const statusOptions = [
  { title: "Active", value: "active" },
  { title: "Archived", value: "archived" },
  { title: "On Leave", value: "on_leave" },
  { title: "Terminated", value: "terminated" },
];

// Filter Dispatcher
let debounceTimer = null;
const triggerFilter = () => {
  clearTimeout(debounceTimer);
  debounceTimer = setTimeout(() => {
    router.get(
      route("people.employee.index"),
      {
        search: search.value || undefined,
        department_id: departmentId.value || undefined,
        location_id: locationId.value || undefined,
        status: status.value || undefined,
      },
      { preserveState: true, replace: true }
    );
  }, 300);
};

watch([search, departmentId, locationId, status], () => {
  triggerFilter();
});

const resetFilters = () => {
  search.value = "";
  departmentId.value = null;
  locationId.value = null;
  status.value = null;
};

const handlePageChange = (page) => {
  router.get(
    route("people.employee.index"),
    {
      ...props.filters,
      page,
    },
    { preserveState: true }
  );
};

// Formatters & Styling Helpers
const getStatusColor = (val) => {
  switch (val) {
    case "active":
      return "success";
    case "archived":
      return "grey";
    case "on_leave":
      return "warning";
    case "terminated":
      return "error";
    default:
      return "primary";
  }
};

const formatStatus = (val) => {
  if (!val) return "";
  return val.replace("_", " ").replace(/\b\w/g, (l) => l.toUpperCase());
};

const getInitials = (firstName, lastName) => {
  const first = firstName ? firstName.charAt(0) : "";
  const last = lastName ? lastName.charAt(0) : "";
  return `${first}${last}`.toUpperCase();
};

// Archive / Restore Modal State
const archiveDialog = ref(false);
const selectedEmployee = ref(null);

const openArchiveModal = (emp) => {
  selectedEmployee.value = emp;
  archiveDialog.value = true;
};

const submitArchiveToggle = () => {
  if (!selectedEmployee.value) return;
  const isArchived = selectedEmployee.value.status === "archived";
  const targetRoute = isArchived
    ? route("people.employee.restore", selectedEmployee.value.id)
    : route("people.employee.archive", selectedEmployee.value.id);

  router.patch(
    targetRoute,
    {},
    {
      preserveScroll: true,
      onSuccess: () => {
        archiveDialog.value = false;
        selectedEmployee.value = null;
      },
    }
  );
};
</script>

<template>
  <SidebarLayout>
    <Head title="Employee Directory" />

    <v-container fluid class="pa-6">
      <!-- Header Bar -->
      <div
        class="d-flex align-center justify-space-between flex-wrap ga-3 mb-6"
      >
        <div>
          <h1 class="text-h5 font-weight-bold text-high-emphasis">Employees</h1>
          <p class="text-body-2 text-medium-emphasis">
            Manage organization members, view profiles, and update status
            records.
          </p>
        </div>

        <div class="d-flex align-center ga-2">
          <v-btn
            variant="outlined"
            color="primary"
            prepend-icon="mdi-export-variant"
            elevation="0"
            size="small"
            :href="route('people.employee.export')"
          >
            Export
          </v-btn>

          <v-btn
            color="primary"
            prepend-icon="mdi-account-plus"
            elevation="0"
            size="small"
            @click="router.get(route('people.employee.create'))"
          >
            Add Employee
          </v-btn>
        </div>
      </div>

      <!-- Filters Toolbar -->
      <v-card variant="outlined" class="rounded-lg mb-6">
        <v-card-text class="pa-4">
          <v-row density="compact" align="center">
            <!-- Search -->
            <v-col cols="12" sm="6" md="3">
              <v-text-field
                v-model="search"
                placeholder="Search name, number or email..."
                prepend-inner-icon="mdi-magnify"
                variant="outlined"
                density="compact"
                hide-details
                clearable
                @click:clear="search = ''"
              />
            </v-col>

            <!-- Department Filter -->
            <v-col cols="12" sm="6" md="3">
              <v-select
                v-model="departmentId"
                :items="options.departments || []"
                item-title="name"
                item-value="id"
                label="Department"
                variant="outlined"
                density="compact"
                hide-details
                clearable
              />
            </v-col>

            <!-- Location Filter -->
            <v-col cols="12" sm="6" md="3">
              <v-select
                v-model="locationId"
                :items="options.locations || []"
                item-title="name"
                item-value="id"
                label="Location"
                variant="outlined"
                density="compact"
                hide-details
                clearable
              />
            </v-col>

            <!-- Status Filter -->
            <v-col cols="12" sm="6" md="2">
              <v-select
                v-model="status"
                :items="statusOptions"
                item-title="title"
                item-value="value"
                label="Status"
                variant="outlined"
                density="compact"
                hide-details
                clearable
              />
            </v-col>

            <!-- Clear Action -->
            <v-col cols="12" sm="12" md="1" class="text-right">
              <v-btn
                variant="text"
                color="primary"
                size="small"
                class="px-2"
                :disabled="!search && !departmentId && !locationId && !status"
                @click="resetFilters"
              >
                Clear
              </v-btn>
            </v-col>
          </v-row>
        </v-card-text>
      </v-card>

      <!-- Employees Data Table Card -->
      <v-card variant="outlined" class="rounded-lg">
        <v-table class="employee-table">
          <thead>
            <tr>
              <th class="text-caption font-weight-bold">EMPLOYEE</th>
              <th class="text-caption font-weight-bold">JOB TITLE & DEPT</th>
              <th class="text-caption font-weight-bold">LOCATION</th>
              <th class="text-caption font-weight-bold">STATUS</th>
              <th class="text-caption font-weight-bold text-end">ACTIONS</th>
            </tr>
          </thead>
          <tbody>
            <template v-if="employees.data && employees.data.length > 0">
              <tr
                v-for="emp in employees.data"
                :key="emp.id"
                class="employee-row"
              >
                <!-- Employee Name & Avatar -->
                <td class="py-3">
                  <div class="d-flex align-center ga-3">
                    <v-avatar size="36" color="primary" variant="tonal">
                      <v-img
                        v-if="emp.photo"
                        :src="`/storage/${emp.photo}`"
                        alt="Employee Photo"
                      />
                      <span v-else class="text-caption font-weight-bold">
                        {{ getInitials(emp.emp_first_name, emp.emp_last_name) }}
                      </span>
                    </v-avatar>

                    <div class="text-truncate">
                      <Link
                        :href="route('people.employee.show', emp.id)"
                        class="text-body-2 font-weight-bold text-high-emphasis text-decoration-none employee-link"
                      >
                        {{ emp.emp_first_name }} {{ emp.emp_last_name }}
                      </Link>
                      <div class="text-caption text-medium-emphasis">
                        #{{ emp.employee_number }} •
                        {{ emp.work_email || "No email" }}
                      </div>
                    </div>
                  </div>
                </td>

                <!-- Job Title & Department -->
                <td>
                  <div
                    class="text-body-2 text-high-emphasis font-weight-medium"
                  >
                    {{ emp.job_title?.job_title || "—" }}
                  </div>
                  <div class="text-caption text-medium-emphasis">
                    {{ emp.department?.name || "Unassigned" }}
                  </div>
                </td>

                <!-- Location -->
                <td>
                  <div class="text-body-2 text-high-emphasis">
                    {{
                      emp.location
                        ? [emp.location.city, emp.location.province]
                            .filter(Boolean)
                            .join(", ") ||
                          emp.location.address ||
                          "—"
                        : "—"
                    }}
                  </div>
                </td>

                <!-- Status Chip -->
                <td>
                  <v-chip
                    :color="getStatusColor(emp.status)"
                    size="x-small"
                    variant="tonal"
                    class="font-weight-medium"
                  >
                    {{ formatStatus(emp.status) }}
                  </v-chip>
                </td>

                <!-- Actions Menu -->
                <td class="text-end">
                  <div class="d-flex align-center justify-end ga-1">
                    <!-- View Button -->
                    <v-btn
                      icon="mdi-eye-outline"
                      variant="text"
                      size="x-small"
                      color="medium-emphasis"
                      @click="router.get(route('people.employee.show', emp.id))"
                    />

                    <!-- Edit Button -->
                    <v-btn
                      icon="mdi-pencil-outline"
                      variant="text"
                      size="x-small"
                      color="medium-emphasis"
                      @click="router.get(route('people.employee.edit', emp.id))"
                    />

                    <v-menu location="bottom end">
                      <template #activator="{ props: menuProps }">
                        <v-btn
                          icon="mdi-dots-vertical"
                          variant="text"
                          size="x-small"
                          color="medium-emphasis"
                          v-bind="menuProps"
                        />
                      </template>
                      <v-list density="compact" nav class="pa-1">
                        <v-list-item
                          :prepend-icon="
                            emp.status === 'archived'
                              ? 'mdi-restore'
                              : 'mdi-archive-outline'
                          "
                          :title="
                            emp.status === 'archived' ? 'Restore' : 'Archive'
                          "
                          class="text-caption"
                          @click="openArchiveModal(emp)"
                        />
                      </v-list>
                    </v-menu>
                  </div>
                </td>
              </tr>
            </template>

            <template v-else>
              <tr>
                <td colspan="5" class="text-center py-8 text-medium-emphasis">
                  <v-icon
                    icon="mdi-account-off-outline"
                    size="40"
                    class="mb-2"
                  />
                  <div class="text-body-2">No employee records found.</div>
                </td>
              </tr>
            </template>
          </tbody>
        </v-table>

        <!-- Pagination Bar -->
        <v-card-actions
          v-if="employees.last_page > 1"
          class="py-3 px-4 border-t justify-space-between flex-wrap ga-2"
        >
          <div class="text-caption text-medium-emphasis">
            Showing {{ employees.from }} to {{ employees.to }} of
            {{ employees.total }} entries
          </div>

          <v-pagination
            :model-value="employees.current_page"
            :length="employees.last_page"
            density="compact"
            total-visible="5"
            active-color="primary"
            @update:model-value="handlePageChange"
          />
        </v-card-actions>
      </v-card>
    </v-container>

    <!-- Archive / Restore Confirmation Modal -->
    <v-dialog v-model="archiveDialog" max-width="400">
      <v-card rounded="lg">
        <v-card-title class="text-h6 font-weight-bold pa-5 border-b">
          {{
            selectedEmployee?.status === "archived"
              ? "Restore Employee"
              : "Archive Employee"
          }}
        </v-card-title>

        <v-card-text class="pa-5 text-body-2">
          Are you sure you want to
          {{ selectedEmployee?.status === "archived" ? "restore" : "archive" }}
          <strong
            >{{ selectedEmployee?.emp_first_name }}
            {{ selectedEmployee?.emp_last_name }}</strong
          >?
        </v-card-text>

        <v-divider />

        <v-card-actions class="pa-4 justify-end">
          <v-btn variant="text" @click="archiveDialog = false">Cancel</v-btn>
          <v-btn
            :color="
              selectedEmployee?.status === 'archived' ? 'success' : 'error'
            "
            elevation="0"
            @click="submitArchiveToggle"
          >
            Confirm
          </v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>
  </SidebarLayout>
</template>

<style scoped>
.employee-table :deep(th) {
  height: 44px !important;
  background-color: rgba(var(--v-theme-on-surface), 0.02);
}

.employee-row:hover {
  background-color: rgba(var(--v-theme-on-surface), 0.02);
}

.employee-link:hover {
  color: rgb(var(--v-theme-primary)) !important;
}
</style>
