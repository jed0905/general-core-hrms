<!--
  The Job Details can only be edited by the superadmin, hr_director, campus_hr, and campus_hr_staff.
  The user account regardless of the role can only view their own job details.
  The forms should be on disabled.
-->
<template>
  <v-card-title class="ml-5">
    <h3>Job Details</h3>
  </v-card-title>
  <v-divider class="mb-2 mt-1"></v-divider>
  <v-form @submit.prevent="submitJobDetailsForm()" class="mt-4 ma-4 pa-4">
    <v-row>
      <v-col cols="12" md="3">
        <v-text-field
          label="Date Hired"
          variant="outlined"
          density="compact"
          type="date"
          v-model="jobDetailForm.date_hired"
          :error-messages="
            v$.jobDetailForm.date_hired.$errors.map((e) => e.$message)
          "
          :disabled="!isUserAllowedToEdit"
          rounded="lg"
        ></v-text-field>
      </v-col>

      <v-col cols="12" md="4">
        <v-field
          variant="outlined"
          density="compact"
          :disabled="!isUserAllowedToEdit"
          rounded="lg"
        >
          <template v-slot:default>
            <div
              class="v-field__input"
              style="min-height: 40px; padding: 8px 12px"
            >
              <div
                v-if="newBiometrics.length > 0"
                class="d-flex flex-wrap align-center gap-1"
                style="width: 100%"
              >
                <v-chip
                  v-for="biometrics in newBiometrics"
                  :key="biometrics.id"
                  closable
                  @click:close="removeBiometrics(biometrics.id)"
                  :disabled="!isUserAllowedToEdit"
                  color="grey-lighten-2"
                  variant="flat"
                  size="small"
                  class="ma-0"
                >
                  {{ biometrics.biometrics_id }}
                </v-chip>
              </div>
              <div v-else class="text-grey text-body-2">
                No biometrics ID added
              </div>
            </div>
          </template>
        </v-field>
      </v-col>
      <v-col cols="12" md="1" class="d-flex align-center">
        <v-btn
          v-if="isUserAllowedToEdit"
          color="starbucks-green"
          icon="mdi-plus"
          size="x-small"
          @click="biometricsDialog = true"
          class="mb-6"
        >
        </v-btn>
      </v-col>
      <v-col cols="12" md="4">
        <v-autocomplete
          label="Detailed At"
          variant="outlined"
          density="compact"
          :items="detailed_at"
          item-title="name"
          item-value="id"
          :clearable="isUserAllowedToEdit"
          v-model="jobDetailForm.detailed_at"
          :disabled="!isUserAllowedToEdit"
          rounded="lg"
        >
        </v-autocomplete>
      </v-col>

      <v-col cols="12" md="3">
        <!-- This will be the bureau or office -->
        <v-autocomplete
          label="Operating Unit"
          density="compact"
          variant="outlined"
          :items="operatingUnits"
          item-title="name"
          item-value="id"
          v-model="jobDetailForm.operating_unit_id"
          :error-messages="
            v$.jobDetailForm.operating_unit_id.$errors.map((e) => e.$message)
          "
          :disabled="!isUserAllowedToEdit"
          rounded="lg"
          @update:model-value="jobDetailForm.department_id = null"
        ></v-autocomplete>
      </v-col>

      <v-col cols="12" md="3">
        <v-autocomplete
          label="Department"
          variant="outlined"
          density="compact"
          :items="filteredDepartments"
          item-value="id"
          item-title="name"
          v-model="jobDetailForm.department_id"
          :disabled="!isUserAllowedToEdit"
          rounded="lg"
        ></v-autocomplete>
      </v-col>

      <v-col cols="12" md="3">
        <v-select
          label="Employment Type"
          variant="outlined"
          density="compact"
          :items="employmentType"
          item-title="name"
          item-value="id"
          v-model="jobDetailForm.employee_type"
          :disabled="!isUserAllowedToEdit"
          rounded="lg"
        ></v-select>
      </v-col>
      <v-col cols="12" md="3">
        <v-select
          label="Employment Status"
          variant="outlined"
          density="compact"
          :items="jobStatuses"
          item-title="name"
          item-value="id"
          v-model="jobDetailForm.job_status_id"
          :disabled="!isUserAllowedToEdit"
          rounded="lg"
        ></v-select>
      </v-col>

      <v-col cols="12" md="3">
        <!-- This will be the position natin. Computer Operator -->
        <v-autocomplete
          label="Position Title"
          variant="outlined"
          density="compact"
          :items="filteredPositions"
          item-title="item_name"
          item-value="id"
          v-model="jobDetailForm.position_id"
          @update:modelValue="onPositionChange"
          :disabled="!isUserAllowedToEdit"
          rounded="lg"
        ></v-autocomplete>
      </v-col>

      <v-col cols="12" md="3">
        <v-text-field
          label="Plantilla Item Number"
          variant="outlined"
          density="compact"
          v-model="jobDetailForm.plantilla_item_number"
          disabled
          rounded="lg"
        ></v-text-field>
      </v-col>

      <v-col cols="12" md="6">
        <!-- This will be the paranthetical title. Junior Programmer -->
        <v-text-field
          label="Parenthetical Title"
          variant="outlined"
          density="compact"
          v-model="jobDetailForm.parenthetical_title"
          :disabled="!isUserAllowedToEdit"
          rounded="lg"
        ></v-text-field>
      </v-col>

      <v-col cols="12" md="3">
        <v-text-field
          label="Salary Grade"
          variant="outlined"
          density="compact"
          v-model="jobDetailForm.salary_grade"
          disabled
          rounded="lg"
        ></v-text-field>
      </v-col>

      <v-col cols="12" md="3">
        <v-select
          label="Salary Step"
          variant="outlined"
          density="compact"
          :items="filteredSalarySteps"
          item-title="salary_step_no"
          item-value="id"
          v-model="jobDetailForm.salary_step_id"
          @update:modelValue="onSalaryStepChange"
          :disabled="!isUserAllowedToEdit"
          rounded="lg"
        ></v-select>
      </v-col>

      <v-col cols="12" md="6">
        <v-text-field
          label="Salary Authorized"
          density="compact"
          variant="outlined"
          v-model="jobDetailForm.salary_authorized"
          disabled
          rounded="lg"
        ></v-text-field>
      </v-col>

      <v-col cols="12" md="3">
        <v-text-field
          label="Custom Hourly Rate"
          density="compact"
          variant="outlined"
          v-model="jobDetailForm.custom_hourly_rate"
          :disabled="!isUserAllowedToEdit"
          rounded="lg"
        ></v-text-field>
      </v-col>

      <v-col cols="12" md="3">
        <v-text-field
          label="Custom Daily Rate"
          density="compact"
          variant="outlined"
          v-model="jobDetailForm.custom_daily_rate"
          :disabled="!isUserAllowedToEdit"
          rounded="lg"
        ></v-text-field>
      </v-col>

      <v-divider class="my-2"></v-divider>

      <v-col cols="12" class="mt-2">
        <div class="d-flex align-center justify-space-between mb-4">
          <p class="mb-4">EMPLOYEE DESIGNATIONS</p>

          <div>
            <v-btn
              variant="text"
              size="small"
              @click="showAllDesignations = !showAllDesignations"
            >
              {{
                showAllDesignations
                  ? "Hide Finished Designations"
                  : "Show All Designations"
              }}
            </v-btn>

            <v-btn
              v-if="isUserAllowedToEdit"
              color="starbucks-green"
              icon="mdi-plus"
              size="x-small"
              @click="addDesignation"
              class="ml-2"
            />
          </div>
        </div>
        <v-row
          v-for="item in displayedDesignations"
          :key="item.id ?? item.designation_id"
        >
          <v-col cols="12" md="7">
            <input type="hidden" v-model="item.id" />
            <v-autocomplete
              label="Designation"
              variant="outlined"
              density="compact"
              :items="
                getAvailableDesignations(
                  jobDetailForm.designation.indexOf(item)
                )
              "
              item-title="name"
              item-value="id"
              v-model="item.designation_id"
              :disabled="!isUserAllowedToEdit"
              rounded="lg"
            />
          </v-col>

          <v-col cols="12" md="2">
            <v-text-field
              label="Date of Assumption"
              variant="outlined"
              density="compact"
              type="date"
              v-model="item.assumption_date"
              :disabled="!isUserAllowedToEdit"
              rounded="lg"
            />
          </v-col>

          <v-col cols="12" md="2">
            <v-text-field
              label="End Date (optional)"
              variant="outlined"
              density="compact"
              type="date"
              v-model="item.end_date"
              :disabled="!isUserAllowedToEdit"
              rounded="lg"
            />
          </v-col>

          <v-col cols="12" md="1">
            <v-btn
              color="error"
              icon="mdi-trash-can-outline"
              size="x-small"
              @click="
                removeDesignation(jobDetailForm.designation.indexOf(item))
              "
            />
          </v-col>
        </v-row>
      </v-col>

      <!-- <v-col cols="12">
        <v-row class="mb-4" v-if="isUserAllowedToEdit">
          <v-col cols="12">
            <div class="d-flex align-center">
              <span class="mr-2 text-body-2"
                >Include Employment Contract Details</span
              >
              <v-switch
                v-model="includeEmploymentContractDetails"
                color="starbucks-green"
                hide-details
                inset
                :disabled="!isUserAllowedToEdit"
                rounded="lg"
              ></v-switch>
            </div>
          </v-col>
        </v-row>

        <v-row v-if="includeEmploymentContractDetails === true">
          <v-col cols="12" md="6">
            <v-text-field
              label="Contract Start Date"
              variant="outlined"
              density="compact"
              type="date"
              v-model="jobDetailForm.contractStartDate"
              :disabled="!isUserAllowedToEdit"
              rounded="lg"
            ></v-text-field>
          </v-col>
          <v-col cols="12" md="6">
            <v-text-field
              label="Contract End Date"
              variant="outlined"
              density="compact"
              type="date"
              v-model="jobDetailForm.contractEndDate"
              :disabled="!isUserAllowedToEdit"
              rounded="lg"
            ></v-text-field>
          </v-col>
          <v-col cols="12">
            <v-file-input
              label="Contract Detail"
              variant="outlined"
              density="compact"
              prepend-inner-icon="mdi-upload"
              v-model="jobDetailForm.contractDetail"
              :disabled="!isUserAllowedToEdit"
              rounded="lg"
            ></v-file-input>
          </v-col>
        </v-row>
      </v-col> -->

      <v-divider class="mb-4"></v-divider>

      <v-col cols="12" md="6">
        <!--
          Select will be the name of employees with designations only.
         -->
        <v-autocomplete
          label="Position Title Of Immediate Supervisor"
          density="compact"
          variant="outlined"
          :items="modifiedEmployeeDesignations"
          item-title="name"
          item-value="key"
          v-model="jobDetailForm.immediate_supervisor_key"
          @update:modelValue="setImmediateSupervisor"
          :clearable="isUserAllowedToEdit"
          :disabled="!isUserAllowedToEdit"
          rounded="lg"
        ></v-autocomplete>
      </v-col>

      <v-col cols="12" md="6">
        <v-autocomplete
          label="Position Title Of Next Higher Supervisor"
          density="compact"
          variant="outlined"
          :items="filteredHigherSupervisors"
          item-title="name"
          item-value="key"
          v-model="jobDetailForm.higher_supervisor_key"
          @update:modelValue="setHigherSupervisor"
          :clearable="isUserAllowedToEdit"
          :disabled="!isUserAllowedToEdit"
          rounded="lg"
        />
      </v-col>

      <v-col cols="12">
        <div class="d-flex align-center justify-space-between mb-4">
          <p>POSITION TITLE, AND ITEM OF THOSE DIRECTLY SUPERVISED</p>
        </div>

        <div>
          <v-row
            v-for="(item, index) in jobDetailForm.subordinates"
            :key="index"
          >
            <v-col cols="12" md="6">
              <v-text-field
                label="Position Title"
                variant="outlined"
                density="compact"
                v-model="jobDetailForm.subordinates[index].position_title"
                disabled
                rounded="lg"
              />
            </v-col>
            <v-col cols="12" md="6">
              <v-text-field
                label="Item Number"
                variant="outlined"
                density="compact"
                v-model="
                  jobDetailForm.subordinates[index].plantilla_item_number
                "
                disabled
                rounded="lg"
              />
            </v-col>
          </v-row>
        </div>
      </v-col>

      <v-col cols="12" v-if="isUserAllowedToEdit">
        <v-row v-if="currentEmployementStatus">
          <v-col cols="12" md="3" class="d-flex align-center">
            <p>Employee Separation/Transfer:</p>
          </v-col>

          <v-col cols="12" md="8" class="d-flex justify-start">
            <v-btn
              :color="getEmploymentButtonColor"
              :prepend-icon="getEmploymentButtonIcon"
              min-width="120"
              :text="getEmploymentButtonText"
              @click="handleEmployementStatus"
              rounded="xl"
            />
          </v-col>
        </v-row>
      </v-col>

      <v-col cols="12">
        <div class="d-flex align-center justify-end">
          <ButtonSuccess
            v-if="
              (hasRole('superadmin') ||
                hasRole('hr_director') ||
                hasRole('campus_hr_staff') ||
                hasRole('campus_hr')) &&
              !$page.url.startsWith('/self-service')
            "
            type="submit"
            name="Save"
          />
        </div>
      </v-col>
    </v-row>
  </v-form>

    <v-dialog v-model="biometricsDialog" max-width="400" persistent>
    <v-card class="pa-2 ma-2 rounded-lg">
      <v-card-title>
        <span class="text-h6">Add Another Biometrics ID</span>
      </v-card-title>
      <v-card-text>
        <v-text-field
          v-model="newBiometricsId"
          label="Biometrics ID"
          variant="outlined"
          density="comfortable"
          type="number"
          rounded="lg"
          autofocus
        ></v-text-field>
      </v-card-text>
      <v-card-actions class="justify-end">
        <ButtonMuted
          color="black"
          @click="closeBiometricsDialog"
          name="Cancel"
        />
        <ButtonSuccess
          @click="addBiometricsIdFromDialog"
          name="Add"
          :disabled="!newBiometricsId || String(newBiometricsId).trim() === ''"
        />
      </v-card-actions>
    </v-card>
  </v-dialog>

  <v-dialog
    v-model="isSeparateTransferDialogVisible"
    max-width="500"
    persistent
  >
    <v-card class="ma-2 pa-2">
      <v-card-title class="pb-2 mt-2">
        <span class="text-h6 font-weight-medium">
          Update Employment Status
        </span>
      </v-card-title>

      <v-card-text>
        <p class="mb-4 text-body-2">
          Are you sure you want to proceed with this action for
          <strong>{{ employeeName }}</strong
          >?
        </p>

        <v-row>
          <!-- EFFECTIVITY DATE -->
          <v-col cols="12">
            <v-text-field
              label="Effectivity Date"
              hint="Date the action takes effect"
              persistent-hint
              type="date"
              variant="outlined"
              density="compact"
              v-model="employmentStatusForm.effectivityDate"
              rounded="lg"
            />
          </v-col>

          <!-- ACTION TYPE -->
          <v-col cols="12">
            <v-select
              label="Employment Status Action"
              hint="Select the type of action"
              persistent-hint
              :items="movementType"
              item-title="label"
              item-value="value"
              variant="outlined"
              density="compact"
              v-model="employmentStatusForm.movementType"
              rounded="lg"
            />
          </v-col>

          <!-- 🔁 TRANSFER: Operating Unit appears ONLY if transfer -->
          <v-col
            cols="12"
            v-if="employmentStatusForm.movementType === 'transfer'"
          >
            <v-select
              label="Operating Unit"
              :items="detailed_at"
              item-title="name"
              item-value="id"
              variant="outlined"
              density="compact"
              v-model="employmentStatusForm.new_operating_unit_id"
              rounded="lg"
              hint="Select new assigned unit"
              persistent-hint
            />
          </v-col>

          <!-- 🧾 TERMINATION: Justification -->
          <v-col
            cols="12"
            v-if="
              employmentStatusForm.movementType === 'termination' ||
              employmentStatusForm.movementType === 'suspension'
            "
          >
            <v-textarea
              label="Reason/Justification"
              variant="outlined"
              density="compact"
              v-model="employmentStatusForm.reason"
              rounded="lg"
              rows="3"
            />
          </v-col>

          <!-- ACTION BUTTONS -->
          <v-col cols="12" class="mb-3">
            <div class="d-flex align-center justify-end">
              <ButtonMuted
                class="mr-2"
                name="Cancel"
                @click="closeEmploymentStatusDialog()"
              />

              <ButtonSuccess
                name="Confirm Action"
                :disabled="isProcessingEmploymentStatusChange"
                @click="handleSeparateTransferEmployment()"
              />
            </div>
          </v-col>
        </v-row>
      </v-card-text>
    </v-card>
  </v-dialog>

  <v-dialog v-model="transferPositionDialog" max-width="400">
    <v-card class="pa-3">
      <v-card-title class="text-h6 font-weight-medium">
        Transfer Position?
      </v-card-title>

      <v-card-text>
        <p class="text-body-2 mb-4">
          Do you also want to transfer the current position
          <b>
            {{ employeeJobDetails.data.position_name }} ({{
              jobDetailForm.plantilla_item_number
            }})
          </b>
          of <strong>{{ employeeName }}</strong> to the new operating unit?
        </p>

        <p class="text-caption text-medium-emphasis">
          If <b>Yes</b>, the employee will retain their position in the new
          operating unit. If <b>No</b>, the position will remain in the current
          unit and may require reassignment.
        </p>
      </v-card-text>

      <v-card-actions class="justify-end">
        <ButtonMuted
          name="No"
          class="mr-2 text-grey-darken-2"
          color="grey-lighten-1"
          @click="handleTransferPositionDecision(false)"
        />

        <ButtonSuccess
          name="Yes"
          @click="handleTransferPositionDecision(true)"
        />
      </v-card-actions>
    </v-card>
  </v-dialog>

  <!-- Delete Dialog -->
  <DeleteDialog
    v-model="isDeleteDialog"
    message="Are you sure you want to delete this designation?"
    :loading="loading"
    @confirm="handleDeleteDesignation"
    @cancel="isDeleteDialog = false"
  />

  <!-- <pre>{{ jobDetailForm }}</pre> -->
</template>

<script>
import ButtonSuccess from "../ButtonSuccess.vue";
import { useForm } from "@inertiajs/vue3";
import useVuelidate from "@vuelidate/core";
import { required, minLength, maxLength, helpers } from "@vuelidate/validators";
import { set } from "lodash";
import ButtonMuted from "../ButtonMuted.vue";
import DeleteDialog from "../DeleteDialog.vue";

export default {
  components: {
    ButtonSuccess,
    DeleteDialog,
    ButtonMuted,
  },

  props: {
    operatingUnits: Array,
    detailed_at: Array,
    departments: Array,
    employeeJobDetails: Object,
    jobStatuses: Array,
    positions: Array,
    salarySteps: Array,
    designations: Array,
    employeeDesignations: Array, // Added employeeDesignations prop
    employeeName: String,
  },

  mounted() {

    // Initialize Salary Grade and Salary Step and Salary Authorized based on the employee's position
    if (this.employeeJobDetails.data.position_id) {
      const position = this.positions.data.find(
        (pos) => pos.id === this.employeeJobDetails.data.position_id
      );

      if (position) {
        this.jobDetailForm.plantilla_item_number =
          position.plantilla_item_number;
        this.jobDetailForm.salary_grade = position.salary_grade;
      }
    }

    // Initialize Salary Authorized based on the employee's salary step
    if (this.employeeJobDetails.data.salary_step_id) {
      // Find the salary step object first

      const position = this.positions.data.find(
        (pos) => pos.id === this.employeeJobDetails.data.position_id
      );


      const salaryStep = this.salarySteps.find(
        (step) => step.id == this.employeeJobDetails.data.salary_step_id
      );

      const salary = salaryStep?.salary_matrix.find(
        (matrix) =>
          matrix.salary_grade_id == position.salary_grade
      );

      if (salary) {
        this.jobDetailForm.salary_authorized = Number(
          salary.amount
        ).toLocaleString("en-US", {
          minimumFractionDigits: 2,
          maximumFractionDigits: 2,
        });
      }

    }

    // Set the current employment status based on the employee's job status
    this.currentEmployementStatus =
      this.employeeJobDetails.data.date_separated === null
        ? "Employed"
        : "Terminated";

    // Initialize biometrics from props
    this.syncBiometricsFromProps();
  },

  data() {
    return {
      activeMenu: "Job",
      biometricsDialog: false,
      newBiometricsId: "",
      newBiometrics: [],
      originalBiometrics: [], // Store original biometrics IDs for comparison
      employeeName: this.employeeName,
      currentEmployementStatus: "Employed",
      isSeparateTransferDialogVisible: false,
      movementType: [
        {
          value: "retirement",
          label: "Retirement",
        },
        {
          value: "resignation",
          label: "Resignation",
        },
        {
          value: "termination",
          label: "Termination",
        },
        {
          value: "end_of_contract",
          label: "End of Contract",
        },
        {
          value: "transfer",
          label: "Transfer",
        },
      ],

      isProcessingEmploymentStatusChange: false,

      transferPositionDialog: false,

      employmentType: ["Non-Teaching", "Teaching"],

      showAllDesignations: false,

      v$: useVuelidate(),

      jobDetailForm: useForm({
        date_hired: this.employeeJobDetails.data?.date_hired || null,
        biometrics_id: this.employeeJobDetails.data?.biometrics_id || null,
        biometrics_to_add: [],
        biometrics_to_delete: [],
        detailed_at: this.employeeJobDetails.data?.detailed_at || null,
        operating_unit_id:
          this.employeeJobDetails.data?.operating_unit_id || null,
        department_id: this.employeeJobDetails.data?.department_id || null,
        employee_type: this.employeeJobDetails.data?.employee_type || null,
        job_status_id: this.employeeJobDetails.data?.job_status_id || null,
        position_id: this.employeeJobDetails.data?.position_id || null,
        parenthetical_title:
          this.employeeJobDetails.data?.parenthetical_title || null,
        salary_grade: null,
        salary_step_id: this.employeeJobDetails.data?.salary_step_id || null,
        salary_authorized: null,
        custom_hourly_rate: this.employeeJobDetails.data?.custom_hourly_rate,
        custom_daily_rate: this.employeeJobDetails.data?.custom_daily_rate,

        designation: this.employeeJobDetails.data.designations.length
          ? this.employeeJobDetails.data.designations.map((d) => ({
              id: d.id || "",
              designation_id: d.designation_id || "",
              assumption_date: d.assumption_date || "",
              end_date: d.end_date || "",
            }))
          : [
              {
                id: "",
                designation_id: "",
                assumption_date: "",
                end_date: "",
              },
            ],

        immediate_supervisor_key:
          this.employeeJobDetails.data?.immediate_supervisor_id &&
          this.employeeJobDetails.data?.immediate_supervisor_designation
            ? `${this.employeeJobDetails.data.immediate_supervisor_id}-${this.employeeJobDetails.data.immediate_supervisor_designation}`
            : null,
        immediate_supervisor_id: this.employeeJobDetails.data
          ?.immediate_supervisor_id
          ? Number(this.employeeJobDetails.data.immediate_supervisor_id)
          : null,
        immediate_supervisor_designation:
          this.employeeJobDetails.data?.immediate_supervisor_designation,
        higher_supervisor_key:
          this.employeeJobDetails.data?.higher_supervisor_id &&
          this.employeeJobDetails.data?.higher_supervisor_designation
            ? `${this.employeeJobDetails.data.higher_supervisor_id}-${this.employeeJobDetails.data.higher_supervisor_designation}`
            : null,
        higher_supervisor_id: this.employeeJobDetails.data?.higher_supervisor_id
          ? Number(this.employeeJobDetails.data.higher_supervisor_id)
          : null,
        higher_supervisor_designation:
          this.employeeJobDetails.data?.higher_supervisor_designation,

        subordinates: this.employeeJobDetails.data.subordinates?.length
          ? this.employeeJobDetails.data.subordinates.map((d) => ({
              position_title: `${d.position_title} (${d.name})`,
              plantilla_item_number: d.plantilla_item_number,
            }))
          : [],

        employmentStatus: null,
        itemNumber: null,
        salaryGrade: null,
        salaryStep: null,
        contractStartDate: null,
        contractEndDate: null,
        contractDetail: null,
        salaryAuthorized: null,
        positionTitleOfNextHigherSupervisor: null,
        positionTitleOfSupervisor: null,
        positionTitleAndItemOfSupervised: null,
        employmentContractNumber: null,
        employmentContractDetail: null,
      }),

      employmentStatusForm: useForm({
        employeeId: this.employeeJobDetails.data.id,
        employeeNumber: this.employeeJobDetails.data.employee_number,
        effectivityDate: null,
        movementType: null,
        new_operating_unit_id: null,
        reason: null,
        transfer_position: false,
      }),

      includeEmploymentContractDetails: false,

      subordinates: [
        {
          positionTitle: null,
          itemNumber: null,
        },
      ],

      selectedDesignation: null,
      isDeleteDialog: false,
    };
  },

  validations: {
    jobDetailForm: {
      date_hired: { required },
      operating_unit_id: { required },
    },

    employmentStatusForm: {
      effectivityDate: { required },
      movementType: { required },
    },
  },

  computed: {
    displayedDesignations() {
      if (this.showAllDesignations) {
        return this.jobDetailForm.designation;
      }

      const today = new Date().toISOString().split("T")[0];

      return this.jobDetailForm.designation.filter(
        (item) => !item.end_date || item.end_date >= today
      );
    },

    getEmploymentButtonText() {
      return this.currentEmployementStatus === "Employed"
        ? "Separate/Transfer Employee"
        : "Activate Employment";
    },

    getEmploymentButtonIcon() {
      return this.currentEmployementStatus === "Employed"
        ? "mdi-account-off"
        : "mdi-account-check";
    },

    getEmploymentButtonColor() {
      return this.currentEmployementStatus === "Employed"
        ? "red-darken-4"
        : "success";
    },

    isUserAllowedToEdit() {
      const roles = this.$page.props.auth.roles;
      const currentUserId = this.$page.props.auth.user.employee_id;
      const editingUserId = this.employeeJobDetails.data.id; // or however you're referencing the profile being edited

      const isAllowedRole = [
        "superadmin",
        "hr_director",
        "campus_hr",
        "campus_hr_staff",
      ].includes(roles[0]);

      // disallow if superadmin is editing their own profile
      if (
        (roles[0] === "superadmin" ||
          roles[0] === "hr_director" ||
          roles[0] === "campus_hr" ||
          roles[0] === "campus_hr_staff") &&
        currentUserId === editingUserId
      ) {
        return false;
      }

      return isAllowedRole;
    },

    userRoles() {
      return this.$page.props.auth.roles ?? [];
    },

    filteredDepartments() {
      if (
        !this.employeeJobDetails.data ||
        !this.jobDetailForm.operating_unit_id
      ) {
        return this.departments;
      }

      return this.departments.filter(
        (dept) =>
          dept.operating_unit_id === this.jobDetailForm.operating_unit_id
      );
    },

    filteredPositions() {
      if (
        !this.employeeJobDetails.data ||
        !this.jobDetailForm.operating_unit_id
      ) {
        return this.positions.data.map((pos) => ({
          ...pos,
          item_name: pos.plantilla_item_number
            ? `${pos.name} (${pos.plantilla_item_number})`
            : pos.name,
        }));
      }

      return this.positions.data
        .filter(
          (pos) =>
            pos.operating_unit_id === this.jobDetailForm.operating_unit_id
        )
        .map((pos) => ({
          ...pos,
          item_name: pos.plantilla_item_number
            ? `${pos.name} (${pos.plantilla_item_number})`
            : pos.name,
        }));
    },

    filteredSalarySteps() {
      if (!this.jobDetailForm.salary_grade) {
        return this.salaryGrades;
      }

      return this.salarySteps.filter(
        (step) => step.salary_grade_id == this.jobDetailForm.salary_grade
      );
    },

    modifiedEmployeeDesignations() {
      return this.employeeDesignations.map((d) => ({
        designation_id: d.designation.id,
        name: `${d.designation.name} (${d.employee.personal_information.firstname} ${d.employee.personal_information.lastname})`,
        employee_id: d.employee.id,
        key: `${d.employee.id}-${d.designation.id}`,
      }));
    },

    filteredHigherSupervisors() {
      return this.modifiedEmployeeDesignations.filter(
        (item) =>
          item.employee_id !== this.jobDetailForm.immediate_supervisor_id
      );
    },

    modifiedSubordinates() {
      return this.employeeJobDetails.subordinates.map((d) => ({
        employee_id: d.id,
        name: `${d.position.government_position.name} (${d.personal_information.firstname} ${d.personal_information.lastname})`,
        plantilla_item_number: d.position.plantilla_item_number,
      }));
    },

    filteredOperatingUnits() {
      return this.operatingUnits.filter(
        (unit) => unit.id !== this.employeeJobDetails.data.operating_unit_id
      );
    },
  },

  watch: {
    employeeJobDetails: {
      handler(newVal) {
        this.jobDetailForm.designation =
          newVal?.data?.designations?.map((d) => ({
            id: d.id,
            designation_id: d.designation_id,
            assumption_date: d.assumption_date,
            end_date: d.end_date,
          })) || [];

        // Sync biometrics when employeeJobDetails is updated
        this.syncBiometricsFromProps();
      },
      deep: true,
      immediate: true,
    },

    "employmentStatusForm.movementType": function (newValue) {
      // Clear fields when movement type changes
      this.employmentStatusForm.reason = null;
      this.employmentStatusForm.new_operating_unit_id = null;
    },
  },

  methods: {
    closeEmploymentStatusDialog() {
      this.isSeparateTransferDialogVisible = false;
      this.employmentStatusForm.effectivityDate = null;
      this.employmentStatusForm.movementType = null;
      this.employmentStatusForm.reason = null;
      this.employmentStatusForm.new_operating_unit_id = null;
    },

    hasRole(role) {
      return this.userRoles.includes(role);
    },

    onPositionChange(selectedId) {
      // find the position object based on the selected id
      const selected = this.positions.data.find((pos) => pos.id === selectedId);

      if (selected) {
        this.jobDetailForm.plantilla_item_number =
          selected.plantilla_item_number;
        this.jobDetailForm.salary_grade = selected.salary_grade;
      } else {
        this.jobDetailForm.plantilla_item_number = null;
        this.jobDetailForm.salary_grade = null;
      }
    },

    onSalaryStepChange(selectedStepId) {
      //   console.log(`Selected Salary Step ID: ${selectedStepId}`);

      const position = this.positions.data.find(
        (pos) => pos.id === this.employeeJobDetails.data.position_id
      );

      // Find the selected salary step
      const salaryStep = this.salarySteps.find(
        (step) => step.id === selectedStepId
      );

      const salary = salaryStep?.salary_matrix.find(
        (matrix) =>
          matrix.salary_grade_id == position?.salary_grade &&
          matrix.step_number == salaryStep.salary_step_no
      );

      //   console.log(salary);

      if (salary) {
        this.jobDetailForm.salary_authorized = Number(
          salary.amount
        ).toLocaleString("en-US", {
          minimumFractionDigits: 2,
          maximumFractionDigits: 2,
        });
      } else {
        this.jobDetailForm.salary_authorized = null;
      }
    },

    getAvailableDesignations() {
      return this.designations.map((d) => ({
        ...d,
        name: `${d.name} (${d.operating_unit.shortcut})`,
      }));
    },

    getAvailableEmployeeDesignations(currentIndex) {
      const selectedIds = this.jobDetailForm.immediate_supervisor_id
        .map((d, i) => (i !== currentIndex ? d.designation_id : null))
        .filter((id) => id);
    },

    setImmediateSupervisor(selectedKey) {
      if (!selectedKey) {
        // User cleared the field
        this.jobDetailForm.immediate_supervisor_id = null;
        this.jobDetailForm.immediate_supervisor_designation = null;
        return;
      }

      // Split back employee_id and designation_id
      const [employee_id, designation_id] = selectedKey.split("-");

      const selected = this.modifiedEmployeeDesignations.find(
        (item) =>
          item.employee_id === Number(employee_id) &&
          item.designation_id === Number(designation_id)
      );

      if (selected) {
        this.jobDetailForm.immediate_supervisor_id = selected.employee_id;
        this.jobDetailForm.immediate_supervisor_designation =
          selected.designation_id;
      }
    },

    setHigherSupervisor(selectedKey) {
      if (selectedKey) {
        // Split back employee_id and designation_id
        const [employee_id, designation_id] = selectedKey.split("-");

        const selected = this.modifiedEmployeeDesignations.find(
          (item) =>
            item.employee_id === Number(employee_id) &&
            item.designation_id === Number(designation_id)
        );

        if (selected) {
          this.jobDetailForm.higher_supervisor_id = selected.employee_id;
          this.jobDetailForm.higher_supervisor_designation =
            selected.designation_id;
        }
      }
    },

    addDesignation() {
      this.jobDetailForm.designation.push({
        designation: null,
      });
    },

    removeDesignation(index) {
      const designationId = this.jobDetailForm.designation[index].id;

      if (designationId) {
        this.isDeleteDialog = true;
        this.selectedDesignation = designationId;
      } else {
        this.jobDetailForm.designation.splice(index, 1);
      }
    },

    handleDeleteDesignation() {
      this.$inertia.delete(
        route("hrmanagement.employee.deleteEmployeeDesignation", {
          id: this.selectedDesignation,
        }),
        {
          onSuccess: () => {
            this.showToast("Designation deleted succcessfully", "success");
            this.isDeleteDialog = false;
            this.$emit("jobDetailRefresh");
          },

          onError: (errors) => {
            const errorMessages = Object.values(errors).flat().join(" ");

            this.showToast(`${errorMessages}`, "error");
          },
        }
      );
    },

    handleSeparateTransferEmployment() {
      this.v$.employmentStatusForm.$touch();

      if (!this.v$.employmentStatusForm.$invalid) {
        this.isSeparateTransferDialogVisible = false;

        if (
          this.employeeJobDetails.data.position_id &&
          this.employmentStatusForm.movementType == "transfer"
        ) {
          this.transferPositionDialog = true;
        } else {
          this.handleSubmitEmploymentStatus();
        }
      } else {
        // Show validation errors
        const errors = this.v$.employmentStatusForm.$errors;
        const errorMessages = errors.map((err) => err.$message).filter(Boolean);
        if (errorMessages.length > 0) {
          this.showToast(errorMessages.join(", "), "error");
        }
      }
    },

    handleTransferPositionDecision(value) {
      // console.log("Transfer Position Decision:", value);
      this.employmentStatusForm.transfer_position = value;
      this.transferPositionDialog = false;

      // Continue submission after decision
      this.handleSubmitEmploymentStatus();
    },

    handleSubmitEmploymentStatus() {
      this.isProcessingEmploymentStatusChange = true;
      // Transform data to match backend expectations (camelCase to snake_case)
      // Update form data before submitting
      const transformedData = {
        employee_id: this.employmentStatusForm.employeeId,
        employee_number: this.employmentStatusForm.employeeNumber,
        effective_date: this.employmentStatusForm.effectivityDate,
        movement_type: this.employmentStatusForm.movementType,
        new_operating_unit_id: this.employmentStatusForm.new_operating_unit_id,
        reason:
          this.employmentStatusForm.movementType === "termination" ||
          this.employmentStatusForm.movementType === "suspension"
            ? this.employmentStatusForm.reason
            : null,
        transfer_position: this.employmentStatusForm.transfer_position,
      };

      // Use Inertia's post method - it automatically handles FormData when files are present
      this.$inertia.post(
        route("hrmanagement.employee.updateEmployementStatus"),
        transformedData,
        {
          preserveScroll: true,
          preserveState: true,
          onSuccess: () => {
            this.showToast("Employment status updated successfully", "success");
            this.$emit("jobDetailRefresh");
            // Reset form
            this.employmentStatusForm.reset();
            this.isProcessingEmploymentStatusChange = false;
          },
          onError: (errors) => {
            const errorMessages = Object.values(errors).flat().join(" ");
            this.showToast(`${errorMessages}`, "error");
            // Reopen dialog on error
            this.isSeparateTransferDialogVisible = true;
            this.isProcessingEmploymentStatusChange = false;
          },
        }
      );
    },

    submitJobDetailsForm() {
      if (!this.jobDetailForm.immediate_supervisor_key) {
        this.jobDetailForm.immediate_supervisor_id = null;
        this.jobDetailForm.immediate_supervisor_designation = null;
      }

      if (!this.jobDetailForm.higher_supervisor_key) {
        this.jobDetailForm.higher_supervisor_id = null;
        this.jobDetailForm.higher_supervisor_designation = null;
      }

      // Calculate biometrics changes
      const currentBiometricIds = this.newBiometrics.map((bio) =>
        String(bio.biometrics_id)
      );
      const originalBiometricIds = this.originalBiometrics.map((bio) =>
        String(bio.biometrics_id)
      );

      // Find IDs to add (in current but not in original)
      const toAdd = this.newBiometrics
        .filter((bio) => {
          const isTemporary = bio.id > 1e12;
          const notInOriginal = !originalBiometricIds.includes(
            String(bio.biometrics_id)
          );
          return isTemporary || notInOriginal;
        })
        .map((bio) => bio.biometrics_id);

      // Find IDs to delete (in original but not in current)
      const toDelete = this.originalBiometrics
        .filter(
          (bio) => !currentBiometricIds.includes(String(bio.biometrics_id))
        )
        .map((bio) => bio.id);

      // Add biometrics changes to form data
      this.jobDetailForm.biometrics_to_add = toAdd;
      this.jobDetailForm.biometrics_to_delete = toDelete;

      this.jobDetailForm.put(
        route("hrmanagement.employee.updateJobDetails", {
          id: this.employeeJobDetails.data.id,
        }),
        {
          onSuccess: () => {
            this.showToast("Job Details updated successfully.", "success");
            this.$emit("jobDetailRefresh");
            // Note: Biometrics will be synced from props via the watch when data refreshes
          },

          onError: (errors) => {
            const errorMessages = Object.values(errors).flat().join(" ");

            this.showToast(`${errorMessages}`, "error");
          },
        }
      );
    },

    addBiometricsId(closeDialog = false) {
      // Convert to string and trim
      const biometricsIdValue = String(this.newBiometricsId || "").trim();

      if (!biometricsIdValue) {
        return;
      }

      // Check if the ID already exists
      const exists = this.newBiometrics.some(
        (bio) => String(bio.biometrics_id) === biometricsIdValue
      );

      if (exists) {
        this.showToast("Biometrics ID already exists", "error");
        return;
      }

      // Add to local array only (will be saved when Save button is clicked)
      const tempId = Date.now(); // Temporary ID for new items
      this.newBiometrics.push({
        id: tempId,
        biometrics_id: biometricsIdValue,
      });

      // Close dialog
      if (closeDialog) {
        this.biometricsDialog = false;
        this.newBiometricsId = "";
      }
    },

    addBiometricsIdFromDialog() {
      this.addBiometricsId(true);
    },

    closeBiometricsDialog() {
      this.biometricsDialog = false;
      this.newBiometricsId = "";
    },

    removeBiometrics(biometricsId) {
      if (!biometricsId) {
        return;
      }

      // Find the biometrics item by id
      const index = this.newBiometrics.findIndex(
        (bio) => bio.id === biometricsId
      );

      if (index !== -1) {
        // Remove from local array only (will be saved when Save button is clicked)
        this.newBiometrics.splice(index, 1);
      } else {
        this.showToast("Biometrics ID not found", "error");
      }
    },

    handleEmployementStatus() {
      this.isSeparateTransferDialogVisible = true;
      // this.currentEmployementStatus = this.currentEmployementStatus === 'Employed' ? 'Terminated' : 'Employed'
    },

    syncBiometricsFromProps() {
      // Check if biometric_ids are loaded from the relationship
      if (
        this.employeeJobDetails.data?.biometric_ids &&
        Array.isArray(this.employeeJobDetails.data.biometric_ids)
      ) {
        this.originalBiometrics =
          this.employeeJobDetails.data.biometric_ids.map((bio) => ({
            id: bio.id,
            biometrics_id: bio.biometric_id || bio.biometrics_id,
          }));
        this.newBiometrics = this.originalBiometrics.map((bio) => ({ ...bio }));
      } else {
        // If no biometric_ids loaded, initialize with empty array
        this.originalBiometrics = [];
        this.newBiometrics = [];
      }
    },
  },
};
</script>
