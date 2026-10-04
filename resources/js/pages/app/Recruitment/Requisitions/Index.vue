<template>
  <SidebarLayout>
    <Head title="Job Requisitions" />
    <v-container fluid class="pa-6">
      <div class="d-flex align-center justify-space-between mb-4">
        <div>
          <h1 class="text-h5 font-weight-bold">Job Requisitions</h1>
          <p class="text-body-2 text-medium-emphasis">Requests to hire, from draft through approval.</p>
        </div>
        <v-btn v-if="can.create" color="primary" prepend-icon="mdi-plus" :href="route('recruitment.requisitions.create')" @click.prevent="go(route('recruitment.requisitions.create'))">
          New requisition
        </v-btn>
      </div>

      <v-card variant="outlined" class="rounded-lg mb-4 pa-4">
        <v-row density="compact">
          <v-col cols="12" md="4">
            <v-text-field v-model="form.search" label="Search number or job title" prepend-inner-icon="mdi-magnify" variant="outlined" density="compact" hide-details clearable @keyup.enter="apply" @click:clear="clearSearch" />
          </v-col>
          <v-col cols="12" md="3">
            <v-select v-model="form.status" :items="statusItems" label="Status" variant="outlined" density="compact" hide-details clearable @update:model-value="apply" />
          </v-col>
          <v-col cols="12" md="5">
            <v-autocomplete v-model="form.department_id" :items="departments" item-title="title" item-value="value" label="Department" variant="outlined" density="compact" hide-details clearable @update:model-value="apply" />
          </v-col>
        </v-row>
      </v-card>

      <v-card variant="outlined" class="rounded-lg">
        <v-table density="comfortable">
          <thead>
            <tr>
              <th>Number</th>
              <th>Position</th>
              <th>Department</th>
              <th class="text-center">Positions</th>
              <th>Requested by</th>
              <th>Status</th>
              <th class="text-end">Details</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="!requisitions.data.length">
              <td colspan="7" class="text-center text-medium-emphasis pa-6">No requisitions found.</td>
            </tr>
            <tr v-for="req in requisitions.data" :key="req.id">
              <td class="font-weight-medium text-no-wrap">{{ req.requisition_number }}</td>
              <td>
                {{ req.job_title?.job_title }}
                <div class="text-caption text-medium-emphasis">{{ reasonLabel(req.reason) }}</div>
              </td>
              <td>{{ req.department?.name }}</td>
              <td class="text-center">
                {{ req.positions }}
                <div v-if="req.status === 'approved'" class="text-caption text-medium-emphasis">{{ req.allocated_openings || 0 }} allocated</div>
              </td>
              <td>{{ personName(req.requested_by) }}</td>
              <td>
                <v-chip size="small" variant="tonal" :color="status(req.status).color">{{ status(req.status).label }}</v-chip>
              </td>
              <td class="text-end">
                <v-btn icon="mdi-eye-outline" size="small" variant="text" :href="route('recruitment.requisitions.show', req.id)" @click.prevent="go(route('recruitment.requisitions.show', req.id))" />
              </td>
            </tr>
          </tbody>
        </v-table>
        <div class="pa-3"><Pagination :meta="requisitions" /></div>
      </v-card>
    </v-container>
  </SidebarLayout>
</template>

<script>
import { Head, router } from "@inertiajs/vue3";
import SidebarLayout from "@/layouts/SidebarLayout.vue";
import Pagination from "@/components/Pagination.vue";
import { REQUISITION_STATUS, REASON_LABELS, statusInfo, personName } from "@/utils/recruitment";

export default {
  name: "RequisitionIndex",
  components: { SidebarLayout, Head, Pagination },
  props: {
    requisitions: { type: Object, required: true },
    filters: { type: Object, default: () => ({}) },
    statuses: { type: Array, default: () => [] },
    departments: { type: Array, default: () => [] },
    can: { type: Object, default: () => ({}) },
  },
  data() {
    return {
      form: {
        search: this.filters.search || "",
        status: this.filters.status || null,
        department_id: this.filters.department_id ? Number(this.filters.department_id) : null,
      },
    };
  },
  computed: {
    statusItems() {
      return this.statuses.map((s) => ({ value: s, title: statusInfo(REQUISITION_STATUS, s).label }));
    },
  },
  methods: {
    status(value) {
      return statusInfo(REQUISITION_STATUS, value);
    },
    reasonLabel(value) {
      return REASON_LABELS[value] || value;
    },
    personName,
    apply() {
      router.get(route("recruitment.requisitions.index"), this.form, { preserveState: true, preserveScroll: true, replace: true });
    },
    clearSearch() {
      this.form.search = "";
      this.apply();
    },
    go(url) {
      router.visit(url);
    },
  },
};
</script>
