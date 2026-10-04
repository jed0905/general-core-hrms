<template>
  <SidebarLayout>
    <Head title="Onboarding Templates" />
    <v-container fluid class="pa-6" style="max-width: 1100px">
      <div class="d-flex flex-wrap align-center justify-space-between ga-3 mb-4">
        <div>
          <h1 class="text-h5 font-weight-bold">Onboarding Templates</h1>
          <p class="text-body-2 text-medium-emphasis">Reusable checklists. Editing a template never changes onboardings already started.</p>
        </div>
        <v-btn v-if="can.create" color="primary" prepend-icon="mdi-plus" @click="go(route('people.onboarding-templates.create'))">New template</v-btn>
      </div>

      <v-card variant="outlined" class="rounded-lg mb-4 pa-4">
        <v-row density="compact">
          <v-col cols="12" md="8"><v-text-field v-model="form.search" label="Search" prepend-inner-icon="mdi-magnify" variant="outlined" density="compact" hide-details clearable @keyup.enter="apply" @click:clear="form.search = ''; apply()" /></v-col>
          <v-col cols="12" md="4"><v-select v-model="form.active" :items="activeItems" label="Status" variant="outlined" density="compact" hide-details clearable @update:model-value="apply" /></v-col>
        </v-row>
      </v-card>

      <v-card variant="outlined" class="rounded-lg">
        <v-table density="comfortable">
          <thead><tr><th>Template</th><th>Suggested for</th><th>Tasks</th><th>Status</th><th class="text-end"></th></tr></thead>
          <tbody>
            <tr v-if="!templates.data.length"><td colspan="5" class="text-center text-medium-emphasis pa-6">No templates yet.</td></tr>
            <tr v-for="t in templates.data" :key="t.id">
              <td class="font-weight-medium">{{ t.name }}<div v-if="t.description" class="text-caption text-medium-emphasis">{{ t.description }}</div></td>
              <td>{{ t.employment_status?.name || "Any" }}</td>
              <td>{{ t.active_tasks_count }} active<span v-if="t.tasks_count !== t.active_tasks_count" class="text-medium-emphasis"> / {{ t.tasks_count }}</span></td>
              <td><v-chip size="small" variant="tonal" :color="t.is_active ? 'success' : 'grey'">{{ t.is_active ? "Active" : "Inactive" }}</v-chip></td>
              <td class="text-end"><v-btn v-if="can.update" icon="mdi-pencil-outline" size="small" variant="text" @click="go(route('people.onboarding-templates.edit', t.id))" /></td>
            </tr>
          </tbody>
        </v-table>
        <div class="pa-3"><Pagination :meta="templates" /></div>
      </v-card>
    </v-container>
  </SidebarLayout>
</template>

<script>
import { Head, router } from "@inertiajs/vue3";
import SidebarLayout from "@/layouts/SidebarLayout.vue";
import Pagination from "@/components/Pagination.vue";

export default {
  name: "OnboardingTemplateIndex",
  components: { SidebarLayout, Head, Pagination },
  props: {
    templates: { type: Object, required: true },
    filters: { type: Object, default: () => ({}) },
    can: { type: Object, default: () => ({}) },
  },
  data() {
    return {
      form: { search: this.filters.search || "", active: this.filters.active ?? null },
      activeItems: [{ value: "1", title: "Active" }, { value: "0", title: "Inactive" }],
    };
  },
  methods: {
    apply() {
      router.get(route("people.onboarding-templates.index"), this.form, { preserveState: true, preserveScroll: true, replace: true });
    },
    go(url) {
      router.visit(url);
    },
  },
};
</script>
