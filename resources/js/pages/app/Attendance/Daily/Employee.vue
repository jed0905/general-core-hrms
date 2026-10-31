<template>
  <SidebarLayout>
    <Head :title="`Attendance · ${employeeName(employee)}`" />
    <v-container fluid class="pa-6">
      <a :href="route('attendance.daily.index')" class="text-caption text-primary text-decoration-none" @click.prevent="go(route('attendance.daily.index'))">← Daily attendance</a>
      <div class="d-flex align-center justify-space-between mb-4 flex-wrap ga-2">
        <div>
          <h1 class="text-h5 font-weight-bold">{{ employeeName(employee) }}</h1>
          <p class="text-body-2 text-medium-emphasis">{{ employee.employee_number }} · attendance history</p>
        </div>
        <div class="d-flex ga-2">
          <v-text-field v-model="range.date_from" type="date" label="From" variant="outlined" density="compact" hide-details @update:model-value="apply" />
          <v-text-field v-model="range.date_to" type="date" label="To" variant="outlined" density="compact" hide-details @update:model-value="apply" />
        </div>
      </div>

      <v-card variant="outlined" class="rounded-lg mb-4">
        <v-table density="compact">
          <thead><tr><th>Date</th><th>Shift</th><th>Punches (pairs)</th><th class="text-end">Worked</th><th class="text-end">Late</th><th class="text-end">Undertime</th><th class="text-end">OT (approved)</th><th>Leave</th><th>Status</th><th></th></tr></thead>
          <tbody>
            <tr v-if="!days.length"><td colspan="10" class="text-center text-medium-emphasis pa-6">No calculated days.</td></tr>
            <tr v-for="d in days" :key="d.id">
              <td class="text-no-wrap">{{ date(d.work_date) }}<div class="text-caption text-medium-emphasis">{{ label(d.day_type) }}</div></td>
              <td>{{ d.shift?.code || '—' }}</td>
              <td class="text-caption"><div v-for="(p, i) in d.pairs || []" :key="i">{{ timeIn(p[0], d.timezone) }} – {{ timeIn(p[1], d.timezone) }}</div></td>
              <td class="text-end">{{ minutes(d.worked_minutes) }}</td>
              <td class="text-end">{{ minutes(d.late_minutes) }}</td>
              <td class="text-end">{{ minutes(d.undertime_minutes) }}</td>
              <td class="text-end">{{ minutes(d.approved_overtime_minutes) }} / {{ minutes(d.overtime_minutes) }}</td>
              <td>{{ Number(d.leave_days) ? Number(d.leave_days) + ' d' : '' }}</td>
              <td><v-chip size="x-small" variant="tonal" :color="statusInfo(statusMap, d.status).color">{{ statusInfo(statusMap, d.status).label }}</v-chip>
                <div v-for="(x, i) in d.exceptions || []" :key="i" class="text-caption text-error">{{ x.message }}</div></td>
              <td class="text-end"><v-btn v-if="can.adjust && !d.locked_at" size="small" variant="text" @click="openAdjust(d)">Adjust</v-btn></td>
            </tr>
          </tbody>
        </v-table>
      </v-card>

      <v-row>
        <v-col cols="12" md="7">
          <v-card variant="outlined" class="rounded-lg">
            <v-card-title class="text-subtitle-1 font-weight-bold">Raw punches</v-card-title>
            <v-table density="compact">
              <thead><tr><th>Time</th><th>Type</th><th>Source</th><th>Device</th></tr></thead>
              <tbody>
                <tr v-for="e in events" :key="e.id"><td>{{ dateTimeIn(e.occurred_at, e.device_timezone) }}</td><td>{{ label(e.event_type) }}</td><td><v-chip size="x-small" variant="tonal" :color="statusInfo(sourceMap, e.source).color">{{ statusInfo(sourceMap, e.source).label }}</v-chip></td><td>{{ e.device?.name || '—' }}</td></tr>
              </tbody>
            </v-table>
          </v-card>
        </v-col>
        <v-col cols="12" md="5">
          <v-card variant="outlined" class="rounded-lg">
            <v-card-title class="text-subtitle-1 font-weight-bold">Adjustments</v-card-title>
            <v-table density="compact">
              <tbody>
                <tr v-if="!adjustments.length"><td class="text-medium-emphasis pa-4">None.</td></tr>
                <tr v-for="a in adjustments" :key="a.id">
                  <td>{{ date(a.work_date) }} · {{ label(a.type) }}<div class="text-caption text-medium-emphasis">{{ a.reason }} ({{ a.requester?.username }})</div></td>
                  <td><v-chip size="x-small" variant="tonal" :color="statusInfo(adjustmentMap, a.status).color">{{ statusInfo(adjustmentMap, a.status).label }}</v-chip></td>
                </tr>
              </tbody>
            </v-table>
          </v-card>
        </v-col>
      </v-row>

      <AdjustmentDialog v-model="adjustDialog" :employee-id="employee.id" :day="adjustDay" :events="adjustDay ? events.filter((e) => (adjustDay.event_ids || []).includes(e.id)) : []" />
    </v-container>
  </SidebarLayout>
</template>

<script>
import { Head, router } from "@inertiajs/vue3";
import SidebarLayout from "@/layouts/SidebarLayout.vue";
import AdjustmentDialog from "@/components/Attendance/AdjustmentDialog.vue";
import attendancePage from "@/mixins/attendancePage";
import { ADJUSTMENT_STATUS, DAY_STATUS, SOURCE } from "@/utils/attendance";

export default {
  name: "AttendanceEmployeeHistory",
  components: { SidebarLayout, Head, AdjustmentDialog },
  mixins: [attendancePage],
  props: {
    employee: { type: Object, required: true },
    filters: { type: Object, default: () => ({}) },
    days: { type: Array, default: () => [] },
    events: { type: Array, default: () => [] },
    adjustments: { type: Array, default: () => [] },
    can: { type: Object, default: () => ({}) },
  },
  data() {
    return { statusMap: DAY_STATUS, sourceMap: SOURCE, adjustmentMap: ADJUSTMENT_STATUS, range: { ...this.filters }, adjustDialog: false, adjustDay: null };
  },
  methods: {
    apply() {
      router.get(route("attendance.daily.employee", this.employee.id), this.range, { preserveState: true, replace: true });
    },
    openAdjust(day) {
      this.adjustDay = day;
      this.adjustDialog = true;
    },
  },
};
</script>
