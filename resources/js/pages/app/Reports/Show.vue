<template>
  <SidebarLayout>
    <Head :title="report.title" />

    <v-container fluid class="pa-4 pa-sm-6">
      <div class="d-flex flex-column flex-md-row align-md-center justify-space-between ga-4 mb-4">
        <div>
          <v-btn
            v-if="canCatalog"
            variant="text"
            size="small"
            prepend-icon="mdi-arrow-left"
            class="mb-1 px-0"
            @click="visit(route('reports.index'))"
          >
            Reports · {{ report.category }}
          </v-btn>
          <h1 class="text-h5 font-weight-bold">{{ report.title }}</h1>
          <p class="text-body-2 text-medium-emphasis">{{ report.description }}</p>
        </div>

        <div v-if="canExport" class="d-flex flex-wrap ga-2">
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

      <v-alert v-if="tooLargeForPdf" type="info" variant="tonal" density="compact" class="mb-4">
        More than {{ pdfRowLimit.toLocaleString() }} rows: narrow the filters for PDF/Print, or use Excel.
      </v-alert>

      <!-- Filters -->
      <v-card v-if="filterSchema.length" variant="outlined" class="rounded-lg mb-4 pa-3">
        <v-row density="compact">
          <v-col v-for="f in filterSchema" :key="f.key" cols="12" sm="6" md="3">
            <v-text-field
              v-if="f.type === 'text'"
              v-model="form[f.key]"
              :label="f.label"
              variant="outlined"
              density="compact"
              clearable
              :error-messages="errors[f.key]"
              @keyup.enter="run"
            />
            <v-text-field
              v-else-if="f.type === 'date'"
              v-model="form[f.key]"
              type="date"
              :label="f.required ? `${f.label} *` : f.label"
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
            <v-btn variant="text" @click="reset">Reset</v-btn>
            <v-btn color="primary" variant="flat" prepend-icon="mdi-play" @click="run">Run Report</v-btn>
          </v-col>
        </v-row>
      </v-card>

      <v-alert
        v-for="(note, i) in notes"
        :key="i"
        type="info"
        variant="tonal"
        density="compact"
        class="mb-2"
      >
        {{ note }}
      </v-alert>

      <!-- Summary sections -->
      <v-row v-if="summary.length" class="mb-2">
        <v-col v-for="section in summary" :key="section.title" cols="12" :md="summary.length > 1 ? 6 : 12" :lg="summary.length > 2 ? 4 : undefined">
          <v-card variant="outlined" class="rounded-lg h-100">
            <v-card-title class="text-subtitle-2 font-weight-bold">{{ section.title }}</v-card-title>
            <v-table density="compact">
              <thead>
                <tr><th v-for="c in section.columns" :key="c">{{ c }}</th></tr>
              </thead>
              <tbody>
                <tr v-if="!section.rows.length">
                  <td :colspan="section.columns.length" class="text-center text-medium-emphasis py-3">No data.</td>
                </tr>
                <tr v-for="(row, i) in section.rows" :key="i">
                  <td v-for="(cell, j) in row" :key="j">{{ cell }}</td>
                </tr>
              </tbody>
            </v-table>
          </v-card>
        </v-col>
      </v-row>

      <!-- Rows -->
      <v-card v-if="rows" variant="outlined" class="rounded-lg">
        <div class="d-flex align-center justify-space-between px-4 py-2">
          <span class="text-body-2 text-medium-emphasis">
            {{ rows.total.toLocaleString() }} {{ rows.total === 1 ? "record" : "records" }}
          </span>
          <v-select
            v-model="perPage"
            :items="perPageOptions"
            label="Rows per page"
            variant="plain"
            density="compact"
            hide-details
            style="max-width: 140px"
            @update:model-value="run"
          />
        </div>
        <div class="report-table">
          <v-table density="compact" hover>
            <thead>
              <tr>
                <th
                  v-for="(label, key) in columns"
                  :key="key"
                  class="text-no-wrap"
                  :class="{ 'cursor-pointer': sortable.includes(key) }"
                  @click="sortBy(key)"
                >
                  {{ label }}
                  <v-icon v-if="filters.sort === key" size="x-small" :icon="filters.direction === 'desc' ? 'mdi-arrow-down' : 'mdi-arrow-up'" />
                </th>
              </tr>
            </thead>
            <tbody>
              <tr v-if="!rows.data.length">
                <td :colspan="Object.keys(columns).length" class="text-center text-medium-emphasis py-6">
                  No records match the selected filters.
                </td>
              </tr>
              <tr v-for="(row, i) in rows.data" :key="i">
                <td v-for="(label, key) in columns" :key="key">{{ display(row[key]) }}</td>
              </tr>
            </tbody>
          </v-table>
        </div>
        <div class="pa-3">
          <Pagination :meta="rows" />
        </div>
      </v-card>
    </v-container>
  </SidebarLayout>
</template>

<script>
import { Head, router } from "@inertiajs/vue3";
import SidebarLayout from "@/layouts/SidebarLayout.vue";
import Pagination from "@/components/Pagination.vue";

export default {
  name: "ReportShow",
  components: { SidebarLayout, Head, Pagination },
  props: {
    report: { type: Object, required: true },
    filterSchema: { type: Array, default: () => [] },
    // Applied filters (validated, defaults filled in by the server).
    filters: { type: Object, default: () => ({}) },
    columns: { type: Object, default: () => ({}) },
    sortable: { type: Array, default: () => [] },
    rows: { type: Object, default: null },
    summary: { type: Array, default: () => [] },
    notes: { type: Array, default: () => [] },
    pdfRowLimit: { type: Number, default: 2000 },
    perPageOptions: { type: Array, default: () => [25] },
    canExport: { type: Boolean, default: false },
  },
  data() {
    return {
      form: this.initialForm(),
      perPage: Number(new URLSearchParams(window.location.search).get("per_page")) || 25,
    };
  },
  computed: {
    errors() {
      return this.$page.props.errors || {};
    },
    canCatalog() {
      return (this.$page.props.auth?.permissions || []).includes("report.view");
    },
    tooLargeForPdf() {
      return !!this.rows && this.rows.total > this.pdfRowLimit;
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
    query(extra = {}) {
      const params = {};
      Object.entries(this.form).forEach(([key, value]) => {
        if (value === null || value === "" || value === undefined) return;
        params[key] = typeof value === "boolean" ? (value ? 1 : 0) : value;
      });
      if (this.filters.sort) {
        params.sort = this.filters.sort;
        params.direction = this.filters.direction || "asc";
      }
      return { ...params, ...extra };
    },
    run() {
      this.visit(route(`reports.${this.report.key}.show`, this.query({ per_page: this.perPage })));
    },
    reset() {
      this.visit(route(`reports.${this.report.key}.show`));
    },
    sortBy(key) {
      if (!this.sortable.includes(key)) return;
      const direction = this.filters.sort === key && this.filters.direction !== "desc" ? "desc" : "asc";
      this.visit(route(`reports.${this.report.key}.show`, { ...this.query(), sort: key, direction, per_page: this.perPage }));
    },
    // Exports use the filters that produced the table on screen.
    exportUrl(format) {
      const applied = {};
      Object.entries(this.filters).forEach(([key, value]) => {
        if (value === null || value === "" || value === undefined) return;
        applied[key] = typeof value === "boolean" ? (value ? 1 : 0) : value;
      });
      return route(`reports.${this.report.key}.${format}`, applied);
    },
    visit(url) {
      router.visit(url, { preserveScroll: true });
    },
    display(value) {
      return value === null || value === undefined || value === "" ? "—" : value;
    },
  },
};
</script>

<style scoped>
.report-table {
  overflow-x: auto;
}
.cursor-pointer {
  cursor: pointer;
}
</style>
