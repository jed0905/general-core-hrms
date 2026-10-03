<template>
  <SidebarLayout>
    <Head title="Time Logs" />

    <v-container fluid class="pa-4 pa-sm-6">
      <div class="d-flex flex-column flex-md-row align-md-center justify-space-between ga-4 mb-4">
        <div>
          <h1 class="text-h5 font-weight-bold">Time Logs</h1>
          <p class="text-body-2 text-medium-emphasis">
            Raw time-in/time-out punches from the attendance devices. Late,
            undertime and absences are not computed here.
          </p>
        </div>

        <div v-if="can.export" class="d-flex flex-wrap ga-2">
          <v-btn :href="exportUrl('excel')" variant="outlined" color="primary" prepend-icon="mdi-microsoft-excel">
            Excel
          </v-btn>
          <v-btn
            :href="tooLargeForPdf ? undefined : exportUrl('pdf')"
            :disabled="tooLargeForPdf"
            variant="outlined"
            color="primary"
            prepend-icon="mdi-file-pdf-box"
          >
            PDF
          </v-btn>
          <v-btn
            :href="tooLargeForPdf ? undefined : exportUrl('print')"
            :disabled="tooLargeForPdf"
            target="_blank"
            variant="outlined"
            color="primary"
            prepend-icon="mdi-printer-outline"
          >
            Print
          </v-btn>
        </div>
      </div>

      <!-- Filters (applied on the server) -->
      <v-card variant="outlined" class="rounded-lg mb-4 pa-3">
        <v-row density="compact">
          <v-col v-for="f in filterSchema" :key="f.key" cols="12" sm="6" md="3">
            <v-text-field
              v-if="f.type === 'text'"
              v-model="form[f.key]"
              label="Employee (name, no. or biometric ID)"
              variant="outlined"
              density="compact"
              clearable
              :error-messages="errors[f.key]"
              @keyup.enter="apply"
            />
            <v-text-field
              v-else-if="f.type === 'date'"
              v-model="form[f.key]"
              type="date"
              :label="f.label"
              variant="outlined"
              density="compact"
              :error-messages="errors[f.key]"
            />
            <v-autocomplete
              v-else-if="f.type === 'select'"
              v-model="form[f.key]"
              :items="f.options || []"
              :label="f.label"
              placeholder="All"
              persistent-placeholder
              variant="outlined"
              density="compact"
              clearable
              :error-messages="errors[f.key]"
            />
            <v-switch
              v-else-if="f.type === 'boolean'"
              v-model="form[f.key]"
              :label="f.label"
              color="primary"
              density="compact"
              hide-details
            />
          </v-col>
          <v-col cols="12" class="d-flex justify-end ga-2">
            <v-btn variant="text" :disabled="loading" @click="reset">Reset</v-btn>
            <v-btn color="primary" variant="flat" prepend-icon="mdi-magnify" :loading="loading" @click="apply">
              Apply
            </v-btn>
          </v-col>
        </v-row>
      </v-card>

      <v-card variant="outlined" class="rounded-lg">
        <div class="d-flex align-center justify-space-between px-4 py-2">
          <span class="text-body-2 text-medium-emphasis">
            {{ logs.total.toLocaleString() }} {{ logs.total === 1 ? "punch" : "punches" }}
            · {{ formatDate(filters.date_from) }} – {{ formatDate(filters.date_to) }}
          </span>
          <v-select
            v-model="perPage"
            :items="perPageOptions"
            label="Rows per page"
            variant="plain"
            density="compact"
            hide-details
            style="max-width: 140px"
            @update:model-value="apply"
          />
        </div>

        <v-progress-linear v-if="loading" indeterminate color="primary" />

        <div class="time-logs-table">
          <v-table density="compact" hover>
            <thead>
              <tr>
                <th>Date</th>
                <th>Time</th>
                <th>Employee No.</th>
                <th>Employee Name</th>
                <th>Department</th>
                <th>Location</th>
                <th>Direction</th>
                <th>Device</th>
                <th>Device Serial</th>
                <th>Card No.</th>
                <th>Person Name</th>
              </tr>
            </thead>
            <tbody>
              <tr v-if="!logs.data.length">
                <td colspan="11" class="text-center text-medium-emphasis py-8">
                  <v-icon icon="mdi-clock-remove-outline" class="mb-2" size="32" />
                  <div>No time logs match the selected filters.</div>
                </td>
              </tr>
              <tr v-for="log in logs.data" :key="log.id">
                <td class="text-no-wrap">{{ formatDate(log.date) }}</td>
                <td class="text-no-wrap">{{ log.time }}</td>
                <td class="text-no-wrap">{{ log.employee_number || "—" }}</td>
                <td>
                  <span v-if="log.employee_id">{{ log.employee }}</span>
                  <v-chip v-else size="x-small" color="warning" variant="tonal">
                    Unregistered ID {{ log.biometric_id }}
                  </v-chip>
                </td>
                <td>{{ log.department || "—" }}</td>
                <td>{{ log.location || "—" }}</td>
                <td>
                  <v-chip size="x-small" :color="directionColor(log.direction)" variant="tonal">
                    {{ log.direction }}
                  </v-chip>
                </td>
                <td>{{ log.device }}</td>
                <td class="text-no-wrap">{{ log.device_serial }}</td>
                <td class="text-no-wrap">{{ log.card_no }}</td>
                <td>{{ log.person_name }}</td>
              </tr>
            </tbody>
          </v-table>
        </div>

        <div class="pa-3">
          <Pagination :meta="logs" />
        </div>
      </v-card>
    </v-container>
  </SidebarLayout>
</template>

<script>
import { Head, router } from "@inertiajs/vue3";
import SidebarLayout from "@/layouts/SidebarLayout.vue";
import Pagination from "@/components/Pagination.vue";

const PDF_ROW_LIMIT = 2000;

export default {
  name: "TimeLogsIndex",
  components: { SidebarLayout, Head, Pagination },
  props: {
    logs: { type: Object, required: true },
    // Applied filters (validated, defaults filled in by the server).
    filters: { type: Object, default: () => ({}) },
    filterSchema: { type: Array, default: () => [] },
    perPageOptions: { type: Array, default: () => [25] },
    can: { type: Object, default: () => ({}) },
  },
  data() {
    return {
      form: this.initialForm(),
      perPage: this.logs.per_page || 25,
      loading: false,
    };
  },
  computed: {
    errors() {
      return this.$page.props.errors || {};
    },
    tooLargeForPdf() {
      return this.logs.total > PDF_ROW_LIMIT;
    },
  },
  methods: {
    initialForm() {
      const form = {};
      this.filterSchema.forEach((f) => {
        let value = this.filters[f.key] ?? null;
        if (f.type === "boolean") value = value === true || value === 1 || value === "1" || value === "true";
        if (f.type === "select" && value !== null && (f.options || []).some((o) => o.value === Number(value))) value = Number(value);
        form[f.key] = value;
      });
      return form;
    },
    params(source) {
      const params = {};
      Object.entries(source).forEach(([key, value]) => {
        if (value === null || value === "" || value === undefined) return;
        params[key] = typeof value === "boolean" ? (value ? 1 : 0) : value;
      });
      return params;
    },
    apply() {
      router.get(
        route("time.time-logs.index"),
        { ...this.params(this.form), per_page: this.perPage },
        {
          preserveState: true,
          preserveScroll: true,
          onStart: () => (this.loading = true),
          onFinish: () => (this.loading = false),
        }
      );
    },
    reset() {
      this.loading = true;
      router.get(route("time.time-logs.index"), {}, { onFinish: () => (this.loading = false) });
    },
    // Same endpoints and filters as Reports → Attendance Log.
    exportUrl(format) {
      return route(`reports.attendance-log.${format}`, this.params(this.filters));
    },
    formatDate(value) {
      return value
        ? new Date(`${String(value).substring(0, 10)}T00:00:00`).toLocaleDateString(undefined, { month: "short", day: "numeric", year: "numeric" })
        : "—";
    },
    directionColor(direction) {
      return { "check in": "success", "check out": "info", "break out": "warning", "break in": "warning" }[direction] || "default";
    },
  },
};
</script>

<style scoped>
.time-logs-table {
  overflow-x: auto;
}
</style>
