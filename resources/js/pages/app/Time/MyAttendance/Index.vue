<template>
  <SidebarLayout>
    <Head title="My Attendance" />

    <v-container fluid class="pa-4 pa-sm-6">
      <div
        class="d-flex flex-column flex-sm-row align-sm-center justify-space-between ga-4 mb-6"
      >
        <div>
          <h1 class="text-h5 font-weight-bold">My Attendance</h1>
          <p class="text-body-2 text-medium-emphasis">
            Your time-in and time-out records from the attendance devices.
          </p>
        </div>

        <v-btn
          v-if="can.export"
          :href="exportUrl"
          color="primary"
          variant="outlined"
          prepend-icon="mdi-download"
        >
          Export CSV
        </v-btn>
      </div>

      <v-alert v-if="!hasEmployeeRecord" type="info" variant="tonal">
        Your account is not linked to an employee record. Contact HR if this is
        unexpected.
      </v-alert>

      <template v-else>
        <v-alert v-if="!hasBiometricId" type="info" variant="tonal" class="mb-4">
          No attendance device ID is registered for you yet, so no records can
          be shown. Contact HR.
        </v-alert>

        <v-row density="compact" class="mb-2">
          <v-col cols="12" sm="4" md="3">
            <v-text-field
              v-model="from"
              type="date"
              label="From"
              variant="outlined"
              density="compact"
              hide-details
            />
          </v-col>
          <v-col cols="12" sm="4" md="3">
            <v-text-field
              v-model="to"
              type="date"
              label="To"
              variant="outlined"
              density="compact"
              hide-details
            />
          </v-col>
          <v-col cols="12" sm="4" md="3">
            <v-btn color="primary" variant="flat" @click="applyFilters">Apply</v-btn>
          </v-col>
        </v-row>

        <v-card variant="outlined" class="rounded-lg">
          <v-table density="comfortable">
            <thead>
              <tr>
                <th>Date</th>
                <th>Time</th>
                <th>Direction</th>
                <th>Device</th>
              </tr>
            </thead>
            <tbody>
              <tr v-if="!logs?.data?.length">
                <td colspan="4" class="text-center py-6 text-medium-emphasis">
                  No attendance records for this period.
                </td>
              </tr>
              <tr v-for="log in logs?.data || []" :key="log.id">
                <td>{{ formatDate(log.auth_date) }}</td>
                <td>{{ log.auth_time }}</td>
                <td>{{ log.direction }}</td>
                <td>{{ log.device_name }}</td>
              </tr>
            </tbody>
          </v-table>
        </v-card>

        <div class="mt-4">
          <Pagination :meta="logs || {}" />
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
  name: "MyAttendanceIndex",
  components: { SidebarLayout, Head, Pagination },
  props: {
    hasEmployeeRecord: { type: Boolean, default: true },
    hasBiometricId: { type: Boolean, default: false },
    logs: { type: Object, default: null },
    filters: { type: Object, default: () => ({}) },
    can: { type: Object, default: () => ({}) },
  },
  data() {
    return {
      from: this.filters.from ?? "",
      to: this.filters.to ?? "",
    };
  },
  computed: {
    exportUrl() {
      return route("time.my-attendance.export", { from: this.from, to: this.to });
    },
  },
  methods: {
    applyFilters() {
      router.get(
        route("time.my-attendance.index"),
        { from: this.from || undefined, to: this.to || undefined },
        { preserveState: true, preserveScroll: true }
      );
    },
    formatDate(value) {
      return value ? new Date(value).toLocaleDateString() : "";
    },
  },
};
</script>
