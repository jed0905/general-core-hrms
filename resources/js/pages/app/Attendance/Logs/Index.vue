<template>
  <SidebarLayout>
    <Head title="Raw Attendance Logs" />
    <v-container fluid class="pa-6">
      <div class="d-flex align-center justify-space-between mb-4 flex-wrap ga-2">
        <div>
          <h1 class="text-h5 font-weight-bold">Raw Attendance Logs</h1>
          <p class="text-body-2 text-medium-emphasis">Every punch exactly as received: device time and timezone are kept. Logs are never edited; corrections are adjustments.</p>
        </div>
        <div class="d-flex ga-2 flex-wrap">
          <v-btn v-if="can.create" color="primary" prepend-icon="mdi-plus" @click="dialogs.manual = true">Manual punch</v-btn>
          <v-btn v-if="can.import" variant="outlined" prepend-icon="mdi-upload" @click="dialogs.import = true">Import</v-btn>
        </div>
      </div>

      <v-card variant="outlined" class="rounded-lg mb-4 pa-4">
        <v-row density="compact">
          <v-col cols="6" md="2"><v-text-field v-model="form.date_from" type="date" label="From" variant="outlined" density="compact" hide-details @update:model-value="apply" /></v-col>
          <v-col cols="6" md="2"><v-text-field v-model="form.date_to" type="date" label="To" variant="outlined" density="compact" hide-details @update:model-value="apply" /></v-col>
          <v-col cols="12" md="3"><v-autocomplete v-model="form.employee_id" :items="employees" :item-title="(e) => `${employeeName(e)} · ${e.employee_number}`" item-value="id" label="Employee" variant="outlined" density="compact" hide-details clearable @update:model-value="apply" /></v-col>
          <v-col cols="12" md="2"><v-select v-model="form.device_id" :items="devices" item-title="name" item-value="id" label="Device" variant="outlined" density="compact" hide-details clearable @update:model-value="apply" /></v-col>
          <v-col cols="6" md="1"><v-select v-model="form.source" :items="sources.map((s) => ({ value: s, title: sourceMap[s]?.label || s }))" label="Source" variant="outlined" density="compact" hide-details clearable @update:model-value="apply" /></v-col>
          <v-col cols="6" md="2"><v-select v-model="form.status" :items="Object.entries(eventMap).map(([value, s]) => ({ value, title: s.label }))" label="Status" variant="outlined" density="compact" hide-details clearable @update:model-value="apply" /></v-col>
        </v-row>
      </v-card>

      <v-card variant="outlined" class="rounded-lg">
        <v-table density="compact">
          <thead><tr><th>Time (device)</th><th>Employee</th><th>Device / user</th><th>Type</th><th>Verify</th><th>Source</th><th>Status</th><th>Received</th></tr></thead>
          <tbody>
            <tr v-if="!events.data.length"><td colspan="8" class="text-center text-medium-emphasis pa-6">No punches in this range.</td></tr>
            <tr v-for="e in events.data" :key="e.id">
              <td class="text-no-wrap">{{ dateTimeIn(e.occurred_at, e.device_timezone) }}<div class="text-caption text-medium-emphasis">{{ e.device_timezone }} · raw {{ e.event_time_raw }}</div></td>
              <td>{{ e.employee ? employeeName(e.employee) : '—' }}</td>
              <td>{{ e.device?.name || '—' }}<div class="text-caption text-medium-emphasis">{{ e.device_user_id }}</div></td>
              <td>{{ label(e.event_type) }}</td>
              <td class="text-caption">{{ e.verify_mode || '—' }}</td>
              <td><v-chip size="x-small" variant="tonal" :color="statusInfo(sourceMap, e.source).color">{{ statusInfo(sourceMap, e.source).label }}</v-chip>
                <div v-if="e.creator" class="text-caption text-medium-emphasis">{{ e.creator.username }}{{ e.remarks ? ': ' + e.remarks : '' }}</div></td>
              <td><v-chip size="x-small" variant="tonal" :color="statusInfo(eventMap, e.status).color">{{ statusInfo(eventMap, e.status).label }}</v-chip>
                <div v-if="e.status_reason" class="text-caption text-medium-emphasis" style="max-width: 220px">{{ e.status_reason }}</div></td>
              <td class="text-caption text-no-wrap">{{ dateTime(e.received_at) }}</td>
            </tr>
          </tbody>
        </v-table>
        <div class="pa-4"><Pagination :meta="events" /></div>
      </v-card>

      <v-dialog v-model="dialogs.manual" max-width="520">
        <v-card class="rounded-lg">
          <v-card-title class="text-h6 pa-4">Manual punch</v-card-title>
          <v-card-text>
            <p class="text-body-2 mb-3">Recorded as a manual entry (with your name and reason), never as a device event. The time is in the employee's attendance timezone.</p>
            <v-autocomplete v-model="manual.employee_id" :items="employees" :item-title="(e) => `${employeeName(e)} · ${e.employee_number}`" item-value="id" label="Employee" variant="outlined" density="compact" class="mb-2" :error-messages="manual.errors.employee_id" />
            <div class="d-flex ga-2">
              <v-text-field v-model="manual.occurred_at" label="Date and time (YYYY-MM-DD HH:MM)" variant="outlined" density="compact" :error-messages="manual.errors.occurred_at" />
              <v-select v-model="manual.event_type" :items="types.map((t) => ({ value: t, title: label(t) }))" label="Type" variant="outlined" density="compact" />
            </div>
            <v-text-field v-model="manual.reason" label="Reason" variant="outlined" density="compact" :error-messages="manual.errors.reason" />
          </v-card-text>
          <v-card-actions class="pa-4"><v-spacer /><v-btn variant="text" @click="dialogs.manual = false">Cancel</v-btn><v-btn color="primary" variant="flat" :loading="manual.processing" @click="saveManual">Save</v-btn></v-card-actions>
        </v-card>
      </v-dialog>

      <v-dialog v-model="dialogs.import" max-width="560">
        <v-card class="rounded-lg">
          <v-card-title class="text-h6 pa-4">Import punches</v-card-title>
          <v-card-text>
            <div class="text-subtitle-2">CSV file</div>
            <p class="text-body-2 mb-2">Columns: employee_number, date_time (YYYY-MM-DD HH:MM, or ISO 8601 with offset), type (check_in, check_out, …, optional), device_serial (optional). <a :href="route('attendance.logs.template')" class="text-primary">Template</a></p>
            <v-file-input v-model="csvFile" accept=".csv,.txt" label="CSV file" variant="outlined" density="compact" />
            <v-btn color="primary" variant="tonal" :disabled="!csvFile" @click="importCsv">Import CSV</v-btn>
          </v-card-text>
          <v-card-actions class="pa-4"><v-spacer /><v-btn variant="text" @click="dialogs.import = false">Close</v-btn></v-card-actions>
        </v-card>
      </v-dialog>
    </v-container>
  </SidebarLayout>
</template>

<script>
import { Head, router, useForm } from "@inertiajs/vue3";
import SidebarLayout from "@/layouts/SidebarLayout.vue";
import Pagination from "@/components/Pagination.vue";
import attendancePage from "@/mixins/attendancePage";
import { EVENT_STATUS, SOURCE } from "@/utils/attendance";

export default {
  name: "AttendanceLogIndex",
  components: { SidebarLayout, Head, Pagination },
  mixins: [attendancePage],
  props: {
    events: { type: Object, required: true },
    filters: { type: Object, default: () => ({}) },
    devices: { type: Array, default: () => [] },
    employees: { type: Array, default: () => [] },
    departments: { type: Array, default: () => [] },
    sources: { type: Array, default: () => [] },
    types: { type: Array, default: () => [] },
    can: { type: Object, default: () => ({}) },
  },
  data() {
    return {
      sourceMap: SOURCE,
      eventMap: EVENT_STATUS,
      dialogs: { manual: false, import: false },
      csvFile: null,
      form: {
        date_from: this.filters.date_from, date_to: this.filters.date_to,
        employee_id: this.filters.employee_id ? Number(this.filters.employee_id) : null,
        device_id: this.filters.device_id ? Number(this.filters.device_id) : null,
        source: this.filters.source || null, status: this.filters.status || null,
      },
      manual: useForm({ employee_id: null, occurred_at: "", event_type: "check_in", reason: "" }),
    };
  },
  methods: {
    apply() {
      router.get(route("attendance.logs.index"), this.form, { preserveState: true, preserveScroll: true, replace: true });
    },
    saveManual() {
      this.manual.post(route("attendance.logs.store"), { preserveScroll: true, onSuccess: () => { this.dialogs.manual = false; this.manual.reset(); } });
    },
    importCsv() {
      router.post(route("attendance.logs.import"), { file: Array.isArray(this.csvFile) ? this.csvFile[0] : this.csvFile }, { forceFormData: true, preserveScroll: true, onSuccess: () => { this.csvFile = null; this.dialogs.import = false; } });
    },
  },
};
</script>
