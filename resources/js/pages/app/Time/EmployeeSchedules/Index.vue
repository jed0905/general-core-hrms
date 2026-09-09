<template>
  <SidebarLayout>
    <Head title="Employee Work Schedules" />

    <v-container fluid class="pa-6">
      <!-- Top Action Bar -->
      <div
        class="d-flex align-center justify-space-between flex-wrap ga-3 mb-6"
      >
        <div>
          <h1 class="text-h5 font-weight-bold text-high-emphasis">
            Employee Work Schedules
          </h1>
          <p class="text-body-2 text-medium-emphasis">
            Assign and manage weekly work schedule patterns for employees across
            departments.
          </p>
        </div>

        <v-btn
          v-if="can('employee_work_schedule.assign')"
          color="primary"
          prepend-icon="mdi-account-clock-outline"
          elevation="0"
          @click="openAssignModal"
        >
          Assign Schedule
        </v-btn>
      </div>

      <!-- Filters Header -->
      <v-card variant="outlined" class="rounded-lg mb-6 bg-surface">
        <v-card-text class="pa-4">
          <v-row density="compact" align="center">
            <v-col cols="12" sm="6" md="3">
              <v-text-field
                v-model="search"
                density="compact"
                variant="outlined"
                placeholder="Search employee or ID..."
                prepend-inner-icon="mdi-magnify"
                hide-details
                clearable
                @keyup.enter="applyFilters"
                @click:clear="clearSearch"
              />
            </v-col>

            <v-col cols="12" sm="6" md="3">
              <v-select
                v-model="departmentFilter"
                density="compact"
                variant="outlined"
                label="Department"
                :items="departments"
                item-title="name"
                item-value="id"
                hide-details
                clearable
                @update:model-value="applyFilters"
              />
            </v-col>

            <v-col cols="12" sm="6" md="3">
              <v-select
                v-model="workScheduleFilter"
                density="compact"
                variant="outlined"
                label="Work Schedule"
                :items="workSchedules"
                item-title="name"
                item-value="id"
                hide-details
                clearable
                @update:model-value="applyFilters"
              />
            </v-col>

            <v-col cols="12" sm="4" md="2">
              <v-select
                v-model="primaryFilter"
                density="compact"
                variant="outlined"
                label="Type"
                :items="primaryOptions"
                item-title="title"
                item-value="value"
                hide-details
                clearable
                @update:model-value="applyFilters"
              />
            </v-col>

            <v-col cols="12" sm="2" md="1">
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

      <!-- Employee Schedules Table -->
      <v-card variant="outlined" class="rounded-lg">
        <v-table hover class="bg-transparent">
          <thead>
            <tr>
              <th class="text-left font-weight-bold">Employee</th>
              <th class="text-left font-weight-bold">Department</th>
              <th class="text-left font-weight-bold">Assigned Schedule</th>
              <th class="text-left font-weight-bold">Effective Period</th>
              <th class="text-left font-weight-bold">Type</th>
              <th class="text-end font-weight-bold">Actions</th>
            </tr>
          </thead>

          <tbody>
            <tr v-if="!schedules.data || schedules.data.length === 0">
              <td colspan="6" class="text-center py-8 text-medium-emphasis">
                No employee schedule assignments found.
              </td>
            </tr>

            <tr v-for="item in schedules.data" :key="item.id">
              <td>
                <div class="font-weight-medium text-high-emphasis">
                  {{ item.employee?.emp_first_name }} {{ item.employee?.emp_last_name }}
                </div>
                <div class="text-caption text-medium-emphasis">
                  ID: {{ item.employee?.employee_number || "N/A" }}
                </div>
              </td>

              <td>
                <span class="text-body-2">
                  {{ item.employee?.department?.name || "Unassigned" }}
                </span>
              </td>

              <td>
                <div class="font-weight-medium text-high-emphasis">
                  {{ item.work_schedule?.name || "N/A" }}
                </div>
                <v-chip
                  v-if="item.work_schedule?.code"
                  size="x-small"
                  variant="tonal"
                  class="mt-1"
                >
                  {{ item.work_schedule.code }}
                </v-chip>
              </td>

              <td>
                <div class="text-body-2 font-weight-medium">
                  {{ formatDate(item.effective_from) }}
                </div>
                <div class="text-caption text-medium-emphasis">
                  to
                  {{
                    item.effective_to
                      ? formatDate(item.effective_to)
                      : "Present (Ongoing)"
                  }}
                </div>
              </td>

              <td>
                <v-chip
                  size="small"
                  :color="item.is_primary ? 'primary' : 'grey'"
                  variant="tonal"
                  class="font-weight-medium"
                >
                  {{ item.is_primary ? "Primary" : "Secondary" }}
                </v-chip>
              </td>

              <td class="text-end">
                <div class="d-flex align-center justify-end ga-1">
                  <v-btn
                    v-if="can('employee_work_schedule.update')"
                    icon="mdi-pencil-outline"
                    variant="text"
                    size="small"
                    color="medium-emphasis"
                    @click="openEditModal(item)"
                  />

                  <v-btn
                    v-if="can('employee_work_schedule.remove')"
                    icon="mdi-delete-outline"
                    variant="text"
                    size="small"
                    color="error"
                    @click="confirmDelete(item)"
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
            Showing {{ schedules.from || 0 }} to {{ schedules.to || 0 }} of
            {{ schedules.total || 0 }} records
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

      <!-- Assign Schedule Modal -->
      <v-dialog v-model="assignModal" max-width="600" persistent>
        <v-card class="rounded-lg pa-2">
          <v-card-title class="text-h6 font-weight-bold">
            Assign Work Schedule
          </v-card-title>

          <v-card-text class="pa-4">
            <v-row density="compact">
              <v-col cols="12">
                <v-autocomplete
                  v-model="assignForm.employee_ids"
                  :items="formattedEmployees"
                  item-title="title"
                  item-value="id"
                  label="Select Employees *"
                  placeholder="Choose one or more employees"
                  variant="outlined"
                  density="compact"
                  multiple
                  chips
                  closable-chips
                  :error-messages="assignForm.errors.employee_ids"
                />
              </v-col>

              <v-col cols="12">
                <v-select
                  v-model="assignForm.work_schedule_id"
                  :items="workSchedules"
                  item-title="name"
                  item-value="id"
                  label="Work Schedule Pattern *"
                  placeholder="Select a schedule pattern"
                  variant="outlined"
                  density="compact"
                  :error-messages="assignForm.errors.work_schedule_id"
                />
              </v-col>

              <v-col cols="12" sm="6">
                <v-text-field
                  v-model="assignForm.effective_from"
                  type="date"
                  label="Effective From Date *"
                  variant="outlined"
                  density="compact"
                  :error-messages="assignForm.errors.effective_from"
                />
              </v-col>

              <v-col cols="12" sm="6">
                <v-text-field
                  v-model="assignForm.effective_to"
                  type="date"
                  label="Effective To Date (Optional)"
                  variant="outlined"
                  density="compact"
                  :error-messages="assignForm.errors.effective_to"
                />
              </v-col>

              <v-col cols="12">
                <v-switch
                  v-model="assignForm.is_primary"
                  color="primary"
                  label="Set as Primary Schedule"
                  density="compact"
                  hide-details
                />
              </v-col>

              <v-col cols="12">
                <v-textarea
                  v-model="assignForm.remarks"
                  label="Remarks"
                  placeholder="Optional notes or reasons for this schedule assignment..."
                  variant="outlined"
                  density="compact"
                  rows="2"
                  :error-messages="assignForm.errors.remarks"
                />
              </v-col>
            </v-row>
          </v-card-text>

          <v-card-actions class="justify-end ga-2 px-4 pb-3">
            <v-btn
              variant="outlined"
              color="medium-emphasis"
              :disabled="assignForm.processing"
              @click="assignModal = false"
            >
              Cancel
            </v-btn>

            <v-btn
              color="primary"
              elevation="0"
              :loading="assignForm.processing"
              @click="executeAssign"
            >
              Assign Schedule
            </v-btn>
          </v-card-actions>
        </v-card>
      </v-dialog>

      <!-- Edit Schedule Modal -->
      <v-dialog v-model="editModal" max-width="550" persistent>
        <v-card class="rounded-lg pa-2">
          <v-card-title class="text-h6 font-weight-bold">
            Edit Employee Schedule
          </v-card-title>

          <v-card-text class="pa-4">
            <v-row density="compact">
              <v-col cols="12">
                <div
                  class="text-subtitle-2 font-weight-bold text-high-emphasis"
                >
                  {{ selectedItem?.employee?.emp_first_name }}
                  {{ selectedItem?.employee?.emp_last_name }}
                </div>
                <div class="text-caption text-medium-emphasis mb-3">
                  Employee ID:
                  {{ selectedItem?.employee?.employee_number || "N/A" }}
                </div>
              </v-col>

              <v-col cols="12">
                <v-select
                  v-model="editForm.work_schedule_id"
                  :items="workSchedules"
                  item-title="name"
                  item-value="id"
                  label="Work Schedule Pattern *"
                  variant="outlined"
                  density="compact"
                  :error-messages="editForm.errors.work_schedule_id"
                />
              </v-col>

              <v-col cols="12" sm="6">
                <v-text-field
                  v-model="editForm.effective_from"
                  type="date"
                  label="Effective From Date *"
                  variant="outlined"
                  density="compact"
                  :error-messages="editForm.errors.effective_from"
                />
              </v-col>

              <v-col cols="12" sm="6">
                <v-text-field
                  v-model="editForm.effective_to"
                  type="date"
                  label="Effective To Date"
                  variant="outlined"
                  density="compact"
                  :error-messages="editForm.errors.effective_to"
                />
              </v-col>

              <v-col cols="12">
                <v-switch
                  v-model="editForm.is_primary"
                  color="primary"
                  label="Primary Schedule"
                  density="compact"
                  hide-details
                />
              </v-col>

              <v-col cols="12" class="mt-2">
                <v-textarea
                  v-model="editForm.remarks"
                  label="Remarks"
                  variant="outlined"
                  density="compact"
                  rows="2"
                  :error-messages="editForm.errors.remarks"
                />
              </v-col>
            </v-row>
          </v-card-text>

          <v-card-actions class="justify-end ga-2 px-4 pb-3">
            <v-btn
              variant="outlined"
              color="medium-emphasis"
              :disabled="editForm.processing"
              @click="editModal = false"
            >
              Cancel
            </v-btn>

            <v-btn
              color="primary"
              elevation="0"
              :loading="editForm.processing"
              @click="executeEdit"
            >
              Update Schedule
            </v-btn>
          </v-card-actions>
        </v-card>
      </v-dialog>

      <!-- Delete Confirmation Dialog -->
      <v-dialog v-model="deleteModal" max-width="450">
        <v-card class="rounded-lg pa-2">
          <v-card-title class="text-h6 font-weight-bold">
            Unassign Work Schedule?
          </v-card-title>

          <v-card-text class="text-body-2 text-medium-emphasis">
            Are you sure you want to remove this schedule assignment for
            <strong>
              {{ selectedItem?.employee?.emp_first_name }}
              {{ selectedItem?.employee?.emp_last_name }} </strong
            >? This action cannot be undone.
          </v-card-text>

          <v-card-actions class="justify-end ga-2">
            <v-btn
              variant="outlined"
              color="medium-emphasis"
              :disabled="submitting"
              @click="deleteModal = false"
            >
              Cancel
            </v-btn>

            <v-btn
              color="error"
              elevation="0"
              :loading="submitting"
              @click="executeDelete"
            >
              Unassign
            </v-btn>
          </v-card-actions>
        </v-card>
      </v-dialog>
    </v-container>
  </SidebarLayout>
</template>

<script>
import { Head, Link, router, useForm } from "@inertiajs/vue3";
import SidebarLayout from "@/Layouts/SidebarLayout.vue";

export default {
  name: "EmployeeScheduleIndex",

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
    workSchedules: {
      type: Array,
      default: () => [],
    },
    employees: {
      type: Array,
      default: () => [],
    },
    departments: {
      type: Array,
      default: () => [],
    },
  },

  data() {
    return {
      search: this.filters.search || "",
      departmentFilter: this.filters.department_id || null,
      workScheduleFilter: this.filters.work_schedule_id || null,
      primaryFilter:
        this.filters.is_primary !== undefined
          ? String(this.filters.is_primary)
          : null,

      currentPage: this.schedules.current_page || 1,

      assignModal: false,
      editModal: false,
      deleteModal: false,

      selectedItem: null,
      submitting: false,

      primaryOptions: [
        { title: "Primary Only", value: "true" },
        { title: "Secondary Only", value: "false" },
      ],

      assignForm: useForm({
        employee_ids: [],
        work_schedule_id: null,
        effective_from: new Date().toISOString().substring(0, 10),
        effective_to: "",
        is_primary: true,
        remarks: "",
      }),

      editForm: useForm({
        work_schedule_id: null,
        effective_from: "",
        effective_to: "",
        is_primary: true,
        remarks: "",
      }),
    };
  },

  computed: {
    formattedEmployees() {
      return (this.employees || []).map((emp) => ({
        ...emp,
        title: `${emp.emp_first_name} ${emp.emp_last_name} (${
          emp.employee_number || "No ID"
        })`,
      }));
    },
  },

  methods: {
    can(permission) {
      return this.$page.props.auth?.permissions?.includes(permission) ?? true;
    },

    formatDate(dateStr) {
      if (!dateStr) return "N/A";
      const date = new Date(dateStr);
      return date.toLocaleDateString("en-US", {
        year: "numeric",
        month: "short",
        day: "numeric",
      });
    },

    showToast(message, type = "success") {
      if (this.$toast) {
        this.$toast[type](message);
      } else if (this.$notify) {
        this.$notify({ type, text: message });
      }
    },

    applyFilters() {
      router.get(
        route("time.employee-schedules.index"),
        {
          search: this.search || undefined,
          department_id: this.departmentFilter || undefined,
          work_schedule_id: this.workScheduleFilter || undefined,
          is_primary: this.primaryFilter ?? undefined,
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
        route("time.employee-schedules.index"),
        {
          page,
          search: this.search || undefined,
          department_id: this.departmentFilter || undefined,
          work_schedule_id: this.workScheduleFilter || undefined,
          is_primary: this.primaryFilter ?? undefined,
        },
        { preserveState: true, replace: true }
      );
    },

    openAssignModal() {
      this.assignForm.reset();
      this.assignForm.clearErrors();
      this.assignForm.effective_from = new Date()
        .toISOString()
        .substring(0, 10);
      this.assignForm.is_primary = true;
      this.assignModal = true;
    },

    executeAssign() {
      this.assignForm.post(route("time.employee-schedules.store"), {
        onSuccess: () => {
          this.assignModal = false;
          this.assignForm.reset();
          this.showToast("Work schedule assigned successfully!", "success");
        },
        onError: () => {
          this.showToast(
            "Failed to assign schedule. Please check input fields.",
            "error"
          );
        },
      });
    },

    openEditModal(item) {
      this.selectedItem = item;
      this.editForm.clearErrors();
      this.editForm.work_schedule_id = item.work_schedule_id;
      this.editForm.effective_from = item.effective_from;
      this.editForm.effective_to = item.effective_to || "";
      this.editForm.is_primary = Boolean(item.is_primary);
      this.editForm.remarks = item.remarks || "";
      this.editModal = true;
    },

    executeEdit() {
      if (!this.selectedItem) return;

      this.editForm.put(
        route("time.employee-schedules.update", this.selectedItem.id),
        {
          onSuccess: () => {
            this.editModal = false;
            this.selectedItem = null;
            this.showToast(
              "Employee schedule updated successfully!",
              "success"
            );
          },
          onError: () => {
            this.showToast("Failed to update schedule. Check errors.", "error");
          },
        }
      );
    },

    confirmDelete(item) {
      this.selectedItem = item;
      this.deleteModal = true;
    },

    executeDelete() {
      if (!this.selectedItem) return;

      this.submitting = true;
      router.delete(
        route("time.employee-schedules.destroy", this.selectedItem.id),
        {
          onSuccess: () => {
            this.deleteModal = false;
            this.selectedItem = null;
            this.showToast(
              "Employee schedule unassigned successfully",
              "success"
            );
          },
          onError: () => {
            this.showToast("Failed to unassign schedule", "error");
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