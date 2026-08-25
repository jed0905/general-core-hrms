<template>
  <div>
    <DailyTimeRecordTabs :activeTab="activeTab" @update:activeTab="setActiveTab" />

    <v-card rounded="lg" elevation="2">
      <v-card-title class="d-flex justify-space-between align-center">
        <span class="text-h6 font-weight-bold">Attendance Log Event Types</span>
        <v-btn color="starbucks-green" rounded="xl" variant="flat" @click="openCreateDialog">
          New Event Type
        </v-btn>
      </v-card-title>

      <v-card-text>
        <v-table>
          <thead>
            <tr>
              <th class="text-left">Name</th>
              <th class="text-left">Description</th>
              <th class="text-left">Status</th>
              <th class="text-center">Actions</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="type in eventTypes" :key="type.id">
              <td>
                <span class="font-weight-medium">{{ type.name }}</span>
              </td>
              <td>
                <span class="text-body-2 text-grey-700">
                  {{ type.description || "—" }}
                </span>
              </td>
              <td>
                <v-switch
                  :model-value="Boolean(type.is_active)"
                  :color="Boolean(type.is_active) ? 'starbucks-green' : 'grey-lighten-1'"
                  inset
                  density="compact"
                  @update:modelValue="(val) => toggleStatus(type, val)"
                  :label="Boolean(type.is_active) ? 'Active' : 'Inactive'"
                />
              </td>
              <td class="text-center">
                <v-btn
                  size="x-small"
                  color="yellow-darken-4"
                  variant="tonal"
                  @click="openEditDialog(type)"
                  icon="mdi-pencil"
                >
                </v-btn>

                <v-btn
                  size="x-small"
                  color="red-darken-4"
                  variant="tonal"
                  icon="mdi-delete"
                  class="ml-2"
                  @click="openDeleteDialog(type)"
                ></v-btn>
              </td>
            </tr>
            <tr v-if="!eventTypes.length">
              <td colspan="4" class="text-center text-grey-600 py-6">
                No event types defined yet.
              </td>
            </tr>
          </tbody>
        </v-table>
      </v-card-text>
    </v-card>

    <v-dialog v-model="dialogOpen" max-width="500">
      <v-card>
        <v-form @submit.prevent="submit">
          <v-card-title class="text-h6 font-weight-bold">
            {{ isEditing ? "Edit Event Type" : "New Event Type" }}
          </v-card-title>
          <v-card-text>
            <v-text-field
              v-model="form.name"
              label="Name (code, e.g. official_travel)"
              variant="outlined"
              density="comfortable"
              class="mb-3"
              rounded="lg"
              :error-messages="v$.form.name.$errors.map(e => e.$message)"
            />
            <v-textarea
              v-model="form.description"
              label="Description"
              variant="outlined"
              rows="2"
              auto-grow
              class="mb-3"
              rounded="lg"
            />
          </v-card-text>
          <v-card-actions>
            <v-spacer />
            <v-btn
              rounded="xl"
              variant="text"
              type="button"
              @click="closeDialog"
              min-width="120"
            >
              Cancel
            </v-btn>
            <v-btn
              color="starbucks-green"
              variant="flat"
              :loading="form.processing"
              type="submit"
              min-width="120"
              rounded="xl"
            >
              Save
            </v-btn>
          </v-card-actions>
        </v-form>
      </v-card>
    </v-dialog>

    <!-- Delete confirmation dialog -->
    <v-dialog v-model="deleteDialogOpen" max-width="420">
      <v-card>
        <v-card-title class="text-h6 font-weight-bold">
          Delete Event Type
        </v-card-title>
        <v-card-text>
          Are you sure you want to delete
          <strong>{{ deleteTarget?.name }}</strong>?
          This action cannot be undone.
        </v-card-text>
        <v-card-actions>
          <v-spacer />
          <v-btn
            variant="text"
            rounded="xl"
            min-width="100"
            @click="closeDeleteDialog"
          >
            Cancel
          </v-btn>
          <v-btn
            color="red-darken-4"
            variant="flat"
            rounded="xl"
            min-width="100"
            :loading="deleteProcessing"
            @click="confirmDelete"
          >
            Delete
          </v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>
  </div>
</template>

<script>
import SidebarLayout from "@/layouts/SidebarLayout.vue";
import { useForm } from "@inertiajs/vue3";
import DailyTimeRecordTabs from "@/components/DailyTimeRecordTabs.vue";
import useVuelidate from '@vuelidate/core';
import { required, helpers } from '@vuelidate/validators';

export default {
  layout: SidebarLayout,
  components: {
    DailyTimeRecordTabs,
  },
  props: {
    errors: Object,
    eventTypes: {
      type: Array,
      required: true,
    },
  },
  data() {
    return {
      v$: useVuelidate(),
      activeTab: "eventTypes",
      dialogOpen: false,
      isEditing: false,
      editingId: null,
      deleteDialogOpen: false,
      deleteTarget: null,
      deleteProcessing: false,
      form: useForm({
        name: "",
        description: "",
      }),
    };
  },
  validations() {
    return {
      form: {
        name: { required: helpers.withMessage("Name is required", required) },
      },
    };
  },
  methods: {
    setActiveTab(tab) {
      this.activeTab = tab;
    },
    openCreateDialog() {
      this.isEditing = false;
      this.editingId = null;
      this.form.reset();
    
      this.dialogOpen = true;
    },
    openEditDialog(type) {
      this.isEditing = true;
      this.editingId = type.id;
      this.form.name = type.name;
      this.form.description = type.description;
      this.dialogOpen = true;
    },
    openDeleteDialog(type) {
      this.deleteTarget = type;
      this.deleteDialogOpen = true;
    },
    closeDeleteDialog() {
      this.deleteDialogOpen = false;
      this.deleteTarget = null;
      this.deleteProcessing = false;
    },
    closeDialog() {
      this.dialogOpen = false;
      this.form.reset();
      this.editingId = null;
      this.isEditing = false;
    },
    submit() {
      if (this.isEditing && this.editingId) {
        this.v$.$touch();
        if (this.v$.$invalid) return;
        this.form.put(
          route(
            "hrmanagement.dailytimerecord.attendanceLogEventTypes.update",
            this.editingId
          ),
          {
            onSuccess: () => {
              this.showToast("Event type updated successfully", "success");
              this.closeDialog();
              this.v$.$reset();
            },
            onError: (errors) => {
              const errorMessages = Object.values(errors).flat().join(" ");
              this.showToast(`${errorMessages}`, "error");
            },
          }
        );
      } else {
        this.v$.$touch();
        if (this.v$.$invalid) return;
        this.form.post(
          route("hrmanagement.dailytimerecord.attendanceLogEventTypes.store"),
          {
            onSuccess: () => {
              this.showToast("Event type created successfully", "success");
              this.closeDialog();
              this.v$.$reset();
            },
            onError: (errors) => {
              const errorMessages = Object.values(errors).flat().join(" ");
              this.showToast(`${errorMessages}`, "error");
            },
          }
        );
      }
    },
    toggleStatus(type, isActive) {
      this.$inertia.put(
        route(
          "hrmanagement.dailytimerecord.attendanceLogEventTypes.update",
          type.id
        ),
        {
          name: type.name,
          description: type.description,
          is_active: isActive,
        },
        {
          onSuccess: () => {
            this.showToast(
              `Event type ${isActive ? "activated" : "deactivated"} successfully`,
              "success"
            );
          },
          onError: (errors) => {
            const errorMessages = Object.values(errors).flat().join(" ");
            this.showToast(`${errorMessages}`, "error");
          },
          onFinish: () => {
            // revert local toggle if server-side validation fails
            if (this.errors && Object.keys(this.errors).length) {
              type.is_active = !isActive;
            }
          },
        }
      );
    },
    confirmDelete() {
      if (!this.deleteTarget) return;
      this.deleteProcessing = true;

      this.$inertia.delete(
        route(
          "hrmanagement.dailytimerecord.attendanceLogEventTypes.destroy",
          this.deleteTarget.id
        ),
        {
          onSuccess: () => {
            this.showToast("Event type deleted successfully", "success");
            this.closeDeleteDialog();
          },
          onError: (errors) => {
            const errorMessages = Object.values(errors).flat().join(" ");
            this.showToast(`${errorMessages}`, "error");
            this.deleteProcessing = false;
          },
        }
      );
    },
  },
};
</script>
