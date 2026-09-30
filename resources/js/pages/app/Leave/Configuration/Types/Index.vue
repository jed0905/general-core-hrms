<template>
  <SidebarLayout>
    <Head title="Leave Types" />

    <v-container fluid class="pa-6">
      <div class="d-flex align-center justify-space-between mb-6">
        <div>
          <h1 class="text-h5 font-weight-bold">Leave Types</h1>
          <p class="text-body-2 text-medium-emphasis">
            Manage global leave definitions.
          </p>
        </div>

        <v-btn
          color="primary"
          prepend-icon="mdi-plus"
          elevation="0"
          v-if="can('leave_type.create')"
          @click="openModal()"
        >
          Add Leave Type
        </v-btn>
      </div>

      <v-card variant="outlined" class="rounded-lg">
        <v-table hover>
          <thead>
            <tr>
              <th class="font-weight-bold">Name</th>
              <th class="font-weight-bold">Code</th>
              <th class="font-weight-bold">Status</th>
              <th class="text-end font-weight-bold">Actions</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="!leaveTypes.data.length">
              <td colspan="4" class="text-center py-6 text-medium-emphasis">
                No leave types found.
              </td>
            </tr>

            <tr v-for="type in leaveTypes.data" :key="type.id">
              <td>
                <div class="font-weight-medium">{{ type.name }}</div>
                <div class="text-caption text-medium-emphasis">
                  {{ type.description || "N/A" }}
                </div>
              </td>
              <td>
                <v-chip size="small" variant="outlined">{{ type.code }}</v-chip>
              </td>
              <td>
                <v-chip
                  size="small"
                  :color="type.is_active ? 'success' : 'error'"
                  variant="tonal"
                >
                  {{ type.is_active ? "Active" : "Archived" }}
                </v-chip>
              </td>
              <td class="text-end">
                <v-btn
                  v-if="can('leave_type.update')"
                  icon="mdi-pencil-outline"
                  variant="text"
                  size="small"
                  @click="openModal(type)"
                />
                <v-btn
                  v-if="type.is_active && can('leave_type.archive')"
                  icon="mdi-archive-outline"
                  variant="text"
                  size="small"
                  color="warning"
                  @click="confirmArchive(type)"
                />
              </td>
            </tr>
          </tbody>
        </v-table>
      </v-card>

      <!-- Create / Edit Dialog -->
      <v-dialog v-model="dialog" max-width="500" persistent>
        <v-card class="pa-2 rounded-lg">
          <v-card-title class="font-weight-bold">
            {{ isEditing ? "Edit Leave Type" : "Add Leave Type" }}
          </v-card-title>
          <v-card-text>
            <v-row density="compact">
              <v-col cols="8">
                <v-text-field
                  v-model="form.name"
                  label="Name *"
                  variant="outlined"
                  density="compact"
                  :error-messages="form.errors.name"
                />
              </v-col>
              <v-col cols="4">
                <v-text-field
                  v-model="form.code"
                  label="Code *"
                  variant="outlined"
                  density="compact"
                  :error-messages="form.errors.code"
                />
              </v-col>
              <v-col cols="12">
                <v-textarea
                  v-model="form.description"
                  label="Description"
                  variant="outlined"
                  density="compact"
                  rows="2"
                />
              </v-col>
              <v-col cols="12">
                <v-switch
                  v-model="form.is_active"
                  label="Active Status"
                  color="primary"
                  hide-details
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

      <!-- Archive Confirmation Dialog -->
      <v-dialog v-model="deleteDialog" max-width="420" persistent>
        <v-card class="pa-2 rounded-lg">
          <v-card-title class="text-h6 font-weight-bold">
            Confirm Archive
          </v-card-title>
          <v-card-text>
            Are you sure you want to archive
            <strong>{{ itemToArchive?.name }}</strong
            >? This will disable it for new applications.
          </v-card-text>
          <v-card-actions class="justify-end ga-2">
            <v-btn variant="outlined" @click="deleteDialog = false">
              Cancel
            </v-btn>
            <v-btn color="warning" :loading="deleting" @click="archive">
              Archive
            </v-btn>
          </v-card-actions>
        </v-card>
      </v-dialog>
    </v-container>
  </SidebarLayout>
</template>

<script>
import { Head, router, useForm } from "@inertiajs/vue3";
import SidebarLayout from "@/layouts/SidebarLayout.vue";
import permissions from "@/mixins/permissions";

export default {
  components: { SidebarLayout, Head },
  mixins: [permissions],
  props: { leaveTypes: Object },
  data() {
    return {
      dialog: false,
      isEditing: false,
      selectedId: null,
      deleteDialog: false,
      deleting: false,
      itemToArchive: null,
      form: useForm({
        name: "",
        code: "",
        description: "",
        is_active: true,
      }),
    };
  },
  methods: {
    openModal(type = null) {
      this.form.reset();
      this.form.clearErrors();
      if (type) {
        this.isEditing = true;
        this.selectedId = type.id;
        this.form.name = type.name;
        this.form.code = type.code;
        this.form.description = type.description;
        this.form.is_active = type.is_active;
      } else {
        this.isEditing = false;
        this.selectedId = null;
      }
      this.dialog = true;
    },
    submit() {
      if (this.isEditing) {
        this.form.put(route("leave.config.types.update", this.selectedId), {
          onSuccess: () => (this.dialog = false),
        });
      } else {
        this.form.post(route("leave.config.types.store"), {
          onSuccess: () => (this.dialog = false),
        });
      }
    },
    confirmArchive(type) {
      this.itemToArchive = type;
      this.deleteDialog = true;
    },
    archive() {
      if (!this.itemToArchive) return;
      this.deleting = true;
      router.delete(
        route("leave.config.types.destroy", this.itemToArchive.id),
        {
          onFinish: () => {
            this.deleting = false;
            this.deleteDialog = false;
            this.itemToArchive = null;
          },
        }
      );
    },
  },
};
</script>