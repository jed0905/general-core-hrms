<script setup>
import { ref, watch } from "vue";
import { Head, router, useForm } from "@inertiajs/vue3";
import SidebarLayout from "@/Layouts/SidebarLayout.vue";

const props = defineProps({
  employmentStatuses: {
    type: Object,
    required: true,
  },
  filters: {
    type: Object,
    default: () => ({}),
  },
});

// Local Filter States
const search = ref(props.filters?.search || "");

// Filter Dispatcher
let debounceTimer = null;
const triggerFilter = () => {
  clearTimeout(debounceTimer);
  debounceTimer = setTimeout(() => {
    router.get(
      route("people.employment-status.index"),
      {
        search: search.value || undefined,
      },
      { preserveState: true, replace: true }
    );
  }, 300);
};

watch(search, () => {
  triggerFilter();
});

const resetFilters = () => {
  search.value = "";
};

const handlePageChange = (page) => {
  router.get(
    route("people.employment-status.index"),
    {
      ...props.filters,
      page,
    },
    { preserveState: true }
  );
};

// Form & Modal States
const dialog = ref(false);
const archiveDialog = ref(false);
const isEditing = ref(false);
const selectedStatus = ref(null);

const form = useForm({
  id: null,
  name: "",
});

const openCreateModal = () => {
  isEditing.value = false;
  form.reset();
  form.clearErrors();
  dialog.value = true;
};

const openEditModal = (item) => {
  isEditing.value = true;
  form.clearErrors();
  form.id = item.id;
  form.name = item.name;
  dialog.value = true;
};

const openArchiveModal = (item) => {
  selectedStatus.value = item;
  archiveDialog.value = true;
};

const submitForm = () => {
  if (isEditing.value) {
    form.put(route("people.employment-status.update", form.id), {
      preserveScroll: true,
      onSuccess: () => {
        dialog.value = false;
        form.reset();
      },
    });
  } else {
    form.post(route("people.employment-status.store"), {
      preserveScroll: true,
      onSuccess: () => {
        dialog.value = false;
        form.reset();
      },
    });
  }
};

const submitArchive = () => {
  if (!selectedStatus.value) return;

  router.patch(
    route("people.employment-status.archive", selectedStatus.value.id),
    {},
    {
      preserveScroll: true,
      onSuccess: () => {
        archiveDialog.value = false;
        selectedStatus.value = null;
      },
    }
  );
};

const formatDate = (dateString) => {
  if (!dateString) return "—";
  return new Date(dateString).toLocaleDateString("en-US", {
    year: "numeric",
    month: "short",
    day: "numeric",
  });
};
</script>

<template>
  <SidebarLayout>
    <Head title="Employment Statuses" />

    <v-container fluid class="pa-6">
      <!-- Header Bar -->
      <div
        class="d-flex align-center justify-space-between flex-wrap ga-3 mb-6"
      >
        <div>
          <h1 class="text-h5 font-weight-bold text-high-emphasis">
            Employment Statuses
          </h1>
          <p class="text-body-2 text-medium-emphasis">
            Manage employment status options and classification categories.
          </p>
        </div>

        <div class="d-flex align-center ga-2">
          <v-btn
            color="primary"
            prepend-icon="mdi-plus"
            elevation="0"
            size="small"
            @click="openCreateModal"
          >
            Add Status
          </v-btn>
        </div>
      </div>

      <!-- Filters Toolbar -->
      <v-card variant="outlined" class="rounded-lg mb-6">
        <v-card-text class="pa-4">
          <v-row density="compact" align="center">
            <!-- Search -->
            <v-col cols="12" sm="6" md="4">
              <v-text-field
                v-model="search"
                placeholder="Search employment status..."
                prepend-inner-icon="mdi-magnify"
                variant="outlined"
                density="compact"
                hide-details
                clearable
                @click:clear="search = ''"
              />
            </v-col>

            <!-- Clear Action -->
            <v-col cols="12" sm="6" md="2">
              <v-btn
                variant="text"
                color="primary"
                size="small"
                class="px-2"
                :disabled="!search"
                @click="resetFilters"
              >
                Clear
              </v-btn>
            </v-col>
          </v-row>
        </v-card-text>
      </v-card>

      <!-- Data Table Card -->
      <v-card variant="outlined" class="rounded-lg">
        <v-table class="status-table">
          <thead>
            <tr>
              <th class="text-caption font-weight-bold">STATUS NAME</th>
              <th class="text-caption font-weight-bold">CREATED AT</th>
              <th class="text-caption font-weight-bold text-end">ACTIONS</th>
            </tr>
          </thead>
          <tbody>
            <template
              v-if="
                employmentStatuses.data && employmentStatuses.data.length > 0
              "
            >
              <tr
                v-for="statusItem in employmentStatuses.data"
                :key="statusItem.id"
                class="status-row"
              >
                <!-- Status Name -->
                <td class="py-3">
                  <div class="text-body-2 font-weight-bold text-high-emphasis">
                    {{ statusItem.name }}
                  </div>
                </td>

                <!-- Created At -->
                <td class="py-3">
                  <div class="text-body-2 text-medium-emphasis">
                    {{ formatDate(statusItem.created_at) }}
                  </div>
                </td>

                <!-- Actions Menu -->
                <td class="text-end py-3">
                  <div class="d-flex align-center justify-end ga-1">
                    <!-- Edit Button -->
                    <v-btn
                      icon="mdi-pencil-outline"
                      variant="text"
                      size="x-small"
                      color="medium-emphasis"
                      @click="openEditModal(statusItem)"
                    />

                    <!-- Archive Button -->
                    <v-btn
                      icon="mdi-archive-outline"
                      variant="text"
                      size="x-small"
                      color="medium-emphasis"
                      @click="openArchiveModal(statusItem)"
                    />
                  </div>
                </td>
              </tr>
            </template>

            <template v-else>
              <tr>
                <td colspan="3" class="text-center py-8 text-medium-emphasis">
                  <v-icon icon="mdi-list-status" size="40" class="mb-2" />
                  <div class="text-body-2">No employment statuses found.</div>
                </td>
              </tr>
            </template>
          </tbody>
        </v-table>

        <!-- Pagination Bar -->
        <v-card-actions
          v-if="employmentStatuses.last_page > 1"
          class="py-3 px-4 border-t justify-space-between flex-wrap ga-2"
        >
          <div class="text-caption text-medium-emphasis">
            Showing {{ employmentStatuses.from }} to
            {{ employmentStatuses.to }} of
            {{ employmentStatuses.total }} entries
          </div>

          <v-pagination
            :model-value="employmentStatuses.current_page"
            :length="employmentStatuses.last_page"
            density="compact"
            total-visible="5"
            active-color="primary"
            @update:model-value="handlePageChange"
          />
        </v-card-actions>
      </v-card>
    </v-container>

    <!-- Create / Edit Dialog -->
    <v-dialog v-model="dialog" max-width="480" persistent>
      <v-card rounded="lg">
        <v-card-title class="text-h6 font-weight-bold pa-5 border-b">
          {{ isEditing ? "Edit Employment Status" : "Add Employment Status" }}
        </v-card-title>

        <form @submit.prevent="submitForm">
          <v-card-text class="pa-5">
            <v-text-field
              v-model="form.name"
              label="Status Name"
              placeholder="e.g. Regular, Probationary, Contractual"
              variant="outlined"
              density="compact"
              :error-messages="form.errors.name"
              autofocus
            />
          </v-card-text>

          <v-divider />

          <v-card-actions class="pa-4 justify-end">
            <v-btn variant="text" @click="dialog = false">Cancel</v-btn>
            <v-btn
              type="submit"
              color="primary"
              elevation="0"
              :loading="form.processing"
            >
              {{ isEditing ? "Update" : "Save" }}
            </v-btn>
          </v-card-actions>
        </form>
      </v-card>
    </v-dialog>

    <!-- Archive Confirmation Modal -->
    <v-dialog v-model="archiveDialog" max-width="400">
      <v-card rounded="lg">
        <v-card-title class="text-h6 font-weight-bold pa-5 border-b">
          Archive Employment Status
        </v-card-title>

        <v-card-text class="pa-5 text-body-2">
          Are you sure you want to archive
          <strong>{{ selectedStatus?.name }}</strong
          >?
        </v-card-text>

        <v-divider />

        <v-card-actions class="pa-4 justify-end">
          <v-btn variant="text" @click="archiveDialog = false">Cancel</v-btn>
          <v-btn color="error" elevation="0" @click="submitArchive">
            Archive
          </v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>
  </SidebarLayout>
</template>

<style scoped>
.status-table :deep(th) {
  height: 44px !important;
  background-color: rgba(var(--v-theme-on-surface), 0.02);
}

.status-row:hover {
  background-color: rgba(var(--v-theme-on-surface), 0.02);
}
</style>
