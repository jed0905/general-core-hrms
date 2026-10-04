<template>
  <SidebarLayout>
    <Head title="Applications" />
    <v-container fluid class="pa-6">
      <div class="mb-4">
        <h1 class="text-h5 font-weight-bold">Applications</h1>
        <p class="text-body-2 text-medium-emphasis">Every application across your vacancies, newest activity first.</p>
      </div>

      <v-card variant="outlined" class="rounded-lg mb-4 pa-4">
        <v-row density="compact">
          <v-col cols="12" md="3">
            <v-text-field v-model="form.search" label="Search number or applicant" prepend-inner-icon="mdi-magnify" variant="outlined" density="compact" hide-details clearable @keyup.enter="apply" @click:clear="clearSearch" />
          </v-col>
          <v-col cols="12" md="4">
            <v-autocomplete v-model="form.vacancy_id" :items="vacancyItems" label="Vacancy" variant="outlined" density="compact" hide-details clearable @update:model-value="apply" />
          </v-col>
          <v-col cols="6" md="2">
            <v-select v-model="form.stage_type" :items="stageItems" label="Stage" variant="outlined" density="compact" hide-details clearable @update:model-value="apply" />
          </v-col>
          <v-col cols="6" md="3">
            <v-select v-model="form.status" :items="statusItems" label="Status" variant="outlined" density="compact" hide-details clearable @update:model-value="apply" />
          </v-col>
        </v-row>
      </v-card>

      <v-card variant="outlined" class="rounded-lg">
        <v-table density="comfortable">
          <thead>
            <tr><th>Application</th><th>Applicant</th><th>Vacancy</th><th>Stage</th><th>Status</th><th>Applied</th><th>Last activity</th><th class="text-end">Details</th></tr>
          </thead>
          <tbody>
            <tr v-if="!applications.data.length"><td colspan="8" class="text-center text-medium-emphasis pa-6">No applications found.</td></tr>
            <tr v-for="a in applications.data" :key="a.id">
              <td class="font-weight-medium text-no-wrap">{{ a.application_number }}</td>
              <td>{{ applicantName(a.applicant) }}<div class="text-caption text-medium-emphasis">{{ a.applicant?.applicant_number }}</div></td>
              <td>{{ a.vacancy?.title }}<div class="text-caption text-medium-emphasis">{{ a.vacancy?.vacancy_number }}</div></td>
              <td>{{ a.current_stage?.name }}</td>
              <td><v-chip size="small" variant="tonal" :color="status(a.status).color">{{ status(a.status).label }}</v-chip></td>
              <td class="text-no-wrap">{{ formatDateTime(a.applied_at) }}</td>
              <td class="text-no-wrap">{{ formatDateTime(a.last_activity_at) }}</td>
              <td class="text-end"><v-btn icon="mdi-eye-outline" size="small" variant="text" @click="go(route('recruitment.applications.show', a.id))" /></td>
            </tr>
          </tbody>
        </v-table>
        <div class="pa-3"><Pagination :meta="applications" /></div>
      </v-card>
    </v-container>
  </SidebarLayout>
</template>

<script>
import { Head, router } from "@inertiajs/vue3";
import SidebarLayout from "@/layouts/SidebarLayout.vue";
import Pagination from "@/components/Pagination.vue";
import { APPLICATION_STATUS, STAGE_TYPE_LABELS, statusInfo, applicantName, formatDateTime } from "@/utils/recruitment";

export default {
  name: "ApplicationIndex",
  components: { SidebarLayout, Head, Pagination },
  props: {
    applications: { type: Object, required: true },
    filters: { type: Object, default: () => ({}) },
    vacancies: { type: Array, default: () => [] },
    statuses: { type: Array, default: () => [] },
    stageTypes: { type: Array, default: () => [] },
  },
  data() {
    return {
      form: {
        search: this.filters.search || "",
        vacancy_id: this.filters.vacancy_id ? Number(this.filters.vacancy_id) : null,
        stage_type: this.filters.stage_type || null,
        status: this.filters.status || null,
      },
    };
  },
  computed: {
    vacancyItems() {
      return this.vacancies.map((v) => ({ value: v.id, title: `${v.title} (${v.vacancy_number})` }));
    },
    statusItems() {
      return this.statuses.map((s) => ({ value: s, title: statusInfo(APPLICATION_STATUS, s).label }));
    },
    stageItems() {
      return this.stageTypes.map((t) => ({ value: t, title: STAGE_TYPE_LABELS[t] || t }));
    },
  },
  methods: {
    applicantName,
    formatDateTime,
    status(value) {
      return statusInfo(APPLICATION_STATUS, value);
    },
    apply() {
      router.get(route("recruitment.applications.index"), this.form, { preserveState: true, preserveScroll: true, replace: true });
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
