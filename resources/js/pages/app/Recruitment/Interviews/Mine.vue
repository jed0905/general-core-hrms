<template>
  <SidebarLayout>
    <Head title="My Interviews" />
    <v-container fluid class="pa-6">
      <div class="mb-4">
        <h1 class="text-h5 font-weight-bold">My Interviews</h1>
        <p class="text-body-2 text-medium-emphasis">Interviews where you sit on the panel. Open one to see the details and write your scorecard once it's completed.</p>
      </div>

      <v-card variant="outlined" class="rounded-lg mb-4 pa-4">
        <v-row density="compact" align="center">
          <v-col cols="12" md="6">
            <v-btn-toggle v-model="form.scope" mandatory color="primary" variant="outlined" density="compact" divided @update:model-value="apply">
              <v-btn value="upcoming">Upcoming</v-btn>
              <v-btn value="completed">Completed</v-btn>
              <v-btn value="cancelled">Cancelled</v-btn>
              <v-btn value="all">All</v-btn>
            </v-btn-toggle>
          </v-col>
          <v-col cols="6" md="3">
            <v-text-field v-model="form.from" type="date" label="From" variant="outlined" density="compact" hide-details clearable @update:model-value="apply" />
          </v-col>
          <v-col cols="6" md="3">
            <v-text-field v-model="form.to" type="date" label="To" variant="outlined" density="compact" hide-details clearable @update:model-value="apply" />
          </v-col>
        </v-row>
      </v-card>

      <v-card variant="outlined" class="rounded-lg">
        <v-table density="comfortable">
          <thead>
            <tr><th>When</th><th>Applicant</th><th>Vacancy</th><th>Interview</th><th>Status</th><th>My scorecard</th><th class="text-end"></th></tr>
          </thead>
          <tbody>
            <tr v-if="!interviews.data.length"><td colspan="7" class="text-center text-medium-emphasis pa-6">No interviews here.</td></tr>
            <tr v-for="i in interviews.data" :key="i.id">
              <td class="text-no-wrap">{{ interviewWhen(i) }}</td>
              <td>{{ applicantName(i.application?.applicant) }}<div class="text-caption text-medium-emphasis">{{ i.application?.application_number }}</div></td>
              <td>{{ i.application?.vacancy?.title }}</td>
              <td>{{ i.type?.name }}<div class="text-caption text-medium-emphasis">Round {{ i.round }} · {{ interviewModeLabel(i.mode) }}</div></td>
              <td><v-chip size="small" variant="tonal" :color="status(i.status).color">{{ status(i.status).label }}</v-chip></td>
              <td>
                <v-chip v-if="i.evaluations.length" size="small" variant="tonal" :color="i.evaluations[0].status === 'submitted' ? 'success' : 'warning'">
                  {{ i.evaluations[0].status === "submitted" ? "Submitted" : "Draft" }}
                </v-chip>
                <span v-else-if="i.status === 'completed'" class="text-warning text-body-2">To do</span>
                <span v-else class="text-medium-emphasis">—</span>
              </td>
              <td class="text-end"><v-btn icon="mdi-eye-outline" size="small" variant="text" @click="go(route('recruitment.interviews.show', i.id))" /></td>
            </tr>
          </tbody>
        </v-table>
        <div class="pa-3"><Pagination :meta="interviews" /></div>
      </v-card>
    </v-container>
  </SidebarLayout>
</template>

<script>
import { Head, router } from "@inertiajs/vue3";
import SidebarLayout from "@/layouts/SidebarLayout.vue";
import Pagination from "@/components/Pagination.vue";
import { INTERVIEW_STATUS, applicantName, interviewModeLabel, interviewWhen, statusInfo } from "@/utils/recruitment";

export default {
  name: "MyInterviews",
  components: { SidebarLayout, Head, Pagination },
  props: {
    interviews: { type: Object, required: true },
    filters: { type: Object, default: () => ({}) },
  },
  data() {
    return {
      form: {
        scope: this.filters.scope || "upcoming",
        from: this.filters.from || null,
        to: this.filters.to || null,
      },
    };
  },
  methods: {
    applicantName,
    interviewWhen,
    interviewModeLabel,
    status(value) {
      return statusInfo(INTERVIEW_STATUS, value);
    },
    apply() {
      router.get(route("recruitment.interviews.mine"), this.form, { preserveState: true, preserveScroll: true, replace: true });
    },
    go(url) {
      router.visit(url);
    },
  },
};
</script>
