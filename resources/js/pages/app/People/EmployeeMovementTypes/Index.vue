<template>
  <SidebarLayout>
    <Head title="Movement Types" />

    <v-container fluid class="pa-4 pa-sm-6">
      <div class="d-flex flex-column flex-sm-row align-sm-center justify-space-between ga-4 mb-6">
        <div>
          <h1 class="text-h5 font-weight-bold">Movement Types</h1>
          <p class="text-body-2 text-medium-emphasis">
            The kinds of employment changes HR can record. Codes are permanent;
            archive a type instead of deleting it.
          </p>
        </div>
        <v-btn
          v-if="can('employee_movement_type.create')"
          color="primary"
          elevation="0"
          prepend-icon="mdi-plus"
          @click="openModal()"
        >
          Add Movement Type
        </v-btn>
      </div>

      <v-card variant="outlined" class="rounded-lg">
        <v-table hover>
          <thead>
            <tr>
              <th class="font-weight-bold">Name</th>
              <th class="font-weight-bold">Code</th>
              <th class="font-weight-bold">Changes</th>
              <th class="font-weight-bold">Ends Employment</th>
              <th class="font-weight-bold">Used</th>
              <th class="font-weight-bold">Status</th>
              <th class="text-end font-weight-bold">Actions</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="!types.length">
              <td colspan="7" class="text-center py-6 text-medium-emphasis">No movement types.</td>
            </tr>
            <tr v-for="t in types" :key="t.id">
              <td>
                <div class="font-weight-medium">{{ t.name }}</div>
                <div class="text-caption text-medium-emphasis">{{ t.description || "" }}</div>
              </td>
              <td><code>{{ t.code }}</code></td>
              <td>
                <span v-if="!(t.affected_fields || []).length" class="text-medium-emphasis">Records only</span>
                <v-chip
                  v-for="f in t.affected_fields || []"
                  :key="f"
                  size="x-small"
                  variant="outlined"
                  class="mr-1 mb-1"
                >
                  {{ fieldLabels[f] || f }}
                </v-chip>
              </td>
              <td>{{ t.employee_status ? `Yes (${t.employee_status})` : "No" }}</td>
              <td>{{ t.movements_count }}</td>
              <td>
                <v-chip size="small" :color="t.is_active ? 'success' : 'grey'" variant="tonal">
                  {{ t.is_active ? "Active" : "Archived" }}
                </v-chip>
              </td>
              <td class="text-end text-no-wrap">
                <v-btn
                  v-if="can('employee_movement_type.update')"
                  icon="mdi-pencil-outline"
                  variant="text"
                  size="small"
                  @click="openModal(t)"
                />
                <v-btn
                  v-if="t.is_active && can('employee_movement_type.archive')"
                  icon="mdi-archive-outline"
                  variant="text"
                  size="small"
                  color="warning"
                  @click="confirmArchive(t)"
                />
              </td>
            </tr>
          </tbody>
        </v-table>
      </v-card>

      <v-dialog v-model="dialog" max-width="600" persistent>
        <v-card class="pa-2 rounded-lg">
          <v-card-title class="font-weight-bold">
            {{ editingId ? "Edit Movement Type" : "Add Movement Type" }}
          </v-card-title>
          <v-card-text>
            <v-row density="compact">
              <v-col cols="12" sm="7">
                <v-text-field v-model="form.name" label="Name *" variant="outlined" density="compact" :error-messages="form.errors.name" />
              </v-col>
              <v-col cols="12" sm="5">
                <v-text-field
                  v-model="form.code"
                  label="Code *"
                  variant="outlined"
                  density="compact"
                  :disabled="!!editingId"
                  :hint="editingId ? 'Codes cannot be changed' : 'e.g. lateral_transfer'"
                  persistent-hint
                  :error-messages="form.errors.code"
                />
              </v-col>
              <v-col cols="12">
                <v-textarea v-model="form.description" label="Description" rows="2" variant="outlined" density="compact" :error-messages="form.errors.description" />
              </v-col>
              <v-col cols="12">
                <v-select
                  v-model="form.affected_fields"
                  :items="fieldOptions"
                  label="Fields this movement changes"
                  multiple
                  chips
                  closable-chips
                  variant="outlined"
                  density="compact"
                  hint="Leave empty for record-only movements such as Hiring."
                  persistent-hint
                  :error-messages="form.errors.affected_fields"
                />
              </v-col>
              <v-col cols="12" sm="7">
                <v-select
                  v-model="form.employee_status"
                  :items="statusOptions"
                  label="Sets record status"
                  variant="outlined"
                  density="compact"
                  hint="For separations, e.g. terminated."
                  persistent-hint
                  :error-messages="form.errors.employee_status"
                />
              </v-col>
              <v-col cols="12" sm="5">
                <v-switch v-model="form.is_active" label="Active" color="primary" density="compact" hide-details />
              </v-col>
            </v-row>
          </v-card-text>
          <v-card-actions class="justify-end ga-2">
            <v-btn variant="outlined" @click="dialog = false">Cancel</v-btn>
            <v-btn color="primary" variant="flat" :loading="form.processing" @click="submit">Save</v-btn>
          </v-card-actions>
        </v-card>
      </v-dialog>

      <v-dialog v-model="archiveDialog" max-width="420">
        <v-card class="pa-2 rounded-lg">
          <v-card-title class="font-weight-bold">Archive movement type?</v-card-title>
          <v-card-text>
            <strong>{{ itemToArchive?.name }}</strong> can no longer be used for
            new movements. Recorded movements keep it.
          </v-card-text>
          <v-card-actions class="justify-end ga-2">
            <v-btn variant="outlined" @click="archiveDialog = false">Cancel</v-btn>
            <v-btn color="warning" :loading="archiving" @click="archive">Archive</v-btn>
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
import { FIELD_LABELS } from "@/utils/employeeMovement";

export default {
  name: "EmployeeMovementTypesIndex",
  components: { SidebarLayout, Head },
  mixins: [permissions],
  props: {
    types: { type: Array, default: () => [] },
    fields: { type: Array, default: () => [] },
    employeeStatuses: { type: Array, default: () => [] },
  },
  data() {
    return {
      fieldLabels: FIELD_LABELS,
      dialog: false,
      editingId: null,
      archiveDialog: false,
      archiving: false,
      itemToArchive: null,
      form: useForm({
        code: "",
        name: "",
        description: "",
        affected_fields: [],
        employee_status: null,
        is_active: true,
      }),
    };
  },
  computed: {
    fieldOptions() {
      return this.fields.map((f) => ({ value: f, title: FIELD_LABELS[f] || f }));
    },
    statusOptions() {
      return [{ value: null, title: "Unchanged" }, ...this.employeeStatuses.map((s) => ({ value: s, title: s }))];
    },
  },
  methods: {
    openModal(type = null) {
      this.form.reset();
      this.form.clearErrors();
      this.editingId = type?.id ?? null;
      if (type) {
        this.form.code = type.code;
        this.form.name = type.name;
        this.form.description = type.description ?? "";
        this.form.affected_fields = [...(type.affected_fields || [])];
        this.form.employee_status = type.employee_status;
        this.form.is_active = type.is_active;
      }
      this.dialog = true;
    },
    submit() {
      const options = { preserveScroll: true, onSuccess: () => (this.dialog = false) };
      if (this.editingId) {
        this.form
          .transform(({ code, ...data }) => data)
          .put(route("people.employee-movement-types.update", { employeeMovementType: this.editingId }), options);
      } else {
        this.form.transform((data) => data).post(route("people.employee-movement-types.store"), options);
      }
    },
    confirmArchive(type) {
      this.itemToArchive = type;
      this.archiveDialog = true;
    },
    archive() {
      this.archiving = true;
      router.delete(route("people.employee-movement-types.destroy", { employeeMovementType: this.itemToArchive.id }), {
        preserveScroll: true,
        onFinish: () => {
          this.archiving = false;
          this.archiveDialog = false;
          this.itemToArchive = null;
        },
      });
    },
  },
};
</script>
