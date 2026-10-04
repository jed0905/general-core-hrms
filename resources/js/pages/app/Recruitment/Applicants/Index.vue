<template>
  <SidebarLayout>
    <Head title="Applicants" />
    <v-container fluid class="pa-6">
      <div class="d-flex align-center justify-space-between mb-4">
        <div>
          <h1 class="text-h5 font-weight-bold">Applicants</h1>
          <p class="text-body-2 text-medium-emphasis">Candidates and their applications.</p>
        </div>
        <v-btn v-if="can.create" color="primary" prepend-icon="mdi-account-plus-outline" @click="go(route('recruitment.applicants.create'))">New applicant</v-btn>
      </div>

      <v-card variant="outlined" class="rounded-lg mb-4 pa-4">
        <v-row density="compact">
          <v-col cols="12" md="5">
            <v-text-field v-model="form.search" label="Search name, number, email or phone" prepend-inner-icon="mdi-magnify" variant="outlined" density="compact" hide-details clearable @keyup.enter="apply" @click:clear="clearSearch" />
          </v-col>
          <v-col cols="12" md="4">
            <v-select v-model="form.source_id" :items="sources" item-title="name" item-value="id" label="Source" variant="outlined" density="compact" hide-details clearable @update:model-value="apply" />
          </v-col>
          <v-col cols="12" md="3">
            <v-select v-model="form.status" :items="statusItems" label="Status" variant="outlined" density="compact" hide-details clearable @update:model-value="apply" />
          </v-col>
        </v-row>
      </v-card>

      <v-card variant="outlined" class="rounded-lg">
        <v-table density="comfortable">
          <thead>
            <tr>
              <th>Number</th>
              <th>Name</th>
              <th>Contact</th>
              <th>Source</th>
              <th class="text-center">Applications</th>
              <th>Status</th>
              <th class="text-end">Profile</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="!applicants.data.length">
              <td colspan="7" class="text-center text-medium-emphasis pa-6">No applicants found.</td>
            </tr>
            <tr v-for="a in applicants.data" :key="a.id">
              <td class="font-weight-medium text-no-wrap">{{ a.applicant_number }}</td>
              <td>
                {{ a.full_name }}
                <v-chip v-if="a.is_internal" size="x-small" color="primary" variant="tonal" class="ml-1">Internal</v-chip>
              </td>
              <td>
                <div>{{ a.email || "—" }}</div>
                <div class="text-caption text-medium-emphasis">{{ a.phone }}</div>
              </td>
              <td>{{ a.source?.name || "—" }}</td>
              <td class="text-center">{{ a.applications_count }}</td>
              <td><v-chip size="small" variant="tonal" :color="a.status === 'active' ? 'success' : 'grey'">{{ a.status === "active" ? "Active" : "Archived" }}</v-chip></td>
              <td class="text-end"><v-btn icon="mdi-eye-outline" size="small" variant="text" @click="go(route('recruitment.applicants.show', a.id))" /></td>
            </tr>
          </tbody>
        </v-table>
        <div class="pa-3"><Pagination :meta="applicants" /></div>
      </v-card>
    </v-container>
  </SidebarLayout>
</template>

<script>
import { Head, router } from "@inertiajs/vue3";
import SidebarLayout from "@/layouts/SidebarLayout.vue";
import Pagination from "@/components/Pagination.vue";

export default {
  name: "ApplicantIndex",
  components: { SidebarLayout, Head, Pagination },
  props: {
    applicants: { type: Object, required: true },
    filters: { type: Object, default: () => ({}) },
    sources: { type: Array, default: () => [] },
    can: { type: Object, default: () => ({}) },
  },
  data() {
    return {
      form: {
        search: this.filters.search || "",
        source_id: this.filters.source_id ? Number(this.filters.source_id) : null,
        status: this.filters.status || null,
      },
      statusItems: [
        { value: "active", title: "Active" },
        { value: "archived", title: "Archived" },
      ],
    };
  },
  methods: {
    apply() {
      router.get(route("recruitment.applicants.index"), this.form, { preserveState: true, preserveScroll: true, replace: true });
    },
    clearSearch() {
      this.form.search = "";
      this.apply();
    },
    go(url) {
      router.visit(url);
    },
  },
};
</script>
