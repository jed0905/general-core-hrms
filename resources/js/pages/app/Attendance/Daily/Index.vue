<template>
  <SidebarLayout>
    <Head title="Daily Attendance" />
    <v-container fluid class="pa-6">
      <div class="d-flex align-center justify-space-between mb-4 flex-wrap ga-2">
        <div>
          <h1 class="text-h5 font-weight-bold">Daily Attendance</h1>
          <p class="text-body-2 text-medium-emphasis">Calculated from raw punches, schedules, approved leave, holidays and approved adjustments.</p>
        </div>
        <div class="d-flex ga-2 align-center">
          <v-switch v-model="form.exceptions" label="Exceptions only" color="error" density="compact" hide-details @update:model-value="apply" />
          <v-btn v-if="can.process" variant="outlined" prepend-icon="mdi-refresh" :loading="busy" @click="recalculate">Recalculate range</v-btn>
        </div>
      </div>

      <v-card variant="outlined" class="rounded-lg mb-4 pa-4">
        <v-row density="compact">
          <v-col cols="6" md="2"><v-text-field v-model="form.date_from" type="date" label="From" variant="outlined" density="compact" hide-details @update:model-value="apply" /></v-col>
          <v-col cols="6" md="2"><v-text-field v-model="form.date_to" type="date" label="To" variant="outlined" density="compact" hide-details @update:model-value="apply" /></v-col>
          <v-col cols="12" md="3"><v-autocomplete v-model="form.employee_id" :items="employees" :item-title="(e) => `${employeeName(e)} · ${e.employee_number}`" item-value="id" label="Employee" variant="outlined" density="compact" hide-details clearable @update:model-value="apply" /></v-col>
          <v-col cols="12" md="2"><v-select v-model="form.department_id" :items="departments" item-title="name" item-value="id" label="Department" variant="outlined" density="compact" hide-details clearable @update:model-value="apply" /></v-col>
          <v-col cols="6" md="1"><v-select v-model="form.location_id" :items="locations" item-title="city" item-value="id" label="Location" variant="outlined" density="compact" hide-details clearable @update:model-value="apply" /></v-col>
          <v-col cols="6" md="1"><v-select v-model="form.shift_id" :items="shifts" item-title="code" item-value="id" label="Shift" variant="outlined" density="compact" hide-details clearable @update:model-value="apply" /></v-col>
          <v-col cols="12" md="1"><v-select v-model="form.status" :items="statuses.map((s) => ({ value: s, title: statusInfo(statusMap, s).label }))" label="Status" variant="outlined" density="compact" hide-details clearable @update:model-value="apply" /></v-col>
        </v-row>
      </v-card>

      <v-card variant="outlined" class="rounded-lg">
        <v-table density="compact">
          <thead><tr><th>Date</th><th>Employee</th><th>Shift</th><th>In</th><th>Out</th><th class="text-end">Worked</th><th class="text-end">Late</th><th class="text-end">Undertime</th><th class="text-end">Overtime</th><th class="text-end">Night</th><th>Status</th></tr></thead>
          <tbody>
            <tr v-if="!days.data.length"><td colspan="11" class="text-center text-medium-emphasis pa-6">No calculated attendance in this range. Synchronize devices or recalculate.</td></tr>
            <tr v-for="d in days.data" :key="d.id">
              <td class="text-no-wrap">{{ date(d.work_date) }}<div class="text-caption text-medium-emphasis">{{ label(d.day_type) }}{{ d.holiday_type ? ' · ' + label(d.holiday_type) : '' }}</div></td>
              <td><a :href="route('attendance.daily.employee', d.employee_id)" class="text-primary text-decoration-none" @click.prevent="go(route('attendance.daily.employee', d.employee_id))">{{ employeeName(d.employee) }}</a></td>
              <td>{{ d.shift?.code || '—' }}</td>
              <td>{{ timeIn(d.first_in, d.timezone) }}</td>
              <td>{{ timeIn(d.last_out, d.timezone) }}</td>
              <td class="text-end">{{ minutes(d.worked_minutes) }}</td>
              <td class="text-end">{{ minutes(d.late_minutes) }}</td>
              <td class="text-end">{{ minutes(d.undertime_minutes) }}</td>
              <td class="text-end">{{ minutes(d.approved_overtime_minutes) }}<span v-if="d.overtime_minutes > d.approved_overtime_minutes" class="text-caption text-warning"> ({{ minutes(d.overtime_minutes) }} worked)</span></td>
              <td class="text-end">{{ minutes(d.night_minutes) }}</td>
              <td>
                <v-chip size="x-small" variant="tonal" :color="statusInfo(statusMap, d.status).color">{{ statusInfo(statusMap, d.status).label }}</v-chip>
                <div v-for="(x, i) in d.exceptions || []" :key="i" class="text-caption text-error">{{ x.message }}</div>
              </td>
            </tr>
          </tbody>
        </v-table>
        <div class="pa-4"><Pagination :meta="days" /></div>
      </v-card>
    </v-container>
  </SidebarLayout>
</template>

<script>
import { Head, router } from "@inertiajs/vue3";
import SidebarLayout from "@/layouts/SidebarLayout.vue";
import Pagination from "@/components/Pagination.vue";
import attendancePage from "@/mixins/attendancePage";
import { DAY_STATUS } from "@/utils/attendance";

export default {
  name: "AttendanceDailyIndex",
  components: { SidebarLayout, Head, Pagination },
  mixins: [attendancePage],
  props: {
    days: { type: Object, required: true },
    filters: { type: Object, default: () => ({}) },
    employees: { type: Array, default: () => [] },
    departments: { type: Array, default: () => [] },
    locations: { type: Array, default: () => [] },
    shifts: { type: Array, default: () => [] },
    statuses: { type: Array, default: () => [] },
    can: { type: Object, default: () => ({}) },
  },
  data() {
    const n = (v) => (v ? Number(v) : null);
    return {
      busy: false,
      statusMap: DAY_STATUS,
      form: {
        date_from: this.filters.date_from, date_to: this.filters.date_to, employee_id: n(this.filters.employee_id),
        department_id: n(this.filters.department_id), location_id: n(this.filters.location_id), shift_id: n(this.filters.shift_id),
        status: this.filters.status || null, exceptions: !!this.filters.exceptions,
      },
    };
  },
  methods: {
    apply() {
      router.get(route("attendance.daily.index"), { ...this.form, exceptions: this.form.exceptions ? 1 : null }, { preserveState: true, preserveScroll: true, replace: true });
    },
    recalculate() {
      this.busy = true;
      router.post(route("attendance.daily.recalculate"), { date_from: this.form.date_from, date_to: this.form.date_to, employee_ids: this.form.employee_id ? [this.form.employee_id] : null }, {
        preserveScroll: true, onFinish: () => (this.busy = false), onError: (e) => this.showToast(this.firstError(e), "error"),
      });
    },
  },
};
</script>
