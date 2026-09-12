<template>
  <SidebarLayout>
    <Head title="Leave Policies" />

    <v-container fluid class="pa-6">
      <div class="d-flex align-center justify-space-between mb-6">
        <div>
          <h1 class="text-h5 font-weight-bold">Leave Policies</h1>
          <p class="text-body-2 text-medium-emphasis">
            Manage company leave policy versions and effective dates.
          </p>
        </div>

        <v-btn
          color="primary"
          prepend-icon="mdi-plus"
          elevation="0"
          @click="openModal()"
        >
          Add Leave Policy
        </v-btn>
      </div>

      <v-card variant="outlined" class="rounded-lg">
        <v-table hover>
          <thead>
            <tr>
              <th class="font-weight-bold">Policy Name</th>
              <th class="font-weight-bold">Effective From</th>
              <th class="font-weight-bold">Effective To</th>
              <th class="font-weight-bold">Rules Count</th>
              <th class="font-weight-bold">Status</th>
              <th class="text-end font-weight-bold">Actions</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="!policies.data.length">
              <td colspan="6" class="text-center py-6 text-medium-emphasis">
                No policies found.
              </td>
            </tr>

            <tr v-for="p in policies.data" :key="p.id">
              <td>
                <div class="font-weight-medium">{{ p.name }}</div>
                <div class="text-caption text-medium-emphasis">
                  {{ p.description || "N/A" }}
                </div>
              </td>
              <td>{{ p.effective_from }}</td>
              <td>{{ p.effective_to || "Indefinite" }}</td>
              <td>
                <v-chip size="small" variant="tonal"
                  >{{ p.rules_count }} Rule(s)</v-chip
                >
              </td>
              <td>
                <v-chip
                  size="small"
                  :color="p.is_active ? 'success' : 'error'"
                  variant="tonal"
                >
                  {{ p.is_active ? "Active" : "Archived" }}
                </v-chip>
              </td>
              <td class="text-end">
                <v-btn
                  icon="mdi-pencil-outline"
                  variant="text"
                  size="small"
                  @click="openModal(p)"
                />
                <v-btn
                  v-if="p.is_active"
                  icon="mdi-archive-outline"
                  variant="text"
                  size="small"
                  color="warning"
                  @click="archive(p.id)"
                />
              </td>
            </tr>
          </tbody>
        </v-table>
      </v-card>

      <!-- Modal -->
      <v-dialog v-model="dialog" max-width="500" persistent>
        <v-card class="pa-2 rounded-lg">
          <v-card-title class="font-weight-bold">{{
            isEditing ? "Edit Policy" : "Add Policy"
          }}</v-card-title>
          <v-card-text>
            <v-text-field
              v-model="form.name"
              label="Policy Name *"
              variant="outlined"
              density="compact"
              :error-messages="form.errors.name"
            />
            <v-textarea
              v-model="form.description"
              label="Description"
              variant="outlined"
              density="compact"
              rows="2"
            />
            <v-text-field
              v-model="form.effective_from"
              type="date"
              label="Effective From *"
              variant="outlined"
              density="compact"
              :error-messages="form.errors.effective_from"
            />
            <v-text-field
              v-model="form.effective_to"
              type="date"
              label="Effective To"
              variant="outlined"
              density="compact"
              :error-messages="form.errors.effective_to"
            />
            <v-switch
              v-model="form.is_active"
              label="Active Policy"
              color="primary"
              hide-details
            />
          </v-card-text>
          <v-card-actions class="justify-end ga-2">
            <v-btn variant="outlined" @click="dialog = false">Cancel</v-btn>
            <v-btn color="primary" :loading="form.processing" @click="submit"
              >Save</v-btn
            >
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
  props: { policies: Object },
  data() {
    return {
      dialog: false,
      isEditing: false,
      selectedId: null,
      form: useForm({
        name: "",
        description: "",
        effective_from: "",
        effective_to: null,
        is_active: true,
      }),
    };
  },
  methods: {
    openModal(p = null) {
      this.form.reset();
      this.form.clearErrors();
      if (p) {
        this.isEditing = true;
        this.selectedId = p.id;
        Object.assign(this.form, p);
      } else {
        this.isEditing = false;
        this.selectedId = null;
      }
      this.dialog = true;
    },
    submit() {
      if (this.isEditing) {
        this.form.put(route("leave.config.policies.update", this.selectedId), {
          onSuccess: () => (this.dialog = false),
        });
      } else {
        this.form.post(route("leave.config.policies.store"), {
          onSuccess: () => (this.dialog = false),
        });
      }
    },
    archive(id) {
      if (confirm("Archive this policy?")) {
        router.delete(route("leave.config.policies.destroy", id));
      }
    },
  },
};
</script>