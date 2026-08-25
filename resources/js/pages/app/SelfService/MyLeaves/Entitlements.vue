<template>
  <MyLeavesTabs v-model:activeTab="activeTab" :tabs="tabs" />

  <TableWrapper>
    <div class="d-flex align-center justify-space-between mb-3">
      <div class="v-card-title text-h6">Regular Leaves Entitlements</div>

      <Link :href="route('self-service.my-leaves.leaveLedger')">
        <v-btn
          color="primary"
          prepend-icon="mdi-book-open-page-variant"
          rounded="lg"
        >
          View Leave Ledger
        </v-btn>
      </Link>
    </div>

    <v-divider></v-divider>

    <div class="d-flex align-center mb-3 mt-3 ml-3">
      <p class="text-h6">({{ totalLeaveType }}) Record Found</p>
    </div>

    <v-table>
      <thead>
        <tr>
          <th>Leave Type</th>
          <th class="text-center">Total Balance</th>
        </tr>
      </thead>

      <tbody>
        <tr
          v-for="employeeLeaveCredit in employeeLeaveCreditsHistory"
          :key="employeeLeaveCredit.id"
        >
          <td>{{ employeeLeaveCredit.leave_type.name }}</td>
          <td class="text-center">
            {{ employeeLeaveCredit.total_balance }}
          </td>
        </tr>
      </tbody>
    </v-table>
  </TableWrapper>

  <TableWrapper>
    <div class="v-card-title text-h6">Special Leaves Entitlements</div>
    <v-table>
      <thead>
        <tr>
          <th>Leave</th>
          <th>Document Control Number</th>
          <th>Valid From</th>
          <th>Valid Until</th>
          <th class="text-center">Balance</th>
          <th class="text-center">Expired ?</th>
        </tr>
      </thead>
      <tbody>
        <tr
          v-for="(
            specialEmployeeEntitlement, i
          ) in specialLeaveCreditsHistory.data"
          :key="i"
        >
          <td>{{ specialEmployeeEntitlement.special_leave.name }}</td>
          <td>{{ specialEmployeeEntitlement.document_type_number }}</td>
          <td>
            {{
              new Date(
                specialEmployeeEntitlement.expiration_date_from
              ).toLocaleDateString("en-US", {
                month: "long",
                day: "numeric",
                year: "numeric",
              })
            }}
          </td>
          <td>
            {{
              new Date(
                specialEmployeeEntitlement.expiration_date_to
              ).toLocaleDateString("en-US", {
                month: "long",
                day: "numeric",
                year: "numeric",
              })
            }}
          </td>
          <td class="text-center">{{ specialEmployeeEntitlement.balance }}</td>
          <td class="text-center">
            {{ specialEmployeeEntitlement.is_expired ? "Yes" : "No" }}
          </td>
        </tr>
      </tbody>
    </v-table>
  </TableWrapper>

  <!-- <pre>{{ employeeLeaveCreditsHistory }}</pre> -->
</template>
<script>
import SidebarLayout from "@/layouts/SidebarLayout.vue";
import TableWrapper from "@/components/TableWrapper.vue";
import DynamicTabs from "@/components/DynamicTabs.vue";
import FilterWrapper from "@/components/FilterWrapper.vue";
import ButtonMuted from "@/components/ButtonMuted.vue";
import ButtonSuccess from "@/components/ButtonSuccess.vue";
import MyLeavesTabs from "@/components/MyLeavesTabs.vue";

export default {
  layout: SidebarLayout,
  components: {
    TableWrapper,
    DynamicTabs,
    FilterWrapper,
    ButtonMuted,
    ButtonSuccess,
    MyLeavesTabs,
  },

  props: {
    employeeLeaveCreditsHistory: Object,
    totalLeaveCredits: Number,
    totalLeaveType: Number,
    specialLeaveCreditsHistory: Object,
    printUrl: String,
  },
  data() {
    return {
      activeTab: "entitlements",

      isPanelOpen: 0,
    };
  },

  methods: {
    printLedger() {
      window.open(this.printUrl, "_blank");
    },
  },
};
</script>
