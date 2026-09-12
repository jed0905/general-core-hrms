<template>
  <SidebarLayout>
    <Head title="Leave Balances" />

    <v-container fluid class="pa-6">
      <div class="d-flex align-center justify-space-between mb-6">
        <div>
          <h1 class="text-h5 font-weight-bold">Leave Balances</h1>
          <p class="text-body-2 text-medium-emphasis">
            View and manage employee leave balance allocations and adjustments.
          </p>
        </div>

        <div class="d-flex ga-2">
          <v-btn
            color="secondary"
            variant="outlined"
            prepend-icon="mdi-plus-minus-box"
            elevation="0"
            @click="openAdjustModal()"
          >
            Quick Adjust
          </v-btn>
          <v-btn
            color="primary"
            prepend-icon="mdi-plus"
            elevation="0"
            @click="openModal()"
          >
            Initialize Balance
          </v-btn>
        </div>
      </div>

      <!-- Filters -->
      <v-card variant="outlined" class="rounded-lg mb-4 pa-4">
        <v-row density="compact">
          <v-col cols="12" sm="4">
            <v-text-field
              v-model="filterForm.search"
              label="Search Employee"
              prepend-inner-icon="mdi-magnify"
              variant="outlined"
              density="compact"
              clearable
              hide-details
              @keyup.enter="applyFilters"
              @click:clear="applyFilters"
            />
          </v-col>
          <v-col cols="12" sm="4">
            <v-select
              v-model="filterForm.leave_type_id"
              label="Filter by Leave Type"
              :items="leaveTypes"
              item-title="name"
              item-value="id"
              variant="outlined"
              density="compact"
              clearable
              hide-details
              @update:model-value="applyFilters"
            />
          </v-col>
          <v-col cols="12" sm="4">
            <v-text-field
              v-model="filterForm.year"
              label="Filter by Year"
              type="number"
              variant="outlined"
              density="compact"
              clearable
              hide-details
              @keyup.enter="applyFilters"
              @click:clear="applyFilters"
            />
          </v-col>
        </v-row>
      </v-card>

      <!-- Table -->
      <v-card variant="outlined" class="rounded-lg">
        <v-table hover>
          <thead>
            <tr>
              <th class="font-weight-bold">Employee</th>
              <th class="font-weight-bold">Leave Type</th>
              <th class="font-weight-bold text-center">Allocated</th>
              <th class="font-weight-bold text-center">Used</th>
              <th class="font-weight-bold text-center">Pending</th>
              <th class="font-weight-bold text-center">Available</th>
              <th class="font-weight-bold">As Of Date</th>
              <th class="text-end font-weight-bold">Actions</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="!balances.data || !balances.data.length">
              <td colspan="8" class="text-center py-6 text-medium-emphasis">
                No leave balances found.
              </td>
            </tr>

            <tr v-for="b in balances.data" :key="b.id">
              <td class="font-weight-medium">
                <div>
                  {{ b.employee?.first_name }} {{ b.employee?.last_name }}
                </div>
                <div class="text-caption text-medium-emphasis">
                  {{ b.employee?.employee_number }}
                </div>
              </td>
              <td>
                <div class="font-weight-medium">{{ b.leave_type?.name }}</div>
                <v-chip size="x-small" variant="outlined">
                  {{ b.leave_type?.code }}
                </v-chip>
              </td>
              <td class="text-center font-weight-bold">
                {{ b.balance }}
              </td>
              <td class="text-center text-error font-weight-medium">
                {{ b.used }}
              </td>
              <td class="text-center text-warning font-weight-medium">
                {{ b.pending }}
              </td>
              <td class="text-center">
                <v-chip
                  size="small"
                  :color="
                    b.balance - b.used - b.pending >= 0 ? 'success' : 'error'
                  "
                  variant="tonal"
                  class="font-weight-bold"
                >
                  {{ (b.balance - b.used - b.pending).toFixed(1) }}
                </v-chip>
              </td>
              <td>{{ b.as_of_date }}</td>
              <td class="text-end">
                <v-btn
                  icon="mdi-plus-minus"
                  variant="text"
                  size="small"
                  color="secondary"
                  title="Quick Adjust"
                  @click="openAdjustModal(b)"
                />
                <v-btn
                  icon="mdi-pencil-outline"
                  variant="text"
                  size="small"
                  title="Edit Record"
                  @click="openModal(b)"
                />
              </td>
            </tr>
          </tbody>
        </v-table>
      </v-card>

      <!-- Initialize / Edit Dialog -->
      <v-dialog v-model="dialog" max-width="500" persistent>
        <v-card class="pa-2 rounded-lg">
          <v-card-title class="font-weight-bold">
            {{ isEditing ? "Edit Leave Balance" : "Initialize Leave Balance" }}
          </v-card-title>
          <v-card-text>
            <v-row density="compact">
              <v-col cols="12" v-if="!isEditing">
                <v-select
                  v-model="form.employee_id"
                  label="Employee *"
                  :items="employees"
                  :item-title="
                    (e) =>
                      `${e.emp_first_name} ${e.emp_last_name} (${e.employee_number})`
                  "
                  item-value="id"
                  variant="outlined"
                  density="compact"
                  :error-messages="form.errors.employee_id"
                />
              </v-col>

              <v-col cols="12" v-if="!isEditing">
                <v-select
                  v-model="form.leave_type_id"
                  label="Leave Type *"
                  :items="leaveTypes"
                  item-title="name"
                  item-value="id"
                  variant="outlined"
                  density="compact"
                  :error-messages="form.errors.leave_type_id"
                />
              </v-col>

              <v-col cols="12">
                <v-text-field
                  v-model="form.balance"
                  type="number"
                  step="0.5"
                  label="Allocated Balance *"
                  variant="outlined"
                  density="compact"
                  :error-messages="form.errors.balance"
                />
              </v-col>

              <v-col cols="6">
                <v-text-field
                  v-model="form.used"
                  type="number"
                  step="0.5"
                  label="Used Days"
                  variant="outlined"
                  density="compact"
                  :error-messages="form.errors.used"
                />
              </v-col>

              <v-col cols="6">
                <v-text-field
                  v-model="form.pending"
                  type="number"
                  step="0.5"
                  label="Pending Days"
                  variant="outlined"
                  density="compact"
                  :error-messages="form.errors.pending"
                />
              </v-col>

              <v-col cols="12">
                <v-text-field
                  v-model="form.as_of_date"
                  type="date"
                  label="As Of Date *"
                  variant="outlined"
                  density="compact"
                  :error-messages="form.errors.as_of_date"
                />
              </v-col>
            </v-row>
          </v-card-text>
          <v-card-actions class="justify-end ga-2">
            <v-btn variant="outlined" @click="dialog = false">Cancel</v-btn>
            <v-btn color="primary" :loading="form.processing" @click="submit">
              Save
            </v-btn>
          </v-card-actions>
        </v-card>
      </v-dialog>

      <!-- Quick Adjust Dialog -->
      <v-dialog v-model="adjustDialog" max-width="480" persistent>
        <v-card class="pa-2 rounded-lg">
          <v-card-title class="font-weight-bold">
            Adjust Leave Balance
          </v-card-title>
          <v-card-text>
            <v-row density="compact">
              <v-col cols="12">
                <v-select
                  v-model="adjustForm.leave_balance_id"
                  label="Select Record *"
                  :items="balances.data || []"
                  :item-title="
                    (b) =>
                      `${b.employee?.emp_first_name} ${b.employee?.emp_last_name} - ${b.leave_type?.name} (Bal: ${b.balance})`
                  "
                  item-value="id"
                  variant="outlined"
                  density="compact"
                  :error-messages="adjustForm.errors.leave_balance_id"
                />
              </v-col>

              <v-col cols="6">
                <v-select
                  v-model="adjustForm.type"
                  label="Action Type *"
                  :items="[
                    { title: 'Add (+)', value: 'add' },
                    { title: 'Deduct (-)', value: 'deduct' },
                  ]"
                  item-title="title"
                  item-value="value"
                  variant="outlined"
                  density="compact"
                  :error-messages="adjustForm.errors.type"
                />
              </v-col>

              <v-col cols="6">
                <v-text-field
                  v-model="adjustForm.days"
                  type="number"
                  step="0.5"
                  label="Days *"
                  variant="outlined"
                  density="compact"
                  :error-messages="adjustForm.errors.days"
                />
              </v-col>

              <v-col cols="12">
                <v-textarea
                  v-model="adjustForm.reason"
                  label="Reason / Notes *"
                  variant="outlined"
                  density="compact"
                  rows="3"
                  :error-messages="adjustForm.errors.reason"
                />
              </v-col>
            </v-row>
          </v-card-text>
          <v-card-actions class="justify-end ga-2">
            <v-btn variant="outlined" @click="adjustDialog = false">
              Cancel
            </v-btn>
            <v-btn
              color="secondary"
              :loading="adjustForm.processing"
              @click="submitAdjust"
            >
              Apply Adjustment
            </v-btn>
          </v-card-actions>
        </v-card>
      </v-dialog>
    </v-container>
  </SidebarLayout>
</template>

<script>
import { Head, router, useForm } from "@inertiajs/vue3";
import SidebarLayout from "@/Layouts/SidebarLayout.vue";

export default {
  components: { SidebarLayout, Head },
  props: {
    balances: Object,
    leaveTypes: Array,
    employees: Array,
    filters: Object,
  },
  data() {
    return {
      dialog: false,
      adjustDialog: false,
      isEditing: false,
      selectedId: null,
      filterForm: {
        search: this.filters?.search || "",
        leave_type_id: this.filters?.leave_type_id || null,
        year: this.filters?.year || new Date().getFullYear(),
      },
      form: useForm({
        employee_id: null,
        leave_type_id: null,
        balance: 0,
        used: 0,
        pending: 0,
        as_of_date: new Date().toISOString().substring(0, 10),
      }),
      adjustForm: useForm({
        leave_balance_id: null,
        type: "add",
        days: 1,
        reason: "",
      }),
    };
  },
  methods: {
    applyFilters() {
      router.get(route("leave.balances.index"), this.filterForm, {
        preserveState: true,
        replace: true,
      });
    },
    openModal(b = null) {
      this.form.reset();
      this.form.clearErrors();

      if (b) {
        this.isEditing = true;
        this.selectedId = b.id;
        this.form.employee_id = b.employee_id;
        this.form.leave_type_id = b.leave_type_id;
        this.form.balance = b.balance;
        this.form.used = b.used;
        this.form.pending = b.pending;
        this.form.as_of_date = b.as_of_date;
      } else {
        this.isEditing = false;
        this.selectedId = null;
        this.form.as_of_date = new Date().toISOString().substring(0, 10);
      }

      this.dialog = true;
    },
    openAdjustModal(b = null) {
      this.adjustForm.reset();
      this.adjustForm.clearErrors();

      if (b) {
        this.adjustForm.leave_balance_id = b.id;
      }

      this.adjustDialog = true;
    },
    submit() {
      if (this.isEditing) {
        this.form.put(route("leave.balances.update", this.selectedId), {
          onSuccess: () => (this.dialog = false),
        });
      } else {
        this.form.post(route("leave.balances.store"), {
          onSuccess: () => (this.dialog = false),
        });
      }
    },
    submitAdjust() {
      this.adjustForm.post(route("leave.balances.adjust"), {
        onSuccess: () => (this.adjustDialog = false),
      });
    },
  },
};
</script>