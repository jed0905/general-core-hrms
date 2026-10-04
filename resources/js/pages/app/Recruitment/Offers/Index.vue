<template>
  <SidebarLayout>
    <Head title="Offers" />
    <v-container fluid class="pa-6">
      <div class="mb-4">
        <h1 class="text-h5 font-weight-bold">Offers</h1>
        <p class="text-body-2 text-medium-emphasis">Job offers to selected candidates, newest first. Offers are prepared from an application.</p>
      </div>

      <v-card variant="outlined" class="rounded-lg mb-4 pa-4">
        <v-row density="compact" align="center">
          <v-col cols="12" md="4">
            <v-text-field v-model="form.search" label="Search offer number or applicant" prepend-inner-icon="mdi-magnify" variant="outlined" density="compact" hide-details clearable @keyup.enter="apply" @click:clear="clearSearch" />
          </v-col>
          <v-col cols="12" md="4">
            <v-select v-model="form.status" :items="statusItems" label="Status" variant="outlined" density="compact" hide-details clearable @update:model-value="apply" />
          </v-col>
          <v-col cols="12" md="4">
            <v-checkbox v-model="form.awaiting" label="Awaiting my approval" density="compact" hide-details :true-value="1" :false-value="null" @update:model-value="apply" />
          </v-col>
        </v-row>
      </v-card>

      <v-card variant="outlined" class="rounded-lg">
        <v-table density="comfortable">
          <thead>
            <tr><th>Offer</th><th>Applicant</th><th>Vacancy</th><th>Position</th><th>Salary</th><th>Status</th><th>Expires</th><th class="text-end"></th></tr>
          </thead>
          <tbody>
            <tr v-if="!offers.data.length"><td colspan="8" class="text-center text-medium-emphasis pa-6">No offers found.</td></tr>
            <tr v-for="o in offers.data" :key="o.id">
              <td class="font-weight-medium text-no-wrap">{{ o.offer_number }}</td>
              <td>{{ applicantName(o.applicant) }}<div class="text-caption text-medium-emphasis">{{ o.applicant?.applicant_number }}</div></td>
              <td>{{ o.vacancy?.title }}<div class="text-caption text-medium-emphasis">{{ o.vacancy?.vacancy_number }}</div></td>
              <td>{{ o.position_title }}</td>
              <td class="text-no-wrap">{{ money(o.base_salary, o.currency) }} <span class="text-caption text-medium-emphasis">{{ frequencyLabel(o.salary_frequency) }}</span></td>
              <td><v-chip size="small" variant="tonal" :color="status(o.status).color">{{ status(o.status).label }}</v-chip></td>
              <td class="text-no-wrap">{{ formatDate(o.expiry_date) }}</td>
              <td class="text-end"><v-btn icon="mdi-eye-outline" size="small" variant="text" @click="go(route('recruitment.offers.show', o.id))" /></td>
            </tr>
          </tbody>
        </v-table>
        <div class="pa-3"><Pagination :meta="offers" /></div>
      </v-card>
    </v-container>
  </SidebarLayout>
</template>

<script>
import { Head, router } from "@inertiajs/vue3";
import SidebarLayout from "@/layouts/SidebarLayout.vue";
import Pagination from "@/components/Pagination.vue";
import { OFFER_STATUS, SALARY_FREQUENCIES, applicantName, formatDate, money, statusInfo } from "@/utils/recruitment";

export default {
  name: "OfferIndex",
  components: { SidebarLayout, Head, Pagination },
  props: {
    offers: { type: Object, required: true },
    filters: { type: Object, default: () => ({}) },
    statuses: { type: Array, default: () => [] },
  },
  data() {
    return {
      form: {
        search: this.filters.search || "",
        status: this.filters.status || null,
        awaiting: this.filters.awaiting ? 1 : null,
      },
    };
  },
  computed: {
    statusItems() {
      return this.statuses.map((s) => ({ value: s, title: statusInfo(OFFER_STATUS, s).label }));
    },
  },
  methods: {
    applicantName,
    formatDate,
    money,
    frequencyLabel(value) {
      return SALARY_FREQUENCIES[value] || value;
    },
    status(value) {
      return statusInfo(OFFER_STATUS, value);
    },
    apply() {
      router.get(route("recruitment.offers.index"), this.form, { preserveState: true, preserveScroll: true, replace: true });
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
