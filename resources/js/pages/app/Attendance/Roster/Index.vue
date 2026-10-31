<template>
  <SidebarLayout>
    <Head title="Shift Roster" />
    <v-container fluid class="pa-6">
      <div class="d-flex align-center justify-space-between mb-4 flex-wrap ga-2">
        <div>
          <h1 class="text-h5 font-weight-bold">Shift Roster</h1>
          <p class="text-body-2 text-medium-emphasis">Date-specific shifts and rest days for rotating schedules. Days without an entry follow the employee's weekly work schedule (Time module).</p>
        </div>
        <div class="d-flex ga-2 align-center">
          <v-btn icon="mdi-chevron-left" variant="text" @click="moveWeek(-7)" />
          <span class="text-body-1">{{ date(week) }} – {{ date(dates[6]) }}</span>
          <v-btn icon="mdi-chevron-right" variant="text" @click="moveWeek(7)" />
          <v-select v-model="department" :items="departments" item-title="name" item-value="id" label="Department" variant="outlined" density="compact" hide-details clearable style="width: 220px" @update:model-value="moveWeek(0)" />
          <v-btn v-if="can.manage" color="primary" prepend-icon="mdi-calendar-edit" :disabled="!selected.length" @click="dialog = true">Assign ({{ selected.length }})</v-btn>
        </div>
      </div>

      <v-card variant="outlined" class="rounded-lg">
        <v-table density="compact">
          <thead><tr><th v-if="can.manage" style="width: 40px"></th><th>Employee</th><th v-for="d in dates" :key="d" class="text-center">{{ dayLabel(d) }}</th></tr></thead>
          <tbody>
            <tr v-if="!employees.data.length"><td colspan="9" class="text-center text-medium-emphasis pa-6">No employees.</td></tr>
            <tr v-for="e in employees.data" :key="e.id">
              <td v-if="can.manage"><v-checkbox-btn v-model="selected" :value="e.id" density="compact" /></td>
              <td>{{ employeeName(e) }}<div class="text-caption text-medium-emphasis">{{ e.employee_number }}</div></td>
              <td v-for="d in dates" :key="d" class="text-center">
                <template v-if="entry(e.id, d)">
                  <v-chip v-if="entry(e.id, d).is_rest_day" size="x-small" color="grey" variant="tonal">Rest</v-chip>
                  <v-chip v-else size="x-small" color="primary" variant="tonal">{{ entry(e.id, d).shift?.code }}</v-chip>
                </template>
                <span v-else class="text-caption text-medium-emphasis">schedule</span>
              </td>
            </tr>
          </tbody>
        </v-table>
        <div class="pa-4"><Pagination :meta="employees" /></div>
      </v-card>

      <v-dialog v-model="dialog" max-width="520">
        <v-card class="rounded-lg">
          <v-card-title class="text-h6 pa-4">Assign roster for {{ selected.length }} employee(s)</v-card-title>
          <v-card-text>
            <div class="d-flex ga-2">
              <v-text-field v-model="form.date_from" type="date" label="From" variant="outlined" density="compact" :error-messages="form.errors.date_from" />
              <v-text-field v-model="form.date_to" type="date" label="To" variant="outlined" density="compact" :error-messages="form.errors.date_to" />
            </div>
            <v-radio-group v-model="mode" inline density="compact">
              <v-radio label="Shift" value="shift" /><v-radio label="Rest day" value="rest" /><v-radio label="Clear (use weekly schedule)" value="clear" />
            </v-radio-group>
            <v-select v-if="mode === 'shift'" v-model="form.shift_id" :items="shifts" :item-title="(s) => `${s.code} · ${s.name} (${(s.start_time || '').substring(0, 5)}–${(s.end_time || '').substring(0, 5)})`" item-value="id" label="Shift" variant="outlined" density="compact" :error-messages="form.errors.shift_id" />
            <v-text-field v-model="form.remarks" label="Remarks" variant="outlined" density="compact" />
          </v-card-text>
          <v-card-actions class="pa-4"><v-spacer /><v-btn variant="text" @click="dialog = false">Cancel</v-btn><v-btn color="primary" variant="flat" :loading="form.processing" @click="save">Save</v-btn></v-card-actions>
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

export default {
  name: "AttendanceRoster",
  components: { SidebarLayout, Head, Pagination },
  mixins: [attendancePage],
  props: {
    week: { type: String, required: true },
    dates: { type: Array, required: true },
    employees: { type: Object, required: true },
    entries: { type: Array, default: () => [] },
    shifts: { type: Array, default: () => [] },
    departments: { type: Array, default: () => [] },
    filters: { type: Object, default: () => ({}) },
    can: { type: Object, default: () => ({}) },
  },
  data() {
    return {
      selected: [],
      dialog: false,
      mode: "shift",
      department: this.filters.department_id ? Number(this.filters.department_id) : null,
      form: useForm({ date_from: this.week, date_to: this.dates[6], shift_id: null, remarks: "" }),
    };
  },
  methods: {
    entry(employeeId, d) {
      return this.entries.find((x) => x.employee_id === employeeId && x.work_date.substring(0, 10) === d);
    },
    dayLabel(d) {
      return new Date(`${d}T00:00:00`).toLocaleDateString(undefined, { weekday: "short", day: "numeric" });
    },
    moveWeek(days) {
      const next = new Date(`${this.week}T00:00:00`);
      next.setDate(next.getDate() + days);
      const week = `${next.getFullYear()}-${String(next.getMonth() + 1).padStart(2, "0")}-${String(next.getDate()).padStart(2, "0")}`;
      router.get(route("attendance.roster.index"), { week, department_id: this.department }, { preserveScroll: true });
    },
    save() {
      this.form
        .transform((d) => ({ ...d, employee_ids: this.selected, shift_id: this.mode === "shift" ? d.shift_id : null, is_rest_day: this.mode === "rest" }))
        .post(route("attendance.roster.store"), { preserveScroll: true, onSuccess: () => { this.dialog = false; this.selected = []; } });
    },
  },
};
</script>
