<template>
  <SidebarLayout>
    <Head title="My Attendance" />
    <v-container fluid class="pa-6">
      <div class="d-flex align-center justify-space-between mb-4 flex-wrap ga-2">
        <div>
          <h1 class="text-h5 font-weight-bold">My Attendance</h1>
          <p class="text-body-2 text-medium-emphasis">Your attendance as calculated from your schedule, punches, holidays and approved leave.</p>
        </div>
        <div v-if="hasEmployeeRecord" class="d-flex ga-2 align-center flex-wrap">
          <v-text-field v-model="range.date_from" type="date" label="From" variant="outlined" density="compact" hide-details @update:model-value="apply" />
          <v-text-field v-model="range.date_to" type="date" label="To" variant="outlined" density="compact" hide-details @update:model-value="apply" />
          <v-btn v-if="can.export" variant="tonal" prepend-icon="mdi-download" :href="route('attendance.my.export', range)">CSV</v-btn>
        </div>
      </div>

      <v-alert v-if="!hasEmployeeRecord" type="info" variant="tonal">Your account is not linked to an employee record.</v-alert>

      <template v-else>
        <v-alert v-if="!hasEnrollment" type="info" variant="tonal" density="compact" class="mb-4">No attendance device ID is mapped to you yet. Ask HR if your punches are missing.</v-alert>
        <v-alert v-if="errors.date_to" type="error" variant="tonal" density="compact" class="mb-4">{{ errors.date_to }}</v-alert>

        <v-card variant="outlined" class="rounded-lg mb-4">
          <v-table density="compact">
            <thead><tr><th>Date</th><th>Shift</th><th>Punches (pairs)</th><th class="text-end">Worked</th><th class="text-end">Late</th><th class="text-end">Undertime</th><th class="text-end">Approved OT</th><th>Leave</th><th>Status</th></tr></thead>
            <tbody>
              <tr v-if="!days.length"><td colspan="9" class="text-center text-medium-emphasis pa-6">No attendance calculated for this range yet.</td></tr>
              <tr v-for="d in days" :key="d.id">
                <td class="text-no-wrap">{{ date(d.work_date) }}<div class="text-caption text-medium-emphasis">{{ label(d.day_type) }}</div></td>
                <td>{{ d.shift?.code || '—' }}</td>
                <td class="text-caption"><div v-for="(p, i) in d.pairs || []" :key="i">{{ timeIn(p[0], d.timezone) }} – {{ timeIn(p[1], d.timezone) }}</div></td>
                <td class="text-end">{{ minutes(d.worked_minutes) }}</td>
                <td class="text-end">{{ minutes(d.late_minutes) }}</td>
                <td class="text-end">{{ minutes(d.undertime_minutes) }}</td>
                <td class="text-end">{{ minutes(d.approved_overtime_minutes) }}</td>
                <td>{{ Number(d.leave_days) ? Number(d.leave_days) + ' d' : '' }}</td>
                <td>
                  <v-chip size="x-small" variant="tonal" :color="statusInfo(statusMap, d.status).color">{{ statusInfo(statusMap, d.status).label }}</v-chip>
                  <div v-for="(x, i) in d.exceptions || []" :key="i" class="text-caption text-error">{{ x.message }}</div>
                </td>
              </tr>
            </tbody>
          </v-table>
        </v-card>

        <v-row>
          <v-col cols="12" md="7">
            <v-card variant="outlined" class="rounded-lg">
              <v-card-title class="text-subtitle-1 font-weight-bold">Punches</v-card-title>
              <v-table density="compact">
                <thead><tr><th>Time</th><th>Type</th><th>Source</th><th>Device</th></tr></thead>
                <tbody>
                  <tr v-if="!events.length"><td colspan="4" class="text-medium-emphasis pa-4">No punches in this range.</td></tr>
                  <tr v-for="e in events" :key="e.id">
                    <td>{{ dateTimeIn(e.occurred_at, e.device_timezone) }}</td>
                    <td>{{ label(e.event_type) }}</td>
                    <td><v-chip size="x-small" variant="tonal" :color="statusInfo(sourceMap, e.source).color">{{ statusInfo(sourceMap, e.source).label }}</v-chip></td>
                    <td>{{ e.device?.name || '—' }}</td>
                  </tr>
                </tbody>
              </v-table>
            </v-card>
          </v-col>
          <v-col cols="12" md="5">
            <v-card variant="outlined" class="rounded-lg">
              <v-card-title class="text-subtitle-1 font-weight-bold">Corrections</v-card-title>
              <v-table density="compact">
                <tbody>
                  <tr v-if="!adjustments.length"><td class="text-medium-emphasis pa-4">None.</td></tr>
                  <tr v-for="a in adjustments" :key="a.id">
                    <td>{{ date(a.work_date) }} · {{ label(a.type) }}<div class="text-caption text-medium-emphasis">{{ a.reason }}</div></td>
                    <td><v-chip size="x-small" variant="tonal" :color="statusInfo(adjustmentMap, a.status).color">{{ statusInfo(adjustmentMap, a.status).label }}</v-chip></td>
                  </tr>
                </tbody>
              </v-table>
            </v-card>
          </v-col>
        </v-row>
      </template>
    </v-container>
  </SidebarLayout>
</template>

<script>
import { Head, router } from "@inertiajs/vue3";
import SidebarLayout from "@/layouts/SidebarLayout.vue";
import attendancePage from "@/mixins/attendancePage";
import { ADJUSTMENT_STATUS, DAY_STATUS, SOURCE } from "@/utils/attendance";

export default {
  name: "MyAttendance",
  components: { SidebarLayout, Head },
  mixins: [attendancePage],
  props: {
    hasEmployeeRecord: { type: Boolean, default: false },
    hasEnrollment: { type: Boolean, default: false },
    filters: { type: Object, default: () => ({}) },
    days: { type: Array, default: () => [] },
    events: { type: Array, default: () => [] },
    adjustments: { type: Array, default: () => [] },
    errors: { type: Object, default: () => ({}) },
    can: { type: Object, default: () => ({}) },
  },
  data() {
    return { statusMap: DAY_STATUS, sourceMap: SOURCE, adjustmentMap: ADJUSTMENT_STATUS, range: { ...this.filters } };
  },
  methods: {
    apply() {
      router.get(route("attendance.my.index"), this.range, { preserveState: true, replace: true });
    },
  },
};
</script>
