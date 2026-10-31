<template>
  <SidebarLayout>
    <Head title="Attendance" />
    <v-container fluid class="pa-6">
      <div class="d-flex align-center justify-space-between mb-4 flex-wrap ga-2">
        <div>
          <h1 class="text-h5 font-weight-bold">Attendance</h1>
          <p class="text-body-2 text-medium-emphasis">Attendance for {{ date(selectedDate) }}, device health and work waiting.</p>
        </div>
        <v-text-field v-model="selectedDate" type="date" label="Date" variant="outlined" density="compact" hide-details style="max-width: 200px" @update:model-value="reload" />
      </div>

      <v-row class="mb-2">
        <v-col v-for="(info, status) in statusMap" :key="status" cols="6" md="2">
          <v-card variant="outlined" class="rounded-lg pa-3" :href="route('attendance.daily.index', { date_from: selectedDate, date_to: selectedDate, status })" @click.prevent="go(route('attendance.daily.index', { date_from: selectedDate, date_to: selectedDate, status }))">
            <div class="text-caption text-medium-emphasis">{{ info.label }}</div>
            <div class="text-h5 font-weight-bold" :class="`text-${info.color}`">{{ statusCounts[status] || 0 }}</div>
          </v-card>
        </v-col>
      </v-row>

      <v-row class="mb-2">
        <v-col cols="12" md="4"><v-card variant="outlined" class="rounded-lg pa-3"><div class="text-caption text-medium-emphasis">Adjustments waiting</div><div class="text-h6">{{ queues.adjustments_pending }}</div></v-card></v-col>
        <v-col cols="12" md="4"><v-card variant="outlined" class="rounded-lg pa-3"><div class="text-caption text-medium-emphasis">Days with missing punches (31 days)</div><div class="text-h6">{{ queues.incomplete_days }}</div></v-card></v-col>
        <v-col v-if="queues.unmatched_events !== null" cols="12" md="4"><v-card variant="outlined" class="rounded-lg pa-3"><div class="text-caption text-medium-emphasis">Punches from unmapped device users</div><div class="text-h6">{{ queues.unmatched_events }}</div></v-card></v-col>
      </v-row>

      <v-card v-if="devices" variant="outlined" class="rounded-lg">
        <v-card-title class="text-subtitle-1 font-weight-bold">Devices</v-card-title>
        <v-table density="compact">
          <thead><tr><th>Device</th><th>Status</th><th>Last contact</th><th>Last successful sync</th><th>Last error</th></tr></thead>
          <tbody>
            <tr v-if="!devices.length"><td colspan="5" class="text-center text-medium-emphasis pa-6">No active devices.</td></tr>
            <tr v-for="d in devices" :key="d.id">
              <td><a :href="route('attendance.devices.show', d.id)" class="text-primary text-decoration-none" @click.prevent="go(route('attendance.devices.show', d.id))">{{ d.name }}</a> <span class="text-caption text-medium-emphasis">{{ d.vendor }}</span></td>
              <td><v-chip size="x-small" variant="tonal" :color="statusInfo(deviceMap, d.status).color">{{ statusInfo(deviceMap, d.status).label }}</v-chip></td>
              <td>{{ dateTime(d.last_seen_at) }}</td>
              <td>{{ dateTime(d.last_success_at) }}</td>
              <td class="text-caption text-error">{{ d.last_error }}</td>
            </tr>
          </tbody>
        </v-table>
      </v-card>
    </v-container>
  </SidebarLayout>
</template>

<script>
import { Head, router } from "@inertiajs/vue3";
import SidebarLayout from "@/layouts/SidebarLayout.vue";
import attendancePage from "@/mixins/attendancePage";
import { DAY_STATUS, DEVICE_STATUS } from "@/utils/attendance";

export default {
  name: "AttendanceDashboard",
  components: { SidebarLayout, Head },
  mixins: [attendancePage],
  props: {
    date: { type: String, required: true },
    statusCounts: { type: Object, default: () => ({}) },
    queues: { type: Object, default: () => ({}) },
    devices: { type: Array, default: null },
  },
  data() {
    return { selectedDate: this.date, statusMap: DAY_STATUS, deviceMap: DEVICE_STATUS };
  },
  methods: {
    reload() {
      router.get(route("attendance.dashboard"), { date: this.selectedDate }, { preserveState: true, replace: true });
    },
  },
};
</script>
