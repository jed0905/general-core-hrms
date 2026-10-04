<template>
  <SidebarLayout>
    <Head title="Vacancies" />
    <v-container fluid class="pa-6">
      <div class="d-flex align-center justify-space-between mb-4">
        <div>
          <h1 class="text-h5 font-weight-bold">Vacancies</h1>
          <p class="text-body-2 text-medium-emphasis">Hiring opportunities and their openings.</p>
        </div>
        <v-btn v-if="can.create" color="primary" prepend-icon="mdi-plus" @click="go(route('recruitment.vacancies.create'))">New vacancy</v-btn>
      </div>

      <v-card variant="outlined" class="rounded-lg mb-4 pa-4">
        <v-row density="compact">
          <v-col cols="12" md="4">
            <v-text-field v-model="form.search" label="Search number or title" prepend-inner-icon="mdi-magnify" variant="outlined" density="compact" hide-details clearable @keyup.enter="apply" @click:clear="clearSearch" />
          </v-col>
          <v-col cols="12" md="3">
            <v-select v-model="form.status" :items="statusItems" label="Status" variant="outlined" density="compact" hide-details clearable @update:model-value="apply" />
          </v-col>
          <v-col cols="12" md="5">
            <v-autocomplete v-model="form.department_id" :items="departments" item-title="title" item-value="value" label="Department" variant="outlined" density="compact" hide-details clearable @update:model-value="apply" />
          </v-col>
        </v-row>
      </v-card>

      <v-card variant="outlined" class="rounded-lg">
        <v-table density="comfortable">
          <thead>
            <tr>
              <th>Number</th>
              <th>Title</th>
              <th>Department</th>
              <th class="text-center">Openings</th>
              <th>Hiring manager</th>
              <th>Closing</th>
              <th>Status</th>
              <th class="text-end">Details</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="!vacancies.data.length">
              <td colspan="8" class="text-center text-medium-emphasis pa-6">No vacancies found.</td>
            </tr>
            <tr v-for="v in vacancies.data" :key="v.id">
              <td class="font-weight-medium text-no-wrap">{{ v.vacancy_number }}</td>
              <td>
                {{ v.title }}
                <div v-if="v.requisition" class="text-caption text-medium-emphasis">From {{ v.requisition.requisition_number }}</div>
              </td>
              <td>{{ v.department?.name }}</td>
              <td class="text-center">{{ v.filled_count }} / {{ v.openings }}</td>
              <td>{{ personName(v.hiring_manager) }}</td>
              <td class="text-no-wrap">{{ formatDate(v.closing_date) }}</td>
              <td><v-chip size="small" variant="tonal" :color="status(v.status).color">{{ status(v.status).label }}</v-chip></td>
              <td class="text-end">
                <v-btn icon="mdi-eye-outline" size="small" variant="text" @click="go(route('recruitment.vacancies.show', v.id))" />
              </td>
            </tr>
          </tbody>
        </v-table>
        <div class="pa-3"><Pagination :meta="vacancies" /></div>
      </v-card>
    </v-container>
  </SidebarLayout>
</template>

<script>
import { Head, router } from "@inertiajs/vue3";
import SidebarLayout from "@/layouts/SidebarLayout.vue";
import Pagination from "@/components/Pagination.vue";
import { VACANCY_STATUS, statusInfo, personName, formatDate } from "@/utils/recruitment";

export default {
  name: "VacancyIndex",
  components: { SidebarLayout, Head, Pagination },
  props: {
    vacancies: { type: Object, required: true },
    filters: { type: Object, default: () => ({}) },
    statuses: { type: Array, default: () => [] },
    departments: { type: Array, default: () => [] },
    can: { type: Object, default: () => ({}) },
  },
  data() {
    return {
      form: {
        search: this.filters.search || "",
        status: this.filters.status || null,
        department_id: this.filters.department_id ? Number(this.filters.department_id) : null,
      },
    };
  },
  computed: {
    statusItems() {
      return this.statuses.map((s) => ({ value: s, title: statusInfo(VACANCY_STATUS, s).label }));
    },
  },
  methods: {
    personName,
    formatDate,
    status(value) {
      return statusInfo(VACANCY_STATUS, value);
    },
    apply() {
      router.get(route("recruitment.vacancies.index"), this.form, { preserveState: true, preserveScroll: true, replace: true });
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
