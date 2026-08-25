<template>
  <LeaveManagementTabs v-model:activeTab="activeTab" />
  <v-row>
    <v-col cols="12">
      <v-card rounded="lg" elevation="4">
        <v-card-text>
          <p class="text-h6 ml-2">Add Leave Entitlements</p>

          <v-col cols="12">
            <v-alert
              type="info"
              variant="tonal"
              density="compact"
              icon="mdi-information-outline"
            >
              <div class="text-subtitle-2 font-weight-bold mb-2">
                Instructions
              </div>

              <ol class="pl-4 mb-3" style="margin: 0">
                <li>
                  Select whether the entitlement will be granted to an
                  <strong>Individual Employee</strong> or
                  <strong>Multiple Employees</strong>.
                </li>
                <li>
                  Choose the appropriate <strong>Leave Type</strong>. For
                  <strong>Others</strong>, complete the Special Leave, Document
                  Control Number, and Expiration Date fields.
                </li>
                <li>
                  Enter the total number of leave credits to be granted in the
                  <strong>Entitlement</strong> field.
                </li>
                <li>
                  Provide a clear and descriptive
                  <strong>Remarks</strong> indicating the basis for granting the
                  leave credits (e.g., Service Credits, Special Leave Grant,
                  Correction, Administrative Order No., Board Resolution No.,
                  etc.).
                </li>
                <li>
                  Review all information carefully before saving. Once
                  submitted, the entitlement will be recorded in the employee's
                  leave credit history and reflected in the Leave Ledger.
                </li>
              </ol>

              <v-divider class="my-2"></v-divider>

              <div class="text-subtitle-2 font-weight-bold mb-2">
                Remarks Guide
              </div>

              <ul class="pl-4" style="margin: 0">
                <li>
                  <strong>Service Credits</strong> – Credits earned for services
                  rendered beyond regular duties (e.g., Commencement Exercises,
                  Registration, Accreditation, Overtime/Overload).
                </li>
                <li>
                  <strong>Correction</strong> – Adjustment made to correct an
                  incorrect leave balance or previous transaction.
                </li>
                <li>
                  <strong>Special Leave Grant</strong> – Leave granted under a
                  specific law, policy, or university issuance.
                </li>
                <li>
                  <strong>Administrative Issuance</strong> – Indicate the
                  applicable Administrative Order, Memorandum, Board Resolution,
                  or similar authority.
                </li>
                <li>
                  <strong>Others</strong> – Clearly specify the reason and
                  include any supporting reference or document number.
                </li>
              </ul>
            </v-alert>
          </v-col>

          <v-form @submit.prevent="handleSubmit()">
            <v-divider class="mt-3 mb-3"></v-divider>
            <v-row>
              <v-col cols="12">
                <p>Add to</p>
                <v-radio-group v-model="addTo" inline>
                  <v-radio
                    class="mr-10"
                    label="Individual Employee"
                    value="individual"
                  />
                  <v-radio label="Multiple Employees" value="multiple" />
                </v-radio-group>
              </v-col>

              <v-col cols="12" v-if="addTo === 'individual'">
                <v-row>
                  <v-col cols="12" md="6">
                    <v-autocomplete
                      v-model="v$.entitlementsForm.employeeId.$model"
                      label="Employee Name (Type to search)"
                      density="compact"
                      variant="outlined"
                      :items="filteredEmployees"
                      item-title="personal_information.full_name_asc"
                      item-value="id"
                      :search="search"
                      :no-filter="true"
                      @update:search="onSearch"
                      :hide-no-data="search.length < 2"
                      :error-messages="
                        v$.entitlementsForm.employeeId?.$errors.map(
                          (e) => e.$message
                        )
                      "
                      return-object
                      rounded="lg"
                    />
                  </v-col>
                </v-row>
              </v-col>

              <v-col cols="12" v-if="addTo === 'multiple'">
                <v-row>
                  <v-col cols="12" md="5">
                    <v-select
                      label="Operating Unit"
                      variant="outlined"
                      density="compact"
                      rounded="lg"
                      :items="operatingUnits"
                      item-title="name"
                      item-value="id"
                      v-model="v$.entitlementsForm.operatingUnitId.$model"
                      :disabled="!isPrivilegedUser"
                    >
                    </v-select>
                  </v-col>
                  <v-col cols="12" md="5">
                    <v-autocomplete
                      label="Operating Unit Departments"
                      variant="outlined"
                      density="compact"
                      rounded="lg"
                      :items="operatingUnitDepartments"
                      item-title="name"
                      item-value="id"
                      v-model="departmentIds"
                      multiple
                      chips
                      closable-chips
                    ></v-autocomplete>
                  </v-col>

                  <v-col cols="12" md="2">
                    <v-btn
                      @click="clearFilter()"
                      color="primary"
                      variant="tonal"
                      min-width="120"
                      rounded="xl"
                      >Reset</v-btn
                    >
                  </v-col>

                  <v-col cols="12">
                    <div v-if="selectedEmployeesCount > 0" class="mb-3">
                      <v-chip color="primary" size="small">
                        {{ selectedEmployeesCount }} employee(s) selected
                      </v-chip>
                    </div>
                    <v-table>
                      <thead>
                        <tr>
                          <th><v-checkbox v-model="selectAll"></v-checkbox></th>
                          <th>Employee Name</th>
                          <th>Department</th>
                          <th>Employee Status</th>
                        </tr>
                      </thead>
                      <tbody>
                        <tr
                          v-for="employee in employeesPerOperatingUnitAndDepartment.data"
                          :key="employee.id"
                        >
                          <td>
                            <v-checkbox
                              v-model="selectedEmployees"
                              :value="employee.id"
                            ></v-checkbox>
                          </td>
                          <td>
                            {{ employee.personal_information.lastname }}
                            {{ employee.personal_information?.suffix ?? "" }},{{
                              employee.personal_information.firstname
                            }}
                            {{
                              employee.personal_information?.middlename ?? ""
                            }}
                          </td>
                          <td>
                            {{ employee.department.name }}
                          </td>
                          <td>{{ employee.job_status.name }}</td>
                        </tr>
                      </tbody>
                    </v-table>
                  </v-col>
                </v-row>
              </v-col>

              <v-col cols="12" md="6">
                <v-select
                  label="Leave Type"
                  variant="outlined"
                  density="compact"
                  rounded="lg"
                  :items="leaveTypes"
                  item-title="name"
                  item-value="id"
                  v-model="v$.entitlementsForm.leaveTypeId.$model"
                  :error-messages="
                    v$.entitlementsForm.leaveTypeId?.$errors.map(
                      (e) => e.$message
                    )
                  "
                ></v-select>
              </v-col>

              <v-col v-if="selectedLeaveTypeName === 'Others'" cols="12" md="6">
                <v-select
                  label="Special Leave Types"
                  variant="outlined"
                  density="compact"
                  rounded="lg"
                  :items="specialLeaves"
                  item-title="name"
                  item-value="id"
                  v-model="v$.entitlementsForm.specialLeaveId.$model"
                  :error-messages="
                    v$.entitlementsForm.specialLeaveId?.$errors.map(
                      (e) => e.$message
                    )
                  "
                ></v-select>
              </v-col>

              <v-col v-if="selectedLeaveTypeName === 'Others'" cols="12" md="6">
                <v-text-field
                  label="Document Control Number"
                  variant="outlined"
                  density="compact"
                  rounded="lg"
                  v-model="v$.entitlementsForm.documentControlNumber.$model"
                  :error-messages="
                    v$.entitlementsForm.documentControlNumber?.$errors.map(
                      (e) => e.$message
                    )
                  "
                ></v-text-field>
              </v-col>

              <v-col
                v-if="selectedLeaveTypeName === 'Others'"
                cols="12"
                sm="6"
                md="3"
              >
                <v-text-field
                  label="Expiration Date From"
                  type="date"
                  variant="outlined"
                  density="compact"
                  rounded="lg"
                  v-model="v$.entitlementsForm.expirationDateFrom.$model"
                  :error-messages="
                    v$.entitlementsForm.expirationDateFrom?.$errors.map(
                      (e) => e.$message
                    )
                  "
                ></v-text-field>
              </v-col>

              <v-col
                v-if="selectedLeaveTypeName === 'Others'"
                cols="12"
                sm="6"
                md="3"
              >
                <v-text-field
                  label="Expiration Date To"
                  type="date"
                  variant="outlined"
                  density="compact"
                  rounded="lg"
                  v-model="v$.entitlementsForm.expirationDateTo.$model"
                  :error-messages="
                    v$.entitlementsForm.expirationDateTo?.$errors.map(
                      (e) => e.$message
                    )
                  "
                ></v-text-field>
              </v-col>

              <v-col cols="12" md="6">
                <v-text-field
                  label="Entitlement"
                  variant="outlined"
                  density="compact"
                  type="number"
                  @keypress="onlyNumbers"
                  rounded="lg"
                  v-model="v$.entitlementsForm.entitlement.$model"
                  :error-messages="
                    v$.entitlementsForm.entitlement.$errors.map(
                      (e) => e.$message
                    )
                  "
                ></v-text-field>
                <p class="font-italic">
                  Note: Enter the number of credits of the leave type selected
                </p>
              </v-col>

              <v-col cols="12">
                <v-textarea
                  label="Remarks"
                  variant="outlined"
                  density="compact"
                  rounded="lg"
                  rows="3"
                  auto-grow
                  placeholder="Enter remarks (e.g. Rendered service during Commencement Exercises)"
                  v-model="v$.entitlementsForm.remarks.$model"
                  :error-messages="
                    v$.entitlementsForm.remarks?.$errors.map((e) => e.$message)
                  "
                ></v-textarea>
              </v-col>

              <v-divider class="mt-3 mb-3"></v-divider>
              <v-col cols="12">
                <div class="d-flex align-center justify-end">
                  <ButtonMuted
                    @click="redirectToIndex()"
                    class="mr-3"
                    name="Cancel"
                  />
                  <ButtonSuccess name="Save" type="submit" />
                </div>
              </v-col>
            </v-row>
          </v-form>
        </v-card-text>
      </v-card>
    </v-col>
  </v-row>

  <v-dialog v-model="showAddAnotherDialog" max-width="500">
    <v-card class="pa-4 rounded-lg">
      <v-card-title
        class="d-flex align-center justify-center text-h5 font-weight-bold mb-4"
      >
        <v-icon color="success" size="large" class="mr-2"
          >mdi-check-circle</v-icon
        >
        Success
      </v-card-title>
      <v-divider class="mb-4"></v-divider>
      <v-card-text class="text-body-1 text-center">
        <p class="mb-2">Leave credits have been added successfully.</p>
        <p class="text-caption text-medium-emphasis">
          Would you like to add another leave credit?
        </p>
      </v-card-text>
      <v-card-actions class="d-flex justify-end gap-2 pa-4">
        <v-btn
          color="grey-darken-1"
          variant="outlined"
          @click="redirectToIndex()"
          min-width="120"
        >
          No, go back
        </v-btn>
        <v-btn
          color="starbucks-green"
          variant="elevated"
          @click="addAnother"
          min-width="120"
        >
          Yes, add another
        </v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>
  <!-- <pre>{{ leaveTypes }}</pre> -->
</template>
<script>
import SidebarLayout from "@/layouts/SidebarLayout.vue";
import LeaveManagementTabs from "@/components/LeaveManagementTabs.vue";
import ButtonSuccess from "@/components/ButtonSuccess.vue";
import TableWrapper from "@/components/TableWrapper.vue";
import ButtonMuted from "@/components/ButtonMuted.vue";
import { useForm } from "@inertiajs/vue3";
import useVuelidate from "@vuelidate/core";
import { required, minLength, requiredIf } from "@vuelidate/validators";

export default {
  layout: SidebarLayout,
  components: {
    LeaveManagementTabs,
    ButtonSuccess,
    TableWrapper,
    ButtonMuted,
  },
  props: {
    errors: Object,
    leaveTypes: Object,
    employees: Object,
    operatingUnits: Object,
    countEmployeePerOperatingUnit: Number,
    operatingUnitDepartments: Object,
    employeesPerOperatingUnitAndDepartment: Object,
    specialLeaves: Object,
  },
  data() {
    return {
      activeTab: "entitlements",

      v$: useVuelidate(),
      addTo: "individual",
      search: "",
      employeeCount: this.countEmployeePerOperatingUnit || 0,
      showAddAnotherDialog: false,

      entitlementsForm: useForm({
        allOrNot: null,
        employeeId: null,
        operatingUnitId: null,
        leaveTypeId: null,
        specialLeaveId: null,
        documentControlNumber: null,
        expirationDateFrom: null,
        expirationDateTo: null,
        entitlement: null,
        remarks: null,
      }),
      departmentIds: [],
      selectedEmployees: [],
    };
  },

  validations() {
    return {
      entitlementsForm: {
        employeeId: { minLength: minLength(0) },
        operatingUnitId: { minLength: minLength(0) },
        leaveTypeId: { required },
        entitlement: { required },
        specialLeaveId: { minLength: minLength(0) },
        documentControlNumber: {
          minLength: minLength(0),
          required: requiredIf(function () {
            // `this` refers to your component instance
            return (
              this.entitlementsForm.specialLeaveId !== null &&
              this.entitlementsForm.specialLeaveId !== ""
            );
          }),
        },
        remarks: { minLength: minLength(0) },
        expirationDateFrom: { minLength: minLength(0) },
        expirationDateTo: { minLength: minLength(0) },
      },
    };
  },

  computed: {
    /* Check if user is superadmin or hr_director */
    isPrivilegedUser() {
      const userRole = this.$page.props.auth.roles[0];
      return userRole === "superadmin" || userRole === "hr_director";
    },

    /* Get current user operating unit */
    currentUserOperatingUnit() {
      return this.$page.props.auth.user?.employee?.operating_unit_id || null;
    },

    /* Filter Employee on Enter of Name */
    filteredEmployees() {
      if (this.search.length < 2) return [];
      return this.employees.data.filter((employee) =>
        employee.personal_information.full_name_asc
          .toLowerCase()
          .includes(this.search.toLowerCase())
      );
    },

    /* Get selected leave type name */
    selectedLeaveTypeName() {
      if (!this.entitlementsForm.leaveTypeId) return "";
      const selectedLeaveType = this.leaveTypes.find(
        (leaveType) => leaveType.id === this.entitlementsForm.leaveTypeId
      );
      return selectedLeaveType ? selectedLeaveType.name : "";
    },

    /* Select all checkbox state */
    selectAll: {
      get() {
        if (!this.employeesPerOperatingUnitAndDepartment?.data?.length)
          return false;
        return (
          this.selectedEmployees.length ===
          this.employeesPerOperatingUnitAndDepartment.data.length
        );
      },
      set(value) {
        if (value) {
          this.selectedEmployees =
            this.employeesPerOperatingUnitAndDepartment.data.map(
              (employee) => employee.id
            );
        } else {
          this.selectedEmployees = [];
        }
      },
    },

    /* Get count of selected employees */
    selectedEmployeesCount() {
      return this.selectedEmployees.length;
    },
  },

  watch: {
    "entitlementsForm.operatingUnitId"(newVal) {
      if (newVal) {
        this.employeePerOperatingUnitCount(newVal);
        this.getOperatingUnitDepartments(newVal);
      } else {
        this.employeeCount = 0;
      }
    },
    departmentIds(newVal) {
      if (newVal && newVal.length > 0) {
        this.getEmployeesPerOperatingUnitAndDepartment(newVal);
        this.selectedEmployees = []; // Clear selections when departments change
      } else {
        // Clear employee data when no departments selected
        this.$inertia.post(
          route("hrmanagement.leave.addLeaveEntitlements"),
          {
            departmentIds: null,
            operatingUnitId: this.entitlementsForm.operatingUnitId,
          },
          {
            preserveState: true,
            preserveScroll: true,
            only: ["employeesPerOperatingUnitAndDepartment"],
          }
        );
      }
    },

    "entitlementsForm.leaveTypeId"(newVal) {
      if (newVal && this.selectedLeaveTypeName === "Others") {
        this.getSpecialLeaves();
      } else {
        this.entitlementsForm.specialLeaveId = null;
        this.entitlementsForm.documentControlNumber = null;
        this.entitlementsForm.expirationDateFrom = null;
        this.entitlementsForm.expirationDateTo = null;
      }
    },
  },

  mounted() {
    // Debug: Log user data to understand the structure
    console.log("User data:", this.$page.props.auth.user);
    console.log("User operating unit:", this.currentUserOperatingUnit);
    console.log("Is privileged user:", this.isPrivilegedUser);

    // For non-privileged users, automatically set their operating unit and load departments
    if (!this.isPrivilegedUser && this.currentUserOperatingUnit) {
      this.entitlementsForm.operatingUnitId = this.currentUserOperatingUnit;
      this.getOperatingUnitDepartments(this.currentUserOperatingUnit);
    }
  },

  methods: {
    clearFilter() {
      this.entitlementsForm.operatingUnitId = null;
      this.departmentIds = [];
      this.selectedEmployees = [];
      this.employeeCount = 0;

      // Clear server-side data by making a request with null values
      this.$inertia.post(
        route("hrmanagement.leave.addLeaveEntitlements"),
        {
          operatingUnitId: null,
          departmentIds: null,
        },
        {
          preserveState: true,
          preserveScroll: true,
          only: [
            "operatingUnitDepartments",
            "employeesPerOperatingUnitAndDepartment",
            "countEmployeePerOperatingUnit",
          ],
        }
      );
    },

    getSpecialLeaves() {
      this.$inertia.post(
        route("hrmanagement.leave.addLeaveEntitlements"),
        {
          getSpecialLeaves: true,
        },
        {
          preserveState: true,
          preserveScroll: true,
          only: ["specialLeaves"],
        }
      );
    },

    getOperatingUnitDepartments(newVal) {
      this.$inertia.post(
        route("hrmanagement.leave.addLeaveEntitlements"),
        {
          operatingUnitId: newVal,
        },
        {
          preserveState: true,
          preserveScroll: true,
          only: ["operatingUnitDepartments"],
        }
      );
    },

    getEmployeesPerOperatingUnitAndDepartment(newVal) {
      console.log(newVal);
      this.$inertia.post(
        route("hrmanagement.leave.addLeaveEntitlements"),
        {
          departmentIds: newVal,
          operatingUnitId: this.entitlementsForm.operatingUnitId,
        },
        {
          preserveState: true,
          preserveScroll: true,
          only: ["employeesPerOperatingUnitAndDepartment"],
        }
      );
    },

    onlyNumbers(event) {
      const charCode = event.which ? event.which : event.keyCode;
      // Allow: digits (0-9) and one dot (.)
      if (
        (charCode >= 48 && charCode <= 57) || // 0-9
        (event.key === "." && !event.target.value.includes("."))
      ) {
        return;
      }
      // Allow: backspace, delete, tab, escape, enter
      if ([8, 9, 27, 13].includes(charCode)) {
        return;
      }
      event.preventDefault();
    },

    onSearch(val) {
      this.search = val;
    },

    handleSubmit() {
      console.log("submit");
      this.v$.entitlementsForm.$validate();
      if (!this.v$.entitlementsForm.$invalid) {
        this.entitlementsForm.allOrNot = this.addTo;
        if (this.addTo === "multiple") {
          this.entitlementsForm.employeeId = this.selectedEmployees;
        }
        // console.log(this.entitlementsForm)
        this.entitlementsForm.post(
          route("hrmanagement.leave.storeEmployeeLeaveEntitlements"),
          {
            preventState: true,
            preserveScroll: true,
            onSuccess: () => {
              this.showToast(
                "Leave entitlements is saved successfully",
                "success"
              );
              if (this.addTo === "individual") {
                this.showAddAnotherDialog = true;
                this.entitlementsForm.reset();
              }
            },
            onError: (errors) => {
              for (const i in errors) {
                this.showToast(`${errors[i]}`, "error");
              }
            },
          }
        );
      }
    },

    employeePerOperatingUnitCount(newVal) {
      this.entitlementsForm.operatingUnitId = newVal;

      this.$inertia.post(
        route("hrmanagement.leave.addLeaveEntitlements"),
        {
          operatingUnitId: newVal,
        },
        {
          preserveState: true,
          preserveScroll: true,
          only: ["countEmployeePerOperatingUnit"],
          onSuccess: (page) => {
            this.employeeCount = page.props.countEmployeePerOperatingUnit || 0;
          },
        }
      );
    },

    redirectToIndex() {
      this.$inertia.visit(route("hrmanagement.leave.index"));
    },

    addAnother() {
      this.showAddAnotherDialog = false;
      this.entitlementsForm.reset();
      this.v$.$reset();
    },
  },
};
</script>
