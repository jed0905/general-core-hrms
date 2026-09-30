<template>
  <SidebarLayout>
    <Head title="Leave Balance" />

    <v-container fluid class="pa-4 pa-sm-6">
      <div
        class="d-flex flex-column flex-sm-row align-sm-center justify-space-between ga-4 mb-6"
      >
        <div>
          <h1 class="text-h5 font-weight-bold">Leave Balance</h1>
          <p class="text-body-2 text-medium-emphasis">
            Available days are your balance minus days waiting for approval.
          </p>
        </div>
        <v-btn
          v-if="hasEmployeeRecord && can('leave.create')"
          color="primary"
          prepend-icon="mdi-plus"
          elevation="0"
          @click="apply"
        >
          Apply Leave
        </v-btn>
      </div>

      <v-alert v-if="!hasEmployeeRecord" type="info" variant="tonal">
        Your account is not linked to an employee record. Contact HR if this is
        unexpected.
      </v-alert>

      <v-alert v-else-if="!balances.length" type="info" variant="tonal">
        No leave balances have been set up for you yet. Contact HR.
      </v-alert>

      <v-card v-else variant="outlined" class="rounded-lg">
        <v-table>
          <thead>
            <tr>
              <th class="font-weight-bold">Leave Type</th>
              <th class="text-end font-weight-bold">Balance</th>
              <th class="text-end font-weight-bold">Pending</th>
              <th class="text-end font-weight-bold">Available</th>
              <th class="text-end font-weight-bold">Used</th>
              <th class="font-weight-bold">As of</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="b in balances" :key="b.id">
              <td>
                <span class="font-weight-medium">{{ b.leave_type?.name }}</span>
                <v-chip size="x-small" variant="outlined" class="ml-2">
                  {{ b.leave_type?.code }}
                </v-chip>
              </td>
              <td class="text-end">{{ b.balance }}</td>
              <td class="text-end">{{ b.pending }}</td>
              <td class="text-end font-weight-bold">{{ b.available }}</td>
              <td class="text-end">{{ b.used }}</td>
              <td>{{ formatDate(b.as_of_date) }}</td>
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
import permissions from "@/mixins/permissions";

export default {
  name: "MyLeaveBalancesIndex",
  components: { SidebarLayout, Head },
  mixins: [permissions],
  props: {
    hasEmployeeRecord: { type: Boolean, default: true },
    // Computed by the server (LeaveApplicationService::getBalanceSummary).
    balances: { type: Array, default: () => [] },
  },
  methods: {
    apply() {
      router.visit(route("leave.applications.create"));
    },
    formatDate(value) {
      return value
        ? new Date(`${String(value).substring(0, 10)}T00:00:00`).toLocaleDateString()
        : "—";
    },
  },
};
</script>
