<template>
  <SidebarLayout>
    <Head title="Employee Movements" />

    <v-container fluid class="pa-4 pa-sm-6">
      <div class="d-flex flex-column flex-sm-row align-sm-center justify-space-between ga-4 mb-6">
        <div>
          <h1 class="text-h5 font-weight-bold">Employee Movements</h1>
          <p class="text-body-2 text-medium-emphasis">
            Promotions, transfers, status changes and separations, with the
            employee's assignment before and after each one.
          </p>
        </div>
        <div class="d-flex ga-2">
          <v-btn
            v-if="can('employee_movement.export')"
            :href="exportUrl"
            variant="outlined"
            color="primary"
            prepend-icon="mdi-microsoft-excel"
          >
            Export Excel
          </v-btn>
          <v-btn
            v-if="can('employee_movement.create')"
            color="primary"
            elevation="0"
            prepend-icon="mdi-plus"
            @click="go('people.employee-movements.create')"
          >
            Record Movement
          </v-btn>
        </div>
      </div>

      <!-- Filters -->
      <v-card variant="outlined" class="rounded-lg mb-4 pa-3">
        <v-row density="compact">
          <v-col cols="12" sm="6" md="3">
            <v-text-field
              v-model="form.search"
              label="Search employee"
              prepend-inner-icon="mdi-magnify"
              variant="outlined"
              density="compact"
              clearable
              hide-details
              @keyup.enter="applyFilters"
            />
          </v-col>
          <v-col cols="12" sm="6" md="3">
            <v-select
              v-model="form.movement_type_id"
              :items="typeOptions"
              label="Movement type"
              variant="outlined"
              density="compact"
              hide-details
            />
          </v-col>
          <v-col cols="12" sm="6" md="2">
            <v-select
              v-model="form.status"
              :items="statusOptions"
              label="Status"
              variant="outlined"
              density="compact"
              hide-details
            />
          </v-col>
          <v-col cols="12" sm="6" md="2">
            <v-select
              v-model="form.department_id"
              :items="departmentOptions"
              label="Department"
              variant="outlined"
              density="compact"
              hide-details
            />
          </v-col>
          <v-col cols="12" sm="6" md="2">
            <v-select
              v-model="form.employment_status_id"
              :items="employmentStatusOptions"
              label="New employment status"
              variant="outlined"
              density="compact"
              hide-details
            />
          </v-col>
          <v-col cols="6" sm="3" md="2">
            <v-text-field
              v-model="form.effective_from"
              type="date"
              label="Effective from"
              variant="outlined"
              density="compact"
              hide-details
            />
          </v-col>
          <v-col cols="6" sm="3" md="2">
            <v-text-field
              v-model="form.effective_to"
              type="date"
              label="Effective to"
              variant="outlined"
              density="compact"
              hide-details
            />
          </v-col>
          <v-col cols="12" md="8" class="d-flex align-center justify-end ga-2">
            <v-btn variant="text" @click="resetFilters">Reset</v-btn>
            <v-btn color="primary" variant="flat" @click="applyFilters">Apply</v-btn>
          </v-col>
        </v-row>
      </v-card>

      <v-card variant="outlined" class="rounded-lg">
        <v-table hover>
          <thead>
            <tr>
              <th class="font-weight-bold">Effective</th>
              <th class="font-weight-bold">Employee</th>
              <th class="font-weight-bold">Movement</th>
              <th class="font-weight-bold">Change</th>
              <th class="font-weight-bold">Status</th>
              <th class="text-end font-weight-bold">Details</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="!movements.data.length">
              <td colspan="6" class="text-center py-6 text-medium-emphasis">
                No employee movements found.
              </td>
            </tr>
            <tr v-for="m in movements.data" :key="m.id">
              <td class="text-no-wrap">{{ formatDate(m.effective_date) }}</td>
              <td>
                <div class="font-weight-medium">{{ employeeName(m.employee) }}</div>
                <div class="text-caption text-medium-emphasis">{{ m.employee?.employee_number }}</div>
              </td>
              <td>{{ m.type?.name }}</td>
              <td>
                <div v-for="c in changeSummary(m)" :key="c.label" class="text-body-2">
                  <span class="text-medium-emphasis">{{ c.label }}:</span>
                  {{ c.from || "—" }} → <strong>{{ c.to || "—" }}</strong>
                </div>
                <span v-if="!changeSummary(m).length" class="text-medium-emphasis">No assignment change</span>
              </td>
              <td>
                <v-chip :color="movementStatus(m.status).color" size="small" variant="tonal">
                  {{ movementStatus(m.status).label }}
                </v-chip>
              </td>
              <td class="text-end">
                <v-btn
                  icon="mdi-eye-outline"
                  variant="text"
                  size="small"
                  color="info"
                  @click="go('people.employee-movements.show', { employeeMovement: m.id })"
                />
              </td>
            </tr>
          </tbody>
        </v-table>
      </v-card>

      <div class="mt-4">
        <Pagination :meta="movements" />
      </div>
    </v-container>
  </SidebarLayout>
</template>

<script>
import { Head, router } from "@inertiajs/vue3";
import SidebarLayout from "@/layouts/SidebarLayout.vue";
import Pagination from "@/components/Pagination.vue";
import permissions from "@/mixins/permissions";
import { changeSummary, employeeName, formatDate, movementStatus } from "@/utils/employeeMovement";

const EMPTY_FILTERS = {
  search: "",
  movement_type_id: null,
  status: null,
  department_id: null,
  employment_status_id: null,
  effective_from: "",
  effective_to: "",
};

export default {
  name: "EmployeeMovementsIndex",
  components: { SidebarLayout, Head, Pagination },
  mixins: [permissions],
  props: {
    movements: { type: Object, required: true },
    filters: { type: Object, default: () => ({}) },
    types: { type: Array, default: () => [] },
    departments: { type: Array, default: () => [] },
    employmentStatuses: { type: Array, default: () => [] },
  },
  data() {
    const f = this.filters || {};
    return {
      form: {
        ...EMPTY_FILTERS,
        ...f,
        movement_type_id: f.movement_type_id ? Number(f.movement_type_id) : null,
        department_id: f.department_id ? Number(f.department_id) : null,
        employment_status_id: f.employment_status_id ? Number(f.employment_status_id) : null,
      },
      statusOptions: [
        { title: "All", value: null },
        { title: "Effective", value: "implemented" },
        { title: "Scheduled", value: "approved" },
        { title: "Cancelled", value: "cancelled" },
      ],
    };
  },
  computed: {
    typeOptions() {
      return [{ title: "All", value: null }, ...this.types.map((t) => ({ title: t.is_active ? t.name : `${t.name} (archived)`, value: t.id }))];
    },
    departmentOptions() {
      return [{ title: "All", value: null }, ...this.departments.map((d) => ({ title: d.name, value: d.id }))];
    },
    employmentStatusOptions() {
      return [{ title: "All", value: null }, ...this.employmentStatuses.map((s) => ({ title: s.name, value: s.id }))];
    },
    activeFilters() {
      return Object.fromEntries(Object.entries(this.form).filter(([, v]) => v !== null && v !== ""));
    },
    exportUrl() {
      return route("people.employee-movements.export", this.activeFilters);
    },
  },
  methods: {
    changeSummary,
    employeeName,
    formatDate,
    movementStatus,
    go(name, params = {}) {
      router.visit(route(name, params));
    },
    applyFilters() {
      router.get(route("people.employee-movements.index"), this.activeFilters, {
        preserveState: true,
        preserveScroll: true,
      });
    },
    resetFilters() {
      this.form = { ...EMPTY_FILTERS };
      this.applyFilters();
    },
  },
};
</script>
