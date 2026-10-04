<template>
  <CareersLayout>
    <Head title="My Applications" />
    <h1 class="text-h5 font-weight-bold mb-4">My Applications</h1>

    <v-alert v-if="!applications.length" type="info" variant="tonal">
      You haven't applied for any position yet.
      <v-btn variant="text" color="primary" class="ml-2" @click="go(route('careers.index'))">Browse jobs</v-btn>
    </v-alert>

    <v-card v-for="a in applications" :key="a.number" variant="outlined" class="rounded-lg mb-3">
      <v-card-item>
        <v-card-title class="text-wrap">{{ a.job_title }}</v-card-title>
        <v-card-subtitle>Application {{ a.number }} · submitted {{ formatDate(a.submitted_on) }}</v-card-subtitle>
        <template #append>
          <v-chip :color="color(a.status.key)" variant="tonal" size="small">{{ a.status.label }}</v-chip>
        </template>
      </v-card-item>
      <v-card-text class="pt-0">{{ a.status.description }}</v-card-text>
      <v-card-actions class="px-4 pb-3">
        <v-spacer />
        <v-btn variant="tonal" @click="go(route('careers.applications.show', a.number))">View</v-btn>
      </v-card-actions>
    </v-card>
  </CareersLayout>
</template>

<script>
import { Head, router } from "@inertiajs/vue3";
import CareersLayout from "@/layouts/CareersLayout.vue";
import { formatDate, STATUS_COLORS } from "@/utils/careers";

export default {
  name: "CareersMyApplications",
  components: { CareersLayout, Head },
  props: { applications: { type: Array, default: () => [] } },
  methods: {
    formatDate,
    color(key) {
      return STATUS_COLORS[key] || "grey";
    },
    go(url) {
      router.visit(url);
    },
  },
};
</script>
