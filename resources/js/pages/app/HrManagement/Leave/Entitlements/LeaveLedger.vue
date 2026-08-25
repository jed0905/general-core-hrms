<template>
  <MyLeavesTabs
    v-if="$page.url.startsWith('/self-service')"
    v-model:activeTab="activeTab"
  />

  <LeaveManagementTabs v-else v-model:activeTab="activeTab" />
  <v-card rounded="lg" elevation="2">
    <v-card-title class="d-flex justify-space-between align-center">
      <span class="text-h6">Leave Ledger</span>
      <v-select
        v-model="selectedYear"
        :items="years"
        label="Year"
        density="compact"
        variant="outlined"
        hide-details
        style="max-width: 120px"
        @update:model-value="changeYear"
      />

      <v-btn
        v-if="!$page.url.startsWith('/self-service')"
        color="primary"
        prepend-icon="mdi-printer"
        @click="printLedger"
      >
        Print
      </v-btn>
    </v-card-title>

    <v-card-text class="pa-5">
      <!-- Employee / Non-Teaching / Faculty with designation -->
      <template v-if="leaveCreditHistories.data[0]?.is_vsl">
        <table class="leave-ledger">
          <thead>
            <tr>
              <th colspan="6" class="text-left">
                Name: {{ leaveCreditHistories.data[0]?.full_name_asc ?? "N/A" }}
              </th>
              <th colspan="4" class="text-left">
                Office/Division:
                {{ leaveCreditHistories.data[0]?.office_division ?? "N/A" }}
              </th>
              <th class="text-left">
                1st Day of Service:
                {{
                  leaveCreditHistories.data[0]?.date_hired
                    ? new Date(
                        leaveCreditHistories.data[0].date_hired
                      ).toLocaleDateString("en-US", {
                        month: "short",
                        day: "numeric",
                        year: "numeric",
                      })
                    : "N/A"
                }}
              </th>
            </tr>
            <tr>
              <th rowspan="2">PERIOD</th>
              <th rowspan="2">PARTICULARS</th>

              <th colspan="4">VACATION LEAVE</th>

              <th colspan="4">SICK LEAVE</th>

              <th rowspan="2">
                DATE &amp; ACTION TAKEN ON<br />
                APPLICATION FOR LEAVE
              </th>
            </tr>

            <tr>
              <th>EARNED</th>
              <th>ABS/UND W/P</th>
              <th>BALANCE</th>
              <th>ABS/UND WOP</th>

              <th>EARNED</th>
              <th>ABS/UND W/P</th>
              <th>BALANCE</th>
              <th>ABS/UND WOP</th>
            </tr>
          </thead>

          <tbody>
            <tr v-for="history in paginatedHistories" :key="history.id">
              <td>
                {{
                  new Date(history.created_at).toLocaleString("en-US", {
                    month: "long",
                    year: "numeric",
                  })
                }}
              </td>

              <td class="text-center">{{ history.particulars }}</td>

              <!-- Vacation Leave -->
              <td>
                {{
                  history.leave_id == 1 && Number(history.credit_addition) !== 0
                    ? history.credit_addition
                    : ""
                }}
              </td>

              <td>
                {{
                  history.leave_id == 1 &&
                  Number(history.credit_deduction) !== 0
                    ? history.credit_deduction
                    : ""
                }}
              </td>

              <td>
                {{
                  history.leave_id == 1 && Number(history.balance) !== 0
                    ? history.balance
                    : ""
                }}
              </td>

              <td>{{ history.vl_abs_wop }}</td>

              <!-- Sick Leave -->
              <td>
                {{
                  history.leave_id == 3 && Number(history.credit_addition) !== 0
                    ? history.credit_addition
                    : ""
                }}
              </td>

              <td>
                {{
                  history.leave_id == 3 &&
                  Number(history.credit_deduction) !== 0
                    ? history.credit_deduction
                    : ""
                }}
              </td>

              <td>
                {{
                  history.leave_id == 3 && Number(history.balance) !== 0
                    ? history.balance
                    : ""
                }}
              </td>

              <td>{{ history.sl_abs_wop }}</td>

              <td>{{ history.action_taken }}</td>
            </tr>

            <tr v-if="leaveHistories.length === 0">
              <td colspan="11" class="text-center py-4">
                No leave ledger records found.
              </td>
            </tr>
          </tbody>
        </table>
      </template>

      <!-- Faculty without designation -->
      <template v-else>
        <table class="faculty-header">
          <tr>
            <td>
              <div class="label">
                {{ leaveCreditHistories.data[0]?.full_name_asc ?? "Name" }}
              </div>
              <div class="line"></div>
              <div class="caption">Name</div>
            </td>

            <td>
              <div class="label">
                {{ leaveCreditHistories.data[0]?.designation ?? "N/A" }}
              </div>
              <div class="line"></div>
              <div class="caption">Designation</div>
            </td>

            <td>
              <div class="label">
                {{
                  leaveCreditHistories.data[0]?.date_hired
                    ? new Date(
                        leaveCreditHistories.data[0].date_hired
                      ).toLocaleDateString("en-US", {
                        month: "long",
                        day: "numeric",
                        year: "numeric",
                      })
                    : ""
                }}
              </div>
              <div class="line"></div>
              <div class="caption">Date of Appointment</div>
            </td>
          </tr>
        </table>

        <table class="leave-ledger">
          <thead>
            <tr>
              <th>INCLUSIVE DATE</th>
              <th>NO. OF DAYS</th>

              <th>
                SERVICE<br />
                CREDIT<br />
                EARNED
              </th>

              <th>BALANCE</th>

              <th>INCLUSIVE DATE</th>
              <th>NO. OF DAYS</th>

              <th>
                SERVICE<br />
                CREDIT<br />
                EARNED
              </th>

              <th>BALANCE</th>
            </tr>
          </thead>

          <tbody>
            <tr v-for="(row, index) in facultyPaginatedHistories" :key="index">
              <!-- LEFT -->
              <td>{{ row.left?.inclusive_date ?? "" }}</td>
              <td>
                {{
                  row.left && Number(row.left.credit_deduction) !== 0
                    ? row.left.credit_deduction
                    : ""
                }}
              </td>
              <td>
                {{
                  row.left && Number(row.left.credit_addition) !== 0
                    ? row.left.credit_addition
                    : ""
                }}
              </td>
              <td v-html="row.left?.balance || '&nbsp;'"></td>

              <!-- RIGHT -->
              <td>{{ row.right?.inclusive_date ?? "" }}</td>
              <td>
                {{
                  row.right && Number(row.right.credit_deduction) !== 0
                    ? row.right.credit_deduction
                    : ""
                }}
              </td>
              <td>
                {{
                  row.right && Number(row.right.credit_addition) !== 0
                    ? row.right.credit_addition
                    : ""
                }}
              </td>
              <td v-html="row.right?.balance || '&nbsp;'"></td>
            </tr>

            <tr v-if="facultyPaginatedHistories.length === 0">
              <td colspan="11" class="text-center py-4">
                No leave ledger records found.
              </td>
            </tr>
          </tbody>
        </table>
      </template>
    </v-card-text>

    <v-card-text class="pa-5">
      <div class="pagination">
        <v-btn
          variant="outlined"
          size="small"
          :disabled="currentPage === 1"
          @click="previousPage"
        >
          Previous
        </v-btn>

        <span class="page-info">
          Page {{ currentPage }} of {{ totalPages }}
        </span>

        <v-btn
          variant="outlined"
          size="small"
          :disabled="currentPage === totalPages"
          @click="nextPage"
        >
          Next
        </v-btn>
      </div>
    </v-card-text>
  </v-card>
  <!-- <pre>{{ leaveCreditHistories.data }}</pre> -->
</template>

<script>
import SidebarLayout from "@/layouts/SidebarLayout.vue";
import LeaveManagementTabs from "@/components/LeaveManagementTabs.vue";
import MyLeavesTabs from "@/components/MyLeavesTabs.vue";
import TableWrapper from "@/components/TableWrapper.vue";
import { useForm } from "@inertiajs/vue3";

export default {
  layout: SidebarLayout,

  components: {
    LeaveManagementTabs,
    MyLeavesTabs,
    TableWrapper,
  },

  props: {
    leaveCreditHistories: Object,
    year: Number,
    leave_ledger_print_url: String,
  },

  data() {
    const currentYear = new Date().getFullYear();

    return {
      activeTab: "entitlements",
      currentPage: 1,
      rowsPerPage: 20,

      years: Array.from({ length: 11 }, (_, i) => currentYear - i),

      selectedYear: this.year ?? currentYear,
    };
  },

  computed: {
    availableYears() {
      const current = new Date().getFullYear();

      return Array.from({ length: 10 }, (_, i) => current - i);
    },

    leaveHistories() {
      return this.leaveCreditHistories.data[0]?.leave_credits_history ?? [];
    },

    paginatedHistories() {
      const start = (this.currentPage - 1) * this.rowsPerPage;
      const end = start + this.rowsPerPage;

      return this.leaveHistories.slice(start, end);
    },

    // NEW - for faculty layout
    facultyPaginatedHistories() {
      const start = (this.currentPage - 1) * 40;

      const records = this.leaveHistories.slice(start, start + 40);

      return Array.from({ length: 20 }, (_, i) => ({
        left: records[i] ?? null,
        right: records[i + 20] ?? null,
      }));
    },

    totalPages() {
      return Math.ceil(this.leaveHistories.length / this.rowsPerPage) || 1;
    },
  },

  mounted() {
    // Load your ledger here
    // this.fetchLeaveLedger();
  },

  methods: {
    changeYear() {
      const isSelfService =
        window.location.pathname.startsWith("/self-service");

      const url = isSelfService
        ? route("self-service.my-leaves.leaveLedger")
        : route("hrmanagement.leave.viewEmployeeLeaveLedger", {
            id: this.leaveCreditHistories.data[0].employee_id,
          });

      this.$inertia.get(url, {
        year: this.selectedYear,
      });
    },

    fetchLeaveLedger() {
      // Example:
      // axios.get(...).then(response => {
      //     this.leaveCreditHistories = response.data;
      // });
    },

    printLedger() {
      const employeeId = this.leaveCreditHistories.data[0].employee_id;
      window.open(
        route("hrmanagement.leave.ledger.print", employeeId) +
          "?year=" +
          this.selectedYear,
        "_blank"
      );
    },

    nextPage() {
      if (this.currentPage < this.totalPages) {
        this.currentPage++;
      }
    },

    previousPage() {
      if (this.currentPage > 1) {
        this.currentPage--;
      }
    },
  },
};
</script>

<style scoped>
.leave-ledger {
  width: 100%;
  border-collapse: collapse;
  font-size: 13px;
}

.leave-ledger th,
.leave-ledger td {
  border: 1px solid #000;
  padding: 6px;
  text-align: center;
  vertical-align: middle;
}

.leave-ledger th {
  background: #f5f5f5;
  font-weight: 600;
}

.leave-ledger td:nth-child(2),
.leave-ledger td:last-child {
  text-align: left;
}

.text-center {
  text-align: center;
}

.pagination {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 20px;
  margin-top: 16px;
}

.page-info {
  font-size: 14px;
}

.service-credit-ledger {
  width: 100%;
  border-collapse: collapse;
  table-layout: fixed;
}

.service-credit-ledger th,
.service-credit-ledger td {
  border: 1px solid #000;
  text-align: center;
  vertical-align: middle;
  padding: 2px 4px;
}

.service-credit-ledger th {
  font-size: 12px;
  font-weight: 600;
  line-height: 1.1;
  white-space: normal;
}

.service-credit-ledger tbody tr {
  height: 32px;
}

.service-credit-ledger tbody td {
  height: 32px;
  line-height: 32px; /* vertically center single-line text */
  overflow: hidden;
}

.faculty-header {
  width: 100%;
  border-collapse: collapse;
  margin-bottom: 20px;
}

.faculty-header td {
  width: 33.33%;
  text-align: center;
  border: none;
  padding: 0 20px;
  vertical-align: bottom;
}

.faculty-header .line {
  border-bottom: 1px solid #000;
  height: 24px;
}

.faculty-header .label {
  margin-top: -18px;
  background: #fff;
  display: inline-block;
  padding: 0 8px;
  font-weight: 600;
}

.faculty-header .caption {
  margin-top: 6px;
  font-size: 12px;
}
</style>
