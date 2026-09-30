<template>
  <SidebarLayout>
    <Head title="My Employment History" />

    <v-container fluid class="pa-4 pa-sm-6">
      <div class="mb-6">
        <h1 class="text-h5 font-weight-bold">My Employment History</h1>
        <p class="text-body-2 text-medium-emphasis">
          Promotions, transfers and other changes to your employment, newest first.
        </p>
      </div>

      <v-alert v-if="!hasEmployeeRecord" type="info" variant="tonal">
        Your account is not linked to an employee record. Contact HR if this is
        unexpected.
      </v-alert>

      <v-alert v-else-if="!movements.length" type="info" variant="tonal">
        No employment changes have been recorded for you yet.
      </v-alert>

      <v-card v-else variant="outlined" class="rounded-lg pa-4">
        <v-timeline density="compact" side="end" truncate-line="both">
          <v-timeline-item
            v-for="m in movements"
            :key="m.id"
            dot-color="primary"
            size="small"
          >
            <div class="text-caption text-medium-emphasis">{{ formatDate(m.effective_date) }}</div>
            <div class="text-subtitle-1 font-weight-bold">{{ m.type?.name }}</div>
            <div v-for="c in changeSummary(m)" :key="c.label" class="text-body-2">
              <span class="text-medium-emphasis">{{ c.label }}:</span>
              {{ c.from || "—" }} → <strong>{{ c.to || "—" }}</strong>
            </div>
            <div v-if="!changeSummary(m).length" class="text-body-2">
              {{ [m.snapshot?.to?.job_title, m.snapshot?.to?.department].filter(Boolean).join(" · ") || "—" }}
            </div>
            <div v-if="m.reason" class="text-caption text-medium-emphasis mt-1">{{ m.reason }}</div>
          </v-timeline-item>
        </v-timeline>
      </v-card>
    </v-container>
  </SidebarLayout>
</template>

<script>
import { Head } from "@inertiajs/vue3";
import SidebarLayout from "@/layouts/SidebarLayout.vue";
import { changeSummary, formatDate } from "@/utils/employeeMovement";

export default {
  name: "MyEmploymentHistoryIndex",
  components: { SidebarLayout, Head },
  props: {
    hasEmployeeRecord: { type: Boolean, default: true },
    movements: { type: Array, default: () => [] },
  },
  methods: {
    changeSummary,
    formatDate,
  },
};
</script>
