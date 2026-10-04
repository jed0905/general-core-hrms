<template>
  <CareersLayout>
    <Head :title="`Careers · ${company}`" />
    <section class="mb-6">
      <h1 class="text-h4 font-weight-bold mb-2">{{ settings.headline || "Careers" }}</h1>
      <p v-if="settings.introduction" class="text-body-1 text-medium-emphasis" style="white-space: pre-line">{{ settings.introduction }}</p>
    </section>

    <v-card variant="outlined" class="rounded-lg pa-4 mb-6" role="search">
      <v-row density="compact">
        <v-col cols="12" md="5">
          <v-text-field v-model="form.search" label="Search jobs" prepend-inner-icon="mdi-magnify" variant="outlined" density="compact" hide-details clearable @keyup.enter="apply" @click:clear="clear" />
        </v-col>
        <v-col cols="12" sm="4" md="2"><v-select v-model="form.department" :items="options.departments" label="Department" variant="outlined" density="compact" hide-details clearable @update:model-value="apply" /></v-col>
        <v-col cols="12" sm="4" md="2"><v-select v-model="form.employment_type" :items="options.employment_types" label="Employment type" variant="outlined" density="compact" hide-details clearable @update:model-value="apply" /></v-col>
        <v-col cols="12" sm="4" md="2"><v-select v-model="form.location" :items="options.locations" label="Location" variant="outlined" density="compact" hide-details clearable @update:model-value="apply" /></v-col>
        <v-col cols="12" md="1" class="d-flex align-center"><v-btn color="primary" block @click="apply">Search</v-btn></v-col>
      </v-row>
    </v-card>

    <p class="text-body-2 text-medium-emphasis mb-3" aria-live="polite">{{ jobs.total }} open position{{ jobs.total === 1 ? "" : "s" }}</p>

    <v-alert v-if="!jobs.data.length" type="info" variant="tonal">No open positions match your search right now. Please check back later.</v-alert>

    <v-row>
      <v-col v-for="job in jobs.data" :key="job.slug" cols="12" md="6">
        <v-card variant="outlined" class="rounded-lg h-100 d-flex flex-column">
          <v-card-item>
            <v-card-title class="text-h6 text-wrap">
              <a :href="route('careers.jobs.show', job.slug)" class="text-decoration-none text-high-emphasis" @click.prevent="open(job)">{{ job.title }}</a>
            </v-card-title>
            <v-card-subtitle class="d-flex flex-wrap ga-3 mt-1">
              <span v-if="job.department"><v-icon icon="mdi-domain" size="small" class="mr-1" />{{ job.department }}</span>
              <span v-if="job.employment_type"><v-icon icon="mdi-briefcase-outline" size="small" class="mr-1" />{{ job.employment_type }}</span>
              <span v-if="job.location"><v-icon icon="mdi-map-marker-outline" size="small" class="mr-1" />{{ job.location }}</span>
            </v-card-subtitle>
          </v-card-item>
          <v-spacer />
          <v-card-actions class="px-4 pb-4">
            <span v-if="job.closing_date" class="text-body-2 text-medium-emphasis">Applications close {{ formatDate(job.closing_date) }}</span>
            <v-spacer />
            <v-btn color="primary" variant="tonal" @click="open(job)">View job</v-btn>
          </v-card-actions>
        </v-card>
      </v-col>
    </v-row>

    <div v-if="jobs.last_page > 1" class="d-flex justify-center mt-6">
      <v-pagination :model-value="jobs.current_page" :length="jobs.last_page" :total-visible="7" @update:model-value="page" />
    </div>
  </CareersLayout>
</template>

<script>
import { Head, router } from "@inertiajs/vue3";
import CareersLayout from "@/layouts/CareersLayout.vue";
import { formatDate } from "@/utils/careers";

export default {
  name: "CareersIndex",
  components: { CareersLayout, Head },
  props: {
    jobs: { type: Object, required: true },
    filters: { type: Object, default: () => ({}) },
    options: { type: Object, default: () => ({ departments: [], employment_types: [], locations: [] }) },
  },
  data() {
    const f = this.filters;
    return {
      form: {
        search: f.search || "",
        department: f.department ? Number(f.department) : null,
        employment_type: f.employment_type ? Number(f.employment_type) : null,
        location: f.location ? Number(f.location) : null,
      },
    };
  },
  computed: {
    settings() {
      return this.$page.props.portal?.settings || {};
    },
    company() {
      return this.$page.props.portal?.company?.name || "Careers";
    },
  },
  methods: {
    formatDate,
    apply() {
      router.get(route("careers.index"), this.form, { preserveState: true, preserveScroll: true, replace: true });
    },
    page(n) {
      router.get(route("careers.index"), { ...this.form, page: n }, { preserveState: true });
    },
    clear() {
      this.form.search = "";
      this.apply();
    },
    open(job) {
      router.visit(route("careers.jobs.show", job.slug));
    },
  },
};
</script>
