<template>
  <SidebarLayout>
    <Head title="Leave History" />

    <v-container fluid class="pa-4 pa-sm-6">
      <div class="mb-6">
        <h1 class="text-h5 font-weight-bold">Leave History</h1>
        <p class="text-body-2 text-medium-emphasis">
          Your approved, rejected and cancelled leave applications.
        </p>
      </div>

      <v-alert v-if="!hasEmployeeRecord" type="info" variant="tonal">
        Your account is not linked to an employee record. Contact HR if this is
        unexpected.
      </v-alert>

      <template v-else>
        <v-row density="compact" class="mb-2">
          <v-col cols="12" sm="4" md="3">
            <v-select
              v-model="status"
              :items="statusOptions"
              label="Status"
              variant="outlined"
              density="compact"
              hide-details
              @update:model-value="applyFilters"
            />
          </v-col>
          <v-col cols="12" sm="4" md="3">
            <v-select
              v-model="leaveTypeId"
              :items="leaveTypeOptions"
              label="Leave Type"
              variant="outlined"
              density="compact"
              hide-details
              @update:model-value="applyFilters"
            />
          </v-col>
        </v-row>

        <v-card variant="outlined" class="rounded-lg">
          <v-table hover>
            <thead>
              <tr>
                <th class="font-weight-bold">Leave Type</th>
                <th class="font-weight-bold">Dates</th>
                <th class="font-weight-bold">Duration</th>
                <th class="font-weight-bold">Status</th>
                <th class="text-end font-weight-bold">Details</th>
              </tr>
            </thead>
            <tbody>
              <tr v-if="!history.data?.length">
                <td colspan="5" class="text-center py-6 text-medium-emphasis">
                  No leave history found.
                </td>
              </tr>
              <tr v-for="app in history.data" :key="app.id">
                <td class="font-weight-medium">{{ app.leave_type?.name }}</td>
                <td>{{ dateRange(app.dates) }}</td>
                <td>{{ app.total_days }} day(s)</td>
                <td>
                  <v-chip :color="statusColor(app.status)" size="small" variant="tonal">
                    {{ app.status }}
                  </v-chip>
                </td>
                <td class="text-end">
                  <v-btn
                    v-if="app.can?.view"
                    icon="mdi-eye-outline"
                    variant="text"
                    size="small"
                    color="info"
                    @click="view(app)"
                  />
                </td>
              </tr>
            </tbody>
          </v-table>
        </v-card>

        <div class="mt-4">
          <Pagination :meta="history" />
        </div>
      </template>
    </v-container>
  </SidebarLayout>
</template>

<script>
import { Head, router } from "@inertiajs/vue3";
import SidebarLayout from "@/layouts/SidebarLayout.vue";
import Pagination from "@/components/Pagination.vue";

export default {
  name: "MyLeaveHistoryIndex",
  components: { SidebarLayout, Head, Pagination },
  props: {
    hasEmployeeRecord: { type: Boolean, default: true },
    history: { type: Object, default: () => ({ data: [] }) },
    leaveTypes: { type: Array, default: () => [] },
    filters: { type: Object, default: () => ({}) },
  },
  data() {
    return {
      status: this.filters.status ?? null,
      leaveTypeId: this.filters.leave_type_id ? Number(this.filters.leave_type_id) : null,
      statusOptions: [
        { title: "All", value: null },
        { title: "Approved", value: "approved" },
        { title: "Rejected", value: "rejected" },
        { title: "Cancelled", value: "cancelled" },
      ],
    };
  },
  computed: {
    leaveTypeOptions() {
      return [
        { title: "All", value: null },
        ...this.leaveTypes.map((t) => ({ title: t.name, value: t.id })),
      ];
    },
  },
  methods: {
    applyFilters() {
      router.get(
        route("leave.applications.history"),
        { status: this.status || undefined, leave_type_id: this.leaveTypeId || undefined },
        { preserveState: true, preserveScroll: true }
      );
    },
    view(app) {
      router.visit(route("leave.applications.show", { leaveApplication: app.id }));
    },
    formatDate(value) {
      return value
        ? new Date(`${String(value).substring(0, 10)}T00:00:00`).toLocaleDateString(undefined, { month: "short", day: "numeric", year: "numeric" })
        : "";
    },
    dateRange(dates) {
      if (!dates?.length) return "—";
      const first = this.formatDate(dates[0].leave_date);
      const last = this.formatDate(dates[dates.length - 1].leave_date);
      return first === last ? first : `${first} – ${last}`;
    },
    statusColor(status) {
      return (
        { approved: "success", rejected: "error", cancelled: "grey" }[status] || "default"
      );
    },
  },
};
</script>
