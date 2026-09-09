<template>
  <SidebarLayout>
    <Head title="Holiday Management" />

    <v-container fluid class="pa-6">
      <!-- Top Action Bar -->
      <div
        class="d-flex align-center justify-space-between flex-wrap ga-3 mb-6"
      >
        <div>
          <h1 class="text-h5 font-weight-bold text-high-emphasis">
            Holiday Management
          </h1>
          <p class="text-body-2 text-medium-emphasis">
            Configure statutory, special, and company holiday calendars and pay rules.
          </p>
        </div>

        <v-btn
          v-if="can('holiday.create')"
          color="primary"
          prepend-icon="mdi-plus"
          elevation="0"
          @click="openCreateModal"
        >
          Add Holiday
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
                placeholder="Search name or code..."
                prepend-inner-icon="mdi-magnify"
                hide-details
                clearable
                @keyup.enter="applyFilters"
                @click:clear="clearSearch"
              />
            </v-col>

            <v-col cols="12" sm="6" md="2">
              <v-select
                v-model="yearFilter"
                density="compact"
                variant="outlined"
                label="Year"
                :items="yearOptions"
                hide-details
                clearable
                @update:model-value="applyFilters"
              />
            </v-col>

            <v-col cols="12" sm="6" md="3">
              <v-select
                v-model="typeFilter"
                density="compact"
                variant="outlined"
                label="Holiday Type"
                :items="holidayTypes"
                item-title="title"
                item-value="value"
                hide-details
                clearable
                @update:model-value="applyFilters"
              />
            </v-col>

            <v-col cols="12" sm="6" md="2">
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

            <v-col cols="12" sm="6" md="2">
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

      <!-- Holidays Table -->
      <v-card variant="outlined" class="rounded-lg">
        <v-table hover class="bg-transparent">
          <thead>
            <tr>
              <th class="text-left font-weight-bold">Holiday Date</th>
              <th class="text-left font-weight-bold">Holiday Name</th>
              <th class="text-left font-weight-bold">Code</th>
              <th class="text-left font-weight-bold">Type</th>
              <th class="text-left font-weight-bold">Pay & Work Rules</th>
              <th class="text-left font-weight-bold">Status</th>
              <th class="text-end font-weight-bold">Actions</th>
            </tr>
          </thead>

          <tbody>
            <tr v-if="!holidays.data || holidays.data.length === 0">
              <td colspan="7" class="text-center py-8 text-medium-emphasis">
                No holidays configured yet.
              </td>
            </tr>

            <tr v-for="item in holidays.data" :key="item.id">
              <td>
                <div class="font-weight-bold text-high-emphasis">
                  {{ formatDate(item.date) }}
                </div>
                <div class="text-caption text-medium-emphasis">
                  {{ getDayOfWeek(item.date) }}
                </div>
              </td>

              <td>
                <div class="font-weight-medium text-high-emphasis">
                  {{ item.name }}
                </div>
                <div
                  v-if="item.description"
                  class="text-caption text-medium-emphasis text-truncate"
                  style="max-width: 250px;"
                >
                  {{ item.description }}
                </div>
              </td>

              <td>
                <v-chip
                  v-if="item.code"
                  size="small"
                  variant="outlined"
                  class="font-mono"
                >
                  {{ item.code }}
                </v-chip>
                <span v-else class="text-caption text-medium-emphasis">—</span>
              </td>

              <td>
                <v-chip
                  size="small"
                  :color="getTypeColor(item.type)"
                  variant="tonal"
                  class="font-weight-medium"
                >
                  {{ formatType(item.type) }}
                </v-chip>
              </td>

              <td>
                <div class="d-flex ga-1 flex-wrap">
                  <v-chip
                    size="x-small"
                    :color="item.is_paid ? 'success' : 'grey'"
                    variant="flat"
                  >
                    {{ item.is_paid ? "Paid" : "Unpaid" }}
                  </v-chip>
                  <v-chip
                    size="x-small"
                    :color="item.is_working_day ? 'warning' : 'info'"
                    variant="flat"
                  >
                    {{ item.is_working_day ? "Working Day" : "Non-Working" }}
                  </v-chip>
                  <v-chip
                    v-if="item.is_recurring"
                    size="x-small"
                    color="purple"
                    variant="flat"
                  >
                    Recurring
                  </v-chip>
                </div>
              </td>

              <td>
                <v-chip
                  size="small"
                  :color="item.status === 'active' ? 'success' : 'error'"
                  variant="tonal"
                  class="font-weight-medium"
                >
                  {{ item.status === 'active' ? 'Active' : 'Inactive' }}
                </v-chip>
              </td>

              <td class="text-end">
                <div class="d-flex align-center justify-end ga-1">
                  <v-btn
                    v-if="can('holiday.update')"
                    icon="mdi-pencil-outline"
                    variant="text"
                    size="small"
                    color="medium-emphasis"
                    @click="openEditModal(item)"
                  />

                  <v-btn
                    v-if="can('holiday.delete')"
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
            Showing {{ holidays.from || 0 }} to {{ holidays.to || 0 }} of
            {{ holidays.total || 0 }} records
          </div>

          <v-pagination
            v-if="holidays.last_page > 1"
            v-model="currentPage"
            :length="holidays.last_page"
            density="comfortable"
            total-visible="5"
            @update:model-value="changePage"
          />
        </div>
      </v-card>

      <!-- Create / Edit Holiday Dialog -->
      <v-dialog v-model="formModal" max-width="600" persistent>
        <v-card class="rounded-lg pa-2">
          <v-card-title class="text-h6 font-weight-bold">
            {{ isEditing ? "Edit Holiday" : "Create Holiday" }}
          </v-card-title>

          <v-card-text class="pa-4">
            <v-row density="compact">
              <v-col cols="12" sm="8">
                <v-text-field
                  v-model="form.name"
                  label="Holiday Name *"
                  placeholder="e.g. Christmas Day"
                  variant="outlined"
                  density="compact"
                  :error-messages="form.errors.name"
                />
              </v-col>

              <v-col cols="12" sm="4">
                <v-text-field
                  v-model="form.code"
                  label="Holiday Code"
                  placeholder="e.g. XMAS"
                  variant="outlined"
                  density="compact"
                  :error-messages="form.errors.code"
                />
              </v-col>

              <v-col cols="12" sm="6">
                <v-text-field
                  v-model="form.date"
                  type="date"
                  label="Holiday Date *"
                  variant="outlined"
                  density="compact"
                  :error-messages="form.errors.date"
                />
              </v-col>

              <v-col cols="12" sm="6">
                <v-select
                  v-model="form.type"
                  :items="holidayTypes"
                  item-title="title"
                  item-value="value"
                  label="Holiday Type *"
                  variant="outlined"
                  density="compact"
                  :error-messages="form.errors.type"
                />
              </v-col>

              <v-col cols="12" sm="6">
                <v-select
                  v-model="form.status"
                  :items="statusOptions"
                  item-title="title"
                  item-value="value"
                  label="Status *"
                  variant="outlined"
                  density="compact"
                  :error-messages="form.errors.status"
                />
              </v-col>

              <v-col cols="12" sm="6" class="d-flex align-center">
                <v-switch
                  v-model="form.is_recurring"
                  color="primary"
                  label="Annual Recurring?"
                  density="compact"
                  hide-details
                />
              </v-col>

              <v-col cols="12" sm="6">
                <v-switch
                  v-model="form.is_paid"
                  color="primary"
                  label="Is Paid?"
                  density="compact"
                  hide-details
                />
              </v-col>

              <v-col cols="12" sm="6">
                <v-switch
                  v-model="form.is_working_day"
                  color="primary"
                  label="Working Day?"
                  density="compact"
                  hide-details
                />
              </v-col>

              <v-col cols="12" class="mt-2">
                <v-textarea
                  v-model="form.description"
                  label="Description"
                  placeholder="Optional details or statutory remarks..."
                  variant="outlined"
                  density="compact"
                  rows="3"
                  :error-messages="form.errors.description"
                />
              </v-col>
            </v-row>
          </v-card-text>

          <v-card-actions class="justify-end ga-2 px-4 pb-3">
            <v-btn
              variant="outlined"
              color="medium-emphasis"
              :disabled="form.processing"
              @click="formModal = false"
            >
              Cancel
            </v-btn>

            <v-btn
              color="primary"
              elevation="0"
              :loading="form.processing"
              @click="saveHoliday"
            >
              {{ isEditing ? "Update" : "Save" }}
            </v-btn>
          </v-card-actions>
        </v-card>
      </v-dialog>

      <!-- Delete Confirmation Dialog -->
      <v-dialog v-model="deleteModal" max-width="450">
        <v-card class="rounded-lg pa-2">
          <v-card-title class="text-h6 font-weight-bold">
            Delete Holiday?
          </v-card-title>

          <v-card-text class="text-body-2 text-medium-emphasis">
            Are you sure you want to delete
            <strong>{{ selectedItem?.name }}</strong> ({{ formatDate(selectedItem?.date) }})? This action cannot be undone.
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
              Delete
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
  name: "HolidayIndex",

  components: {
    SidebarLayout,
    Head,
  },

  props: {
    holidays: {
      type: Object,
      required: true,
    },
    filters: {
      type: Object,
      default: () => ({}),
    },
    holidayTypes: {
      type: Array,
      default: () => [],
    },
  },

  data() {
    const currentYear = new Date().getFullYear();

    return {
      search: this.filters.search || "",
      yearFilter: this.filters.year || currentYear,
      typeFilter: this.filters.type || null,
      statusFilter: this.filters.status || null,

      currentPage: this.holidays.current_page || 1,

      formModal: false,
      deleteModal: false,
      isEditing: false,

      selectedItem: null,
      submitting: false,

      yearOptions: [
        currentYear - 1,
        currentYear,
        currentYear + 1,
        currentYear + 2,
      ],

      statusOptions: [
        { title: "Active", value: "active" },
        { title: "Inactive", value: "inactive" },
      ],

      form: useForm({
        name: "",
        code: "",
        date: new Date().toISOString().substring(0, 10),
        type: "regular",
        is_paid: true,
        is_working_day: false,
        is_recurring: false,
        description: "",
        status: "active",
      }),
    };
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

    getDayOfWeek(dateStr) {
      if (!dateStr) return "";
      const date = new Date(dateStr);
      return date.toLocaleDateString("en-US", { weekday: "long" });
    },

    formatType(type) {
      const matched = this.holidayTypes.find((t) => t.value === type);
      return matched ? matched.title : type;
    },

    getTypeColor(type) {
      switch (type) {
        case "regular":
          return "primary";
        case "special_non_working":
          return "warning";
        case "special_working":
          return "info";
        case "company":
          return "purple";
        default:
          return "grey";
      }
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
        route("time.holidays.index"),
        {
          search: this.search || undefined,
          year: this.yearFilter || undefined,
          type: this.typeFilter || undefined,
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
        route("time.holidays.index"),
        {
          page,
          search: this.search || undefined,
          year: this.yearFilter || undefined,
          type: this.typeFilter || undefined,
          status: this.statusFilter || undefined,
        },
        { preserveState: true, replace: true }
      );
    },

    openCreateModal() {
      this.isEditing = false;
      this.selectedItem = null;
      this.form.reset();
      this.form.clearErrors();
      this.form.date = new Date().toISOString().substring(0, 10);
      this.form.type = "regular";
      this.form.status = "active";
      this.form.is_paid = true;
      this.form.is_working_day = false;
      this.form.is_recurring = false;
      this.formModal = true;
    },

    openEditModal(item) {
      this.isEditing = true;
      this.selectedItem = item;
      this.form.clearErrors();
      this.form.name = item.name;
      this.form.code = item.code || "";
      this.form.date = item.date;
      this.form.type = item.type;
      this.form.is_paid = Boolean(item.is_paid);
      this.form.is_working_day = Boolean(item.is_working_day);
      this.form.is_recurring = Boolean(item.is_recurring);
      this.form.description = item.description || "";
      this.form.status = item.status;
      this.formModal = true;
    },

    saveHoliday() {
      if (this.isEditing && this.selectedItem) {
        this.form.put(route("time.holidays.update", this.selectedItem.id), {
          onSuccess: () => {
            this.formModal = false;
            this.showToast("Holiday updated successfully!", "success");
          },
          onError: () => {
            this.showToast(
              "Failed to update holiday. Check form errors.",
              "error"
            );
          },
        });
      } else {
        this.form.post(route("time.holidays.store"), {
          onSuccess: () => {
            this.formModal = false;
            this.form.reset();
            this.showToast("Holiday created successfully!", "success");
          },
          onError: () => {
            this.showToast(
              "Failed to create holiday. Check form errors.",
              "error"
            );
          },
        });
      }
    },

    confirmDelete(item) {
      this.selectedItem = item;
      this.deleteModal = true;
    },

    executeDelete() {
      if (!this.selectedItem) return;

      this.submitting = true;
      router.delete(route("time.holidays.destroy", this.selectedItem.id), {
        onSuccess: () => {
          this.deleteModal = false;
          this.selectedItem = null;
          this.showToast("Holiday deleted successfully", "success");
        },
        onError: () => {
          this.showToast("Failed to delete holiday", "error");
        },
        onFinish: () => {
          this.submitting = false;
        },
      });
    },
  },
};
</script>