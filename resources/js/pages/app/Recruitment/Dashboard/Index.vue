<template>
  <SidebarLayout>
    <Head title="Talent & Recruitment" />
    <v-container fluid class="pa-6">
      <div class="mb-4">
        <h1 class="text-h5 font-weight-bold">Talent &amp; Recruitment</h1>
        <p class="text-body-2 text-medium-emphasis">Workforce requests and open vacancies.</p>
      </div>

      <v-row>
        <v-col v-if="access.requisitions" cols="12" sm="6" lg="3">
          <v-card variant="outlined" class="rounded-lg pa-4" @click="go(route('recruitment.requisitions.index', { status: 'pending_approval' }))">
            <div class="text-caption text-medium-emphasis">Requisitions awaiting approval</div>
            <div class="text-h4 font-weight-bold">{{ requisitionCounts.pending_approval }}</div>
          </v-card>
        </v-col>
        <v-col v-if="access.requisitions" cols="12" sm="6" lg="3">
          <v-card variant="outlined" class="rounded-lg pa-4" @click="go(route('recruitment.requisitions.index', { status: 'approved' }))">
            <div class="text-caption text-medium-emphasis">Approved requisitions</div>
            <div class="text-h4 font-weight-bold">{{ requisitionCounts.approved }}</div>
          </v-card>
        </v-col>
        <v-col v-if="access.vacancies" cols="12" sm="6" lg="3">
          <v-card variant="outlined" class="rounded-lg pa-4" @click="go(route('recruitment.vacancies.index', { status: 'open' }))">
            <div class="text-caption text-medium-emphasis">Open vacancies</div>
            <div class="text-h4 font-weight-bold">{{ vacancyCounts.open }}</div>
          </v-card>
        </v-col>
        <v-col v-if="access.vacancies" cols="12" sm="6" lg="3">
          <v-card variant="outlined" class="rounded-lg pa-4">
            <div class="text-caption text-medium-emphasis">Unfilled openings (open and on hold)</div>
            <div class="text-h4 font-weight-bold text-primary">{{ remainingOpenings }}</div>
          </v-card>
        </v-col>
      </v-row>

      <v-row>
        <v-col v-if="awaitingMyApproval.length" cols="12" lg="6">
          <v-card variant="outlined" class="rounded-lg">
            <v-card-title class="text-subtitle-1 font-weight-bold border-b">Awaiting your approval</v-card-title>
            <v-list density="compact">
              <v-list-item
                v-for="r in awaitingMyApproval"
                :key="r.id"
                :title="`${r.requisition_number} · ${r.job_title?.job_title}`"
                :subtitle="`${r.department?.name} · ${r.positions} position(s) · submitted ${formatDateTime(r.submitted_at)}`"
                @click="go(route('recruitment.requisitions.show', r.id))"
              />
            </v-list>
          </v-card>
        </v-col>

        <v-col v-if="offersAwaitingMyApproval.length" cols="12" lg="6">
          <v-card variant="outlined" class="rounded-lg">
            <v-card-title class="text-subtitle-1 font-weight-bold border-b">Offers awaiting your approval</v-card-title>
            <v-list density="compact">
              <v-list-item
                v-for="o in offersAwaitingMyApproval"
                :key="o.id"
                :title="`${o.offer_number} · ${applicantName(o.applicant)}`"
                :subtitle="`${o.position_title} · ${o.vacancy?.title} · submitted ${formatDateTime(o.submitted_at)}`"
                @click="go(route('recruitment.offers.show', o.id))"
              />
            </v-list>
          </v-card>
        </v-col>

        <v-col v-if="access.vacancies" cols="12" :lg="awaitingMyApproval.length ? 6 : 12">
          <v-card variant="outlined" class="rounded-lg">
            <v-card-title class="text-subtitle-1 font-weight-bold border-b">Active vacancies</v-card-title>
            <v-list v-if="recentVacancies.length" density="compact">
              <v-list-item
                v-for="v in recentVacancies"
                :key="v.id"
                :title="`${v.vacancy_number} · ${v.title}`"
                :subtitle="`${v.department?.name} · ${v.filled_count}/${v.openings} filled · closes ${formatDate(v.closing_date)}`"
                @click="go(route('recruitment.vacancies.show', v.id))"
              >
                <template #append>
                  <v-chip size="x-small" variant="tonal" :color="vacancyStatus(v.status).color">{{ vacancyStatus(v.status).label }}</v-chip>
                </template>
              </v-list-item>
            </v-list>
            <v-card-text v-else class="text-medium-emphasis">No open vacancies.</v-card-text>
          </v-card>
        </v-col>

        <v-col v-if="access.requisitions" cols="12" :lg="access.vacancies ? 12 : 6">
          <v-card variant="outlined" class="rounded-lg">
            <v-card-title class="text-subtitle-1 font-weight-bold border-b">Requisitions by status</v-card-title>
            <v-card-text class="d-flex flex-wrap ga-2">
              <v-chip v-for="(count, key) in requisitionCounts" :key="key" variant="tonal" :color="requisitionStatus(key).color" @click="go(route('recruitment.requisitions.index', { status: key }))">
                {{ requisitionStatus(key).label }}: {{ count }}
              </v-chip>
            </v-card-text>
          </v-card>
        </v-col>
      </v-row>
    </v-container>
  </SidebarLayout>
</template>

<script>
import { Head, router } from "@inertiajs/vue3";
import SidebarLayout from "@/layouts/SidebarLayout.vue";
import { REQUISITION_STATUS, VACANCY_STATUS, statusInfo, applicantName, formatDate, formatDateTime } from "@/utils/recruitment";

export default {
  name: "RecruitmentDashboard",
  components: { SidebarLayout, Head },
  props: {
    access: { type: Object, default: () => ({}) },
    requisitionCounts: { type: Object, default: () => ({}) },
    vacancyCounts: { type: Object, default: () => ({}) },
    remainingOpenings: { type: Number, default: 0 },
    awaitingMyApproval: { type: Array, default: () => [] },
    offersAwaitingMyApproval: { type: Array, default: () => [] },
    recentVacancies: { type: Array, default: () => [] },
  },
  methods: {
    applicantName,
    formatDate,
    formatDateTime,
    requisitionStatus(value) {
      return statusInfo(REQUISITION_STATUS, value);
    },
    vacancyStatus(value) {
      return statusInfo(VACANCY_STATUS, value);
    },
    go(url) {
      router.visit(url);
    },
  },
};
</script>
