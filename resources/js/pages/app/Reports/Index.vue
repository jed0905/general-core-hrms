<template>
  <SidebarLayout>
    <Head title="Reports" />

    <v-container fluid class="pa-4 pa-sm-6">
      <div class="mb-6">
        <h1 class="text-h5 font-weight-bold">Reports</h1>
        <p class="text-body-2 text-medium-emphasis">
          Core HR reports. You see the categories your permissions allow.
        </p>
      </div>

      <v-alert v-if="!categories.length" type="info" variant="tonal">
        You don't have access to any report category.
      </v-alert>

      <div v-for="category in categories" :key="category.key" class="mb-6">
        <h2 class="text-subtitle-1 font-weight-bold mb-3">{{ category.title }}</h2>
        <v-row>
          <v-col v-for="report in category.reports" :key="report.key" cols="12" sm="6" lg="4">
            <v-card
              variant="outlined"
              class="rounded-lg h-100"
              link
              @click="open(report.key)"
            >
              <v-card-title class="text-body-1 font-weight-bold">{{ report.title }}</v-card-title>
              <v-card-text class="text-body-2 text-medium-emphasis">{{ report.description }}</v-card-text>
            </v-card>
          </v-col>
        </v-row>
      </div>
    </v-container>
  </SidebarLayout>
</template>

<script>
import { Head, router } from "@inertiajs/vue3";
import SidebarLayout from "@/layouts/SidebarLayout.vue";

export default {
  name: "ReportsIndex",
  components: { SidebarLayout, Head },
  props: {
    categories: { type: Array, default: () => [] },
  },
  methods: {
    open(key) {
      router.visit(route(`reports.${key}.show`));
    },
  },
};
</script>
