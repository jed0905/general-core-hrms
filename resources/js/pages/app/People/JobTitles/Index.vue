<script setup>
import { ref, watch } from "vue";
import { Head, router, useForm } from "@inertiajs/vue3";
import SidebarLayout from "@/Layouts/SidebarLayout.vue";

const props = defineProps({
  jobTitles: {
    type: Object,
    required: true,
  },
  filters: {
    type: Object,
    default: () => ({}),
  },
});

// Search & Filter Dispatcher
const search = ref(props.filters?.search || "");

let debounceTimer = null;
const triggerFilter = () => {
  clearTimeout(debounceTimer);
  debounceTimer = setTimeout(() => {
    router.get(
      route("people.job-title.index"),
      { search: search.value || undefined },
      { preserveState: true, replace: true }
    );
  }, 300);
};

watch(search, () => {
  triggerFilter();
});

const handlePageChange = (page) => {
  router.get(
    route("people.job-title.index"),
    { ...props.filters, page },
    { preserveState: true }
  );
};

// Create / Edit Modal State
const dialog = ref(false);
const isEditing = ref(false);
const activeId = ref(null);

const form = useForm({
  job_title: "",
  job_description: "",
});

const openCreateModal = () => {
  form.reset();
  form.clearErrors();
  isEditing.value = false;
  activeId.value = null;
  dialog.value = true;
};

const openEditModal = (item) => {
  form.clearErrors();
  form.job_title = item.job_title;
  form.job_description = item.job_description || "";
  isEditing.value = true;
  activeId.value = item.id;
  dialog.value = true;
};

const submitForm = () => {
  if (isEditing.value) {
    form.put(route("people.job-title.update", activeId.value), {
      preserveScroll: true,
      onSuccess: () => {
        dialog.value = false;
        form.reset();
      },
    });
  } else {
    form.post(route("people.job-title.store"), {
      preserveScroll: true,
      onSuccess: () => {
        dialog.value = false;
        form.reset();
      },
    });
  }
};

// Archive Modal State
const archiveDialog = ref(false);
const selectedJobTitle = ref(null);

const openArchiveModal = (item) => {
  selectedJobTitle.value = item;
  archiveDialog.value = true;
};

const submitArchive = () => {
  if (!selectedJobTitle.value) return;
  router.patch(
    route("people.job-title.archive", selectedJobTitle.value.id),
    {},
    {
      preserveScroll: true,
      onSuccess: () => {
        archiveDialog.value = false;
        selectedJobTitle.value = null;
      },
    }
  );
};
</script>

<template>
  <SidebarLayout>
    <Head title="Job Titles" />

    <v-container fluid class="pa-6">
      <!-- Header Bar -->
      <div
        class="d-flex align-center justify-space-between flex-wrap ga-3 mb-6"
      >
        <div>
          <h1 class="text-h5 font-weight-bold text-high-emphasis">
            Job Titles
          </h1>
          <p class="text-body-2 text-medium-emphasis">
            Manage position designations, job responsibilities, and staff
            assignments.
          </p>
        </div>

        <v-btn
          color="primary"
          prepend-icon="mdi-plus"
          elevation="0"
          size="small"
          @click="openCreateModal"
        >
          Add Job Title
        </v-btn>
      </div>

      <!-- Search Toolbar -->
      <v-card variant="outlined" class="rounded-lg mb-6">
        <v-card-text class="pa-4">
          <v-row density="compact" align="center">
            <v-col cols="12" sm="6" md="4">
              <v-text-field
                v-model="search"
                placeholder="Search job title or description..."
                prepend-inner-icon="mdi-magnify"
                variant="outlined"
                density="compact"
                hide-details
                clearable
                @click:clear="search = ''"
              />
            </v-col>
          </v-row>
        </v-card-text>
      </v-card>

      <!-- Job Titles Table Card -->
      <v-card variant="outlined" class="rounded-lg">
        <v-table class="job-titles-table">
          <thead>
            <tr>
              <th class="text-caption font-weight-bold">JOB TITLE</th>
              <th class="text-caption font-weight-bold">DESCRIPTION</th>
              <th class="text-caption font-weight-bold">EMPLOYEES</th>
              <th class="text-caption font-weight-bold text-end">ACTIONS</th>
            </tr>
          </thead>
          <tbody>
            <template v-if="jobTitles.data && jobTitles.data.length > 0">
              <tr
                v-for="item in jobTitles.data"
                :key="item.id"
                class="job-title-row"
              >
                <!-- Job Title -->
                <td
                  class="py-3 font-weight-bold text-body-2 text-high-emphasis"
                >
                  {{ item.job_title }}
                </td>

                <!-- Description -->
                <td
                  class="text-body-2 text-medium-emphasis text-truncate max-width-desc"
                >
                  {{ item.job_description || "No description provided." }}
                </td>

                <!-- Employees Count Badge -->
                <td>
                  <v-chip
                    size="x-small"
                    color="primary"
                    variant="tonal"
                    class="font-weight-medium"
                  >
                    {{ item.employees_count || 0 }}
                    {{ item.employees_count === 1 ? "Employee" : "Employees" }}
                  </v-chip>
                </td>

                <!-- Actions -->
                <td class="text-end">
                  <div class="d-flex align-center justify-end ga-1">
                    <v-btn
                      icon="mdi-pencil-outline"
                      variant="text"
                      size="x-small"
                      color="medium-emphasis"
                      @click="openEditModal(item)"
                    />

                    <v-btn
                      icon="mdi-delete-outline"
                      variant="text"
                      size="x-small"
                      color="error"
                      @click="openArchiveModal(item)"
                    />
                  </div>
                </td>
              </tr>
            </template>

            <template v-else>
              <tr>
                <td colspan="4" class="text-center py-8 text-medium-emphasis">
                  <v-icon
                    icon="mdi-briefcase-off-outline"
                    size="40"
                    class="mb-2"
                  />
                  <div class="text-body-2">No job titles found.</div>
                </td>
              </tr>
            </template>
          </tbody>
        </v-table>

        <!-- Pagination Bar -->
        <v-card-actions
          v-if="jobTitles.last_page > 1"
          class="py-3 px-4 border-t justify-space-between flex-wrap ga-2"
        >
          <div class="text-caption text-medium-emphasis">
            Showing {{ jobTitles.from }} to {{ jobTitles.to }} of
            {{ jobTitles.total }} entries
          </div>

          <v-pagination
            :model-value="jobTitles.current_page"
            :length="jobTitles.last_page"
            density="compact"
            total-visible="5"
            active-color="primary"
            @update:model-value="handlePageChange"
          />
        </v-card-actions>
      </v-card>
    </v-container>

    <!-- Create / Edit Dialog -->
    <v-dialog v-model="dialog" max-width="500">
      <v-card rounded="lg">
        <v-card-title class="text-h6 font-weight-bold pa-5 border-b">
          {{ isEditing ? "Edit Job Title" : "Add New Job Title" }}
        </v-card-title>

        <v-card-text class="pa-5">
          <v-row density="compact">
            <v-col cols="12">
              <v-text-field
                v-model="form.job_title"
                label="Job Title *"
                placeholder="e.g. Senior Software Engineer"
                variant="outlined"
                density="compact"
                :error-messages="form.errors.job_title"
                autofocus
              />
            </v-col>

            <v-col cols="12">
              <v-textarea
                v-model="form.job_description"
                label="Job Description"
                placeholder="Brief summary of duties and responsibilities..."
                variant="outlined"
                density="compact"
                rows="3"
                hide-details="auto"
                :error-messages="form.errors.job_description"
              />
            </v-col>
          </v-row>
        </v-card-text>

        <v-divider />

        <v-card-actions class="pa-4 justify-end">
          <v-btn variant="text" @click="dialog = false">Cancel</v-btn>
          <v-btn
            color="primary"
            elevation="0"
            :loading="form.processing"
            @click="submitForm"
          >
            {{ isEditing ? "Update" : "Save" }}
          </v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>

    <!-- Archive Confirmation Dialog -->
    <v-dialog v-model="archiveDialog" max-width="400">
      <v-card rounded="lg">
        <v-card-title class="text-h6 font-weight-bold pa-5 border-b">
          Archive Job Title
        </v-card-title>

        <v-card-text class="pa-5 text-body-2">
          Are you sure you want to archive
          <strong>{{ selectedJobTitle?.job_title }}</strong
          >?
        </v-card-text>

        <v-divider />

        <v-card-actions class="pa-4 justify-end">
          <v-btn variant="text" @click="archiveDialog = false">Cancel</v-btn>
          <v-btn color="error" elevation="0" @click="submitArchive"
            >Archive</v-btn
          >
        </v-card-actions>
      </v-card>
    </v-dialog>
  </SidebarLayout>
</template>

<style scoped>
.job-titles-table :deep(th) {
  height: 44px !important;
  background-color: rgba(var(--v-theme-on-surface), 0.02);
}

.job-title-row:hover {
  background-color: rgba(var(--v-theme-on-surface), 0.02);
}

.max-width-desc {
  max-width: 320px;
}
</style>