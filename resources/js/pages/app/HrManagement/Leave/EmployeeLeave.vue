/* View Employee Entitlements */
/*
  This Allows user to view employee entitlements per employee
*/
<template>
  <!-- <pre>{{ employeeEntitlements }}</pre> -->
  <LeaveManagementTabs v-model:activeTab="activeTab" />

  <v-card class="mb-4" rounded="lg" elevation="2">
    <v-card-text>
      <v-row align="center">
        <v-col cols="12" md="5">
          <div class="text-subtitle-1 font-weight-bold mb-2">
            Employee Information
          </div>
          <div class="mb-1">
            <span class="font-weight-medium">Name:</span>
            {{ employee.personal_information.firstname }}
            {{ employee.personal_information.middlename ?? "" }}
            {{ employee.personal_information.lastname }}
            {{ employee.personal_information.suffix ?? "" }}
          </div>
          <div class="mb-1">
            <span class="font-weight-medium">Employee ID:</span>
            {{ employee.employee_number }}
          </div>
          <div>
            <span class="font-weight-medium">Position:</span>
            {{ employee.position?.government_position.name }}
          </div>
        </v-col>

        <v-col cols="12" md="5">
          <div class="text-subtitle-1 font-weight-bold mb-2">&nbsp;</div>
          <div class="mb-1">
            <span class="font-weight-medium">Department:</span>
            {{ employee.department?.name }}
          </div>
          <div>
            <span class="font-weight-medium">Operating Unit:</span>
            {{ employee.operating_unit?.name }}
          </div>
        </v-col>

        <v-col cols="12" md="2" class="d-flex justify-end align-center">
          <Link :href="leave_ledger_link">
            <v-btn color="primary" prepend-icon="mdi-book-open-page-variant">
              View Leave Ledger
            </v-btn>
          </Link>
        </v-col>
      </v-row>
    </v-card-text>
  </v-card>

  <TableWrapper>
    <div class="v-card-title text-h6">Regular Leaves Entitlements</div>
    <v-table>
      <thead>
        <tr>
          <th>Leave Type</th>
          <!-- <th class="text-center">Total Earned</th> -->
          <th class="text-center">Total Balance</th>
          <th class="text-center">Action</th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="(employeeEntitlement, i) in employeeEntitlements" :key="i">
          <td>{{ employeeEntitlement.leave_type.name }}</td>
          <!-- <td class="text-center">{{ employeeEntitlement.total_earned }}</td> -->
          <td class="text-center">{{ employeeEntitlement.total_balance }}</td>
          <td class="text-center">
            <!-- <v-btn
              variant="tonal"
              color="red-darken-1"
              size="x-small"
              icon="mdi-trash-can"
              @click="deleteDialog = true; id = employeeEntitlement.leave_id"
            ></v-btn> -->
            <v-btn
              class="ml-2"
              variant="tonal"
              color="warning"
              size="x-small"
              icon="mdi mdi-minus"
              :title="'Deduct Leave Balance'"
              @click="openEmployeeEntitlementDialog(employeeEntitlement)"
            ></v-btn>
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
          <th class="text-center">Action</th>
        </tr>
      </thead>
      <tbody>
        <tr
          v-for="(
            specialEmployeeEntitlement, i
          ) in specialEmployeeEntitlements.data"
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
          <td class="text-center">
            <v-btn
              class="ml-2"
              variant="tonal"
              color="warning"
              size="x-small"
              icon="mdi mdi-minus"
              :title="'Deduct Leave Balance'"
              @click="
                openSpecialEmployeeEntitlementDialog(specialEmployeeEntitlement)
              "
            ></v-btn>
          </td>
        </tr>
      </tbody>
    </v-table>
  </TableWrapper>

  <v-dialog v-model="dialog" max-width="500">
    <v-form @submit.prevent="handleDeductLeave">
      <v-card class="pa-4 rounded-lg">
        <!-- Title -->
        <v-card-title
          class="d-flex align-center justify-center text-h5 font-weight-bold mb-4"
        >
          <v-icon color="warning" size="large" class="mr-2"
            >mdi-minus-circle</v-icon
          >
          Deduct Leave Balance
        </v-card-title>

        <v-divider class="mb-4"></v-divider>

        <!-- Body -->
        <v-card-text>
          <v-row>
            <v-col cols="12">
              <v-text-field
                v-model="deductForm.value"
                label="Value to Deduct"
                type="number"
                variant="outlined"
                density="compact"
                hide-details
                class="mb-3"
                min="0"
              ></v-text-field>
            </v-col>

            <v-col cols="12">
              <v-select
                v-model="deductForm.credit_origin"
                label="Origin"
                variant="outlined"
                density="compact"
                :items="[
                  { title: 'Leave Application', value: 'leave_application' },
                  { title: 'Undertime', value: 'undertime' },
                  { title: 'Correction', value: 'correction' },
                  { title: 'Monetization', value: 'monetization' },
                  { title: 'Manual Adjustment', value: 'manual_adjustment' },
                ]"
                item-title="title"
                item-value="value"
              />
            </v-col>

            <v-col cols="12">
              <v-card variant="tonal" color="info">
                <v-card-title class="text-subtitle-2 py-2">
                  Origin Legend
                </v-card-title>

                <v-card-text class="text-caption">
                  <strong>Leave Application</strong> – Deduction from approved
                  leave<br />

                  <strong>Undertime</strong> – Deduction for undertime charged
                  to leave credits<br />

                  <strong>Correction</strong> – Correction of an incorrect leave
                  balance or transaction<br />

                  <strong>Monetization</strong> – Deduction due to leave
                  monetization. Indicate in the remarks the period (month/year) of monetization<br />

                  <strong>Manual Adjustment</strong> – Other authorized
                  deductions (specify in Remarks)
                </v-card-text>
              </v-card>
            </v-col>

            <v-col cols="12">
              <v-textarea
                v-model="deductForm.remarks"
                label="Remarks"
                variant="outlined"
                density="compact"
                rows="3"
                auto-grow
                placeholder="Enter remarks..."
              />
            </v-col>
          </v-row>
        </v-card-text>

        <!-- Actions -->
        <v-card-actions class="d-flex justify-end gap-3 pa-0 mt-4">
          <v-btn
            color="grey"
            variant="outlined"
            rounded="xl"
            @click="dialog = false"
            min-width="120"
          >
            Cancel
          </v-btn>

          <v-btn
            color="warning"
            variant="elevated"
            rounded="xl"
            type="submit"
            min-width="120"
            :loading="deductForm.loading"
            :disabled="
              !deductForm.value || deductForm.value <= 0 || deductForm.loading
            "
          >
            Deduct
          </v-btn>
        </v-card-actions>
      </v-card>
    </v-form>
  </v-dialog>

  <v-dialog v-model="isDeductSpecialLeaveDialog" max-width="500">
    <v-form @submit.prevent="handleDeductSpecialLeave">
      <v-card class="pa-4 rounded-lg">
        <!-- Title -->
        <v-card-title
          class="d-flex align-center justify-center text-h5 font-weight-bold mb-4"
        >
          <v-icon color="warning" size="large" class="mr-2"
            >mdi-minus-circle</v-icon
          >
          Deduct Special Leave Balance
        </v-card-title>

        <v-divider class="mb-4"></v-divider>

        <!-- Body -->
        <v-card-text>
          <v-row>
            <v-col cols="12">
              <v-text-field
                v-model="specialDeductionForm.value"
                label="Value to Deduct"
                type="number"
                variant="outlined"
                density="compact"
                hide-details
                class="mb-3"
                min="0"
              ></v-text-field>
            </v-col>
          </v-row>
        </v-card-text>

        <!-- Actions -->
        <v-card-actions class="d-flex justify-end gap-3 pa-0 mt-4">
          <v-btn
            color="grey"
            variant="outlined"
            rounded="xl"
            @click="isDeductSpecialLeaveDialog = false"
            min-width="120"
          >
            Cancel
          </v-btn>

          <v-btn
            color="warning"
            variant="elevated"
            rounded="xl"
            min-width="120"
            type="submit"
            :loading="specialDeductionForm.loading"
            :disabled="
              !specialDeductionForm.value ||
              specialDeductionForm.value <= 0 ||
              specialDeductionForm.loading
            "
          >
            Deduct
          </v-btn>
        </v-card-actions>
      </v-card>
    </v-form>
  </v-dialog>

  <v-dialog v-model="isDeductSpecialLeaveDialog" max-width="500">
    <v-form @submit.prevent="handleDeductSpecialLeave">
      <v-card class="pa-4 rounded-lg">
        <!-- Title -->
        <v-card-title
          class="d-flex align-center justify-center text-h5 font-weight-bold mb-4"
        >
          <v-icon color="warning" size="large" class="mr-2">
            mdi-minus-circle
          </v-icon>
          Deduct Special Leave Balance
        </v-card-title>

        <v-divider class="mb-4"></v-divider>

        <!-- Body -->
        <v-card-text>
          <v-row>
            <v-col cols="12">
              <v-text-field
                v-model="specialDeductionForm.value"
                label="Value to Deduct"
                type="number"
                variant="outlined"
                density="compact"
                min="0"
              />
            </v-col>

            <v-col cols="12">
              <v-select
                v-model="specialDeductionForm.credit_origin"
                label="Origin"
                variant="outlined"
                density="compact"
                :items="[
                  {
                    title: 'Leave Application',
                    value: 'leave_application',
                  },
                  {
                    title: 'Undertime',
                    value: 'undertime',
                  },
                  {
                    title: 'Correction',
                    value: 'correction',
                  },
                  {
                    title: 'Monetization',
                    value: 'monetization',
                  },
                  {
                    title: 'Manual Adjustment',
                    value: 'manual_adjustment',
                  },
                ]"
                item-title="title"
                item-value="value"
              />
            </v-col>

            <v-col cols="12">
              <v-card variant="tonal" color="info">
                <v-card-title class="text-subtitle-2 py-2">
                  Origin Legend
                </v-card-title>

                <v-card-text class="text-caption">
                  <strong>Leave Application</strong> – Deduction from approved
                  leave<br />

                  <strong>Undertime</strong> – Deduction for undertime charged
                  to leave credits<br />

                  <strong>Correction</strong> – Correction of an incorrect leave
                  balance or transaction<br />

                  <strong>Monetization</strong> – Deduction due to leave
                  monetization<br />

                  <strong>Manual Adjustment</strong> – Other authorized
                  deductions (specify in Remarks)
                </v-card-text>
              </v-card>
            </v-col>

            <v-col cols="12">
              <v-textarea
                v-model="specialDeductionForm.remarks"
                label="Remarks"
                variant="outlined"
                density="compact"
                rows="3"
                auto-grow
                placeholder="Enter remarks..."
              />
            </v-col>
          </v-row>
        </v-card-text>

        <!-- Actions -->
        <v-card-actions class="d-flex justify-end gap-3 pa-0 mt-4">
          <v-btn
            color="grey"
            variant="outlined"
            rounded="xl"
            @click="isDeductSpecialLeaveDialog = false"
            min-width="120"
          >
            Cancel
          </v-btn>

          <v-btn
            color="warning"
            variant="elevated"
            rounded="xl"
            min-width="120"
            type="submit"
            :loading="specialDeductionForm.loading"
            :disabled="
              !specialDeductionForm.value ||
              specialDeductionForm.value <= 0 ||
              !specialDeductionForm.credit_origin ||
              specialDeductionForm.loading
            "
          >
            Deduct
          </v-btn>
        </v-card-actions>
      </v-card>
    </v-form>
  </v-dialog>

  <!-- <DeleteDialog
    v-model="deleteDialog"
    @confirm="handleDeleteEmployeeLeave()"
    @cancel="deleteDialog = false"
  /> -->

  <!-- <pre>{{ employeeEntitlements }}</pre> -->
</template>
<script>
import SidebarLayout from "@/layouts/SidebarLayout.vue";
import LeaveManagementTabs from "@/components/LeaveManagementTabs.vue";
import TableWrapper from "@/components/TableWrapper.vue";
import { useForm } from "@inertiajs/vue3";
import DeleteDialog from "@/components/DeleteDialog.vue";

export default {
  layout: SidebarLayout,
  components: {
    LeaveManagementTabs,
    TableWrapper,
    DeleteDialog,
  },
  props: {
    errors: Object,
    employee: Object,
    employeeEntitlements: Object,
    specialEmployeeEntitlements: Object,
    leave_ledger_link: String,
  },
  data() {
    return {
      activeTab: "entitlements",
      dialog: false,

      isDeductSpecialLeaveDialog: false,
      deleteDialog: false,

      deductForm: useForm({
        employee_id: this.employee.id,
        entitlement_id: null,
        value: null,
        credit_origin: null,
        remarks: null,
      }),

      specialDeductionForm: useForm({
        employee_id: this.employee.id,
        special_entitlement_id: null,
        value: null,
        credit_origin: null,
        remarks: null,
      }),
    };
  },

  methods: {
    handleDeductLeave() {
      this.deductForm.post(route("hrmanagement.leave.deductLeave"), {
        onSuccess: () => {
          this.showToast("Leave deducted successfully", "success");
          this.deductForm.value = null;
          this.deductForm.credit_origin = null;
          this.deductForm.remarks = null;
          this.dialog = false;
        },
        onError: (errors) => {
          const errorMessages = Object.values(errors).flat().join(" ");
          this.showToast(errorMessages, "error");
          this.dialog = false;
          this.deductForm.credit_origin = null;
          this.deductForm.remarks = null;
        },
      });
    },

    handleDeductSpecialLeave() {
      //   console.log(this.specialDeductionForm.data);
      this.specialDeductionForm.post(
        route("hrmanagement.leave.deductSpecialLeave"),
        {
          preserveScroll: true,
          preserveState: true,
          onSuccess: () => {
            this.showToast("Special leave deducted successfully", "success");
            this.specialDeductionForm.reset();
            this.specialDeductionForm.clearErrors();
            this.isDeductSpecialLeaveDialog = false;
          },
          onError: (errors) => {
            const errorMessages = Object.values(errors).flat().join(" ");
            this.showToast(errorMessages, "error");
            this.isDeductSpecialLeaveDialog = false;
          },
        }
      );
    },

    openEmployeeEntitlementDialog(employeeEntitlement) {
      console.log(employeeEntitlement);
      this.dialog = true;
      this.deductForm.entitlement_id = employeeEntitlement.leave_id;
    },

    openSpecialEmployeeEntitlementDialog(specialEmployeeEntitlement) {
      console.log(specialEmployeeEntitlement);
      this.isDeductSpecialLeaveDialog = true;
      this.specialDeductionForm.special_entitlement_id =
        specialEmployeeEntitlement.id;
    },

    handleDeleteEmployeeLeave() {
      this.$inertia.delete(
        route("hrmanagement.leave.deleteEmployeeLeaveEntitlements", {
          id: this.id,
        }),
        {
          onSuccess: () => {
            this.showToast("Leave deleted successfully", "success");
            this.deleteDialog = false;
          },
        }
      );
    },
  },
};
</script>
