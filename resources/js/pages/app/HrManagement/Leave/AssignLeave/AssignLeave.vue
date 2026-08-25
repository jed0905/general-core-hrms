<template>
  <LeaveManagementTabs v-model:activeTab="activeTab" :tabs="tabs" />
  <PageOnBuild v-if="pageBeingBuilt === true" />

  <v-row v-else>
    <!-- <pre>{{ leaveCredits }}</pre> -->
    <v-col cols="12">
      <v-card rounded="lg" class="pa-4">
        <v-card-title class="text-h6 ma-2">Apply Leave</v-card-title>
        <v-divider></v-divider>
        <v-card-text>
          <v-form>
            <v-row>
              <v-col cols="12">
                <v-autocomplete
                  v-model="v$.leaveApplicationForm.employeeId.$model"
                  label="Employee Name (Type to search)"
                  density="compact"
                  variant="outlined"
                  :items="filteredEmployees"
                  item-title="personal_information.full_name_asc"
                  item-value="id"
                  :search="search"
                  :no-filter="true"
                  @update:search="onSearch"
                  @update:modelValue="onEmployeeChange"
                  :hide-no-data="search.length < 2"
                  :error-messages="
                    v$.leaveApplicationForm.employeeId?.$errors.map(
                      (e) => e.$message
                    )
                  "
                  return-object
                  rounded="lg"
                />
              </v-col>

              <v-col cols="12" md="5" v-if="leaveApplicationForm.employeeId">
                <p class="mb-3">Type of Leave to Be Availed of</p>
                <v-autocomplete
                  label="Leave Type"
                  variant="outlined"
                  density="compact"
                  hide-details="auto"
                  :items="leaveTypes"
                  item-title="name"
                  item-value="name"
                  rounded="lg"
                  v-model="leaveApplicationForm.leaveType"
                  :error-messages="
                    v$.leaveApplicationForm.leaveType.$error
                      ? v$.leaveApplicationForm.leaveType.$errors[0].$message
                      : ''
                  "
                ></v-autocomplete>
              </v-col>

              <!-- Other Purpose Selection  -->
              <v-col
                cols="12"
                md="6"
                v-if="leaveApplicationForm.leaveType === 'Others'"
              >
                <p class="mb-3">Type of Leave (Others)</p>
                <v-row>
                  <v-col cols="12">
                    <v-autocomplete
                      label="Other Purpose"
                      variant="outlined"
                      density="compact"
                      rounded="lg"
                      :items="formattedSpecialLeaveCredits"
                      item-title="name"
                      item-value="id"
                      v-model="leaveApplicationForm.specialLeaveCreditId"
                    />
                  </v-col>
                </v-row>
              </v-col>

              <v-col
                cols="12"
                md="1"
                v-if="
                  leaveApplicationForm.leaveType !== 'Others' &&
                  leaveApplicationForm.employeeId
                "
              >
                <p class="mb-3">Leave Balance</p>
                <p class="d-flex align-center">
                  {{
                    leaveCredits?.balance
                      ? Number(leaveCredits.balance).toFixed(2)
                      : "0.00"
                  }}
                  Day(s)
                </p>
              </v-col>

              <!-- Selection of Leave Dates -->
              <v-col cols="12" md="6" v-if="leaveApplicationForm.employeeId">
                <p class="mb-3">Inclusive Dates:</p>
                <v-row>
                  <v-col cols="12" md="6">
                    <v-text-field
                      label="From"
                      variant="outlined"
                      density="compact"
                      type="date"
                      rounded="lg"
                      v-model="leaveApplicationForm.from"
                      :min="minimumAllowedDate"
                      @blur="v$.leaveApplicationForm.from.$touch()"
                      :error-messages="
                        v$.leaveApplicationForm.from.$error
                          ? v$.leaveApplicationForm.from.$errors[0].$message
                          : ''
                      "
                      :hint="minimumDateHint"
                      persistent-hint
                      hide-details="auto"
                    ></v-text-field>
                  </v-col>

                  <v-col cols="12" md="6">
                    <v-text-field
                      label="To"
                      variant="outlined"
                      density="compact"
                      type="date"
                      rounded="lg"
                      v-model="leaveApplicationForm.to"
                      :error-messages="
                        v$.leaveApplicationForm.to.$error
                          ? v$.leaveApplicationForm.to.$errors[0].$message
                          : ''
                      "
                      :min="minimumAllowedDate"
                      :hint="minimumDateHint"
                      hide-details="auto"
                    ></v-text-field>
                  </v-col>
                </v-row>
              </v-col>

              <!-- Selection of Specific Days for Staggered Leave within a week -->
              <v-col
                cols="12"
                v-if="
                  leaveApplicationForm.leave_dates.length && isWholeWeekRange
                "
              >
                <p class="mb-2">Select Dates and Duration:</p>
                <v-row dense wrap>
                  <v-col
                    v-for="(dateObj, index) in leaveApplicationForm.leave_dates"
                    :key="dateObj.date"
                    cols="auto"
                    class="d-flex align-center mr-4 mb-2"
                  >
                    <!-- Checkbox per date -->
                    <v-checkbox
                      v-model="dateObj.included"
                      :label="
                        new Date(dateObj.date).toLocaleDateString('en-US', {
                          month: 'short',
                          day: 'numeric',
                        })
                      "
                      class="mr-2 mb-0"
                      dense
                    ></v-checkbox>

                    <!-- Duration select inline -->
                    <v-autocomplete
                      v-if="dateObj.included && showCocDuration"
                      variant="outlined"
                      density="compact"
                      v-model="dateObj.type"
                      :items="leaveTypeOptions"
                      item-title="title"
                      item-value="value"
                      hide-details
                      dense
                      style="width: 150px"
                    ></v-autocomplete>
                  </v-col>
                </v-row>
              </v-col>

              <!--
                Selection for Details of Leave (Location within Philippines of Abroad) for:
                Vacation Leave
                Mandatory/Forced Leave
                Special Privilege Leave
                Solo Parent Leave
                Special Emergency (Calamity) Leave
                Others (Compensatory Time Off)
                Others (Wellness Leave - Filed in advance)
               -->
              <v-col cols="12" md="6" v-if="shouldShowLocationField">
                <p class="mb-3">Details of Leave</p>
                <v-row>
                  <v-col cols="12">
                    <p class="mb-3" style="font-style: Italic">
                      In case of Vacation/Special Privilege Leave
                    </p>

                    <v-radio-group
                      v-model="leaveApplicationForm.locationType"
                      inline
                    >
                      <v-radio label="Within The Philippines" value="within" />
                      <v-radio label="Abroad (Specify)" value="abroad" />
                    </v-radio-group>

                    <v-text-field
                      variant="outlined"
                      density="compact"
                      v-if="leaveApplicationForm.locationType"
                      label="Location"
                      v-model="leaveApplicationForm.location"
                      :error-messages="
                        v$.leaveApplicationForm.location.$error
                          ? v$.leaveApplicationForm.location.$errors.map(
                              (e) => e.$message || 'Invalid input'
                            )
                          : []
                      "
                    />
                  </v-col>
                </v-row>
              </v-col>

              <!-- Selection of Details of Leave for:
                Sick Leave
                Rehabilitation Privilege
                Others (Wellness Leave - Filed after return)
               -->
              <v-col
                cols="12"
                md="6"
                v-if="
                  leaveApplicationForm.leaveType === 'Sick Leave' ||
                  leaveApplicationForm.leaveType ===
                    'Rehabilitation Privilege' ||
                  (leaveApplicationForm.leaveType === 'Others' &&
                    isWellnessLeave &&
                    !isWellnessLeaveFiledInAdvance)
                "
              >
                <p class="mb-3">Details of Leave</p>
                <v-row>
                  <v-col cols="12">
                    <p class="mb-3" style="font-style: italic">
                      In case of Sick Leave:
                    </p>

                    <!-- Sick Leave Type Selection -->
                    <v-radio-group v-model="leaveApplicationForm.sickLeaveType">
                      <v-radio label="In Hospital" value="hospital" />
                      <v-radio label="Out Patient" value="outPatient" />
                    </v-radio-group>

                    <!-- Illness TextField (required for both types) -->
                    <v-text-field
                      v-if="leaveApplicationForm.sickLeaveType"
                      label="Specify Illness"
                      variant="outlined"
                      density="compact"
                      rounded="lg"
                      v-model="leaveApplicationForm.illness"
                      :error-messages="
                        v$.leaveApplicationForm.illness.$error
                          ? v$.leaveApplicationForm.illness.$errors.map(
                              (e) => e.$message || 'Invalid input'
                            )
                          : []
                      "
                      hide-details="auto"
                    ></v-text-field>
                  </v-col>
                </v-row>
              </v-col>

              <!-- Selection of Details of Leave for Special Leave Benefits for Women -->
              <v-col
                cols="12"
                md="6"
                v-if="
                  leaveApplicationForm.leaveType ===
                  'Special Leave Benefits for Women'
                "
              >
                <p class="mb-3">Details of Leave</p>
                <v-row>
                  <v-col cols="12">
                    <p class="mb-3" style="font-style: Italic">
                      In case of Special Leave Benefits for Women:
                    </p>
                    <v-text-field
                      label="Specify Illness"
                      variant="outlined"
                      density="compact"
                      rounded="lg"
                      v-model="
                        leaveApplicationForm.specialLeaveBenefitsForWomenIllness
                      "
                      :error-messages="
                        v$.leaveApplicationForm
                          .specialLeaveBenefitsForWomenIllness.$error
                          ? v$.leaveApplicationForm.specialLeaveBenefitsForWomenIllness.$errors.map(
                              (e) => e.$message || 'Invalid input'
                            )
                          : []
                      "
                      hide-details
                    ></v-text-field>
                  </v-col>
                </v-row>
              </v-col>

              <!-- Selection of Details of Leave for Study Leave -->
              <v-col
                cols="12"
                md="6"
                v-if="leaveApplicationForm.leaveType === 'Study Leave'"
              >
                <p class="mb-3">Details of Leave</p>
                <v-row>
                  <v-col cols="12">
                    <p class="mb-3" style="font-style: Italic">
                      In case of Study Leave:
                    </p>
                    <v-radio-group
                      v-model="leaveApplicationForm.study_leave_application"
                      inline
                    >
                      <v-radio
                        label="Completion of Master's Degree"
                        value="Completion of Master's Degree"
                      ></v-radio>
                      <v-radio
                        label="BAR/Board Examination Review"
                        value="BAR/Board Examination Review"
                      ></v-radio>
                    </v-radio-group>
                  </v-col>
                </v-row>
              </v-col>

              <v-col
                cols="12"
                md="6"
                v-if="leaveApplicationForm.leaveType !== ''"
              >
                <p>Commutation</p>
                <v-radio-group v-model="commutationType" inline>
                  <v-radio label="Not Requested" value="notRequested"></v-radio>
                  <v-radio label="Requested" value="requested"></v-radio>
                </v-radio-group>
              </v-col>

              <v-divider></v-divider>
              <v-col cols="12">
                <div class="d-flex align-center justify-end">
                  <ButtonMuted name="Reset" class="mr-2" @click="resetForm()" />
                  <!-- <ButtonSuccess name="Apply" @click="handleSubmit()" /> -->
                  <v-btn
                    color="starbucks-green"
                    rounded="xl"
                    min-width="120"
                    prepend-icon="mdi-application"
                    @click="checkLeaveCredits()"
                    >Apply</v-btn
                  >
                </div>
              </v-col>
            </v-row>
          </v-form>
        </v-card-text>
      </v-card>
    </v-col>
  </v-row>

  <!-- Insufficient Leave Credits Dialog -->
  <v-dialog v-model="insufficientCreditsDialog" max-width="500px" persistent>
    <v-card class="pa-4" rounded="lg">
      <!-- Dialog Header -->
      <v-card-title class="text-h6 text-center pa-0 mb-4">
        <v-icon color="warning" size="48" class="mb-2">mdi-alert-circle</v-icon>
        <div>Insufficient Leave Credits</div>
      </v-card-title>

      <!-- Dialog Content -->
      <v-card-text class="pa-0">
        <div class="text-body-1 mb-4 text-center">
          <template v-if="!leaveCredits?.has_credits">
            You don't have any leave credits for
            {{ leaveCredits?.leave_type || leaveApplicationForm.leaveType }}. If
            you proceed, this will be processed as unpaid leave.
          </template>
          <template v-else>
            You have insufficient leave credits. If you proceed, this will be
            processed as unpaid leave.
          </template>
        </div>

        <!-- Leave Details Card -->
        <v-card variant="outlined" class="mb-4" rounded="lg">
          <v-card-text class="py-3">
            <div class="text-subtitle-2 text-primary mb-2">Leave Details:</div>
            <v-row dense>
              <v-col cols="12">
                <div class="text-caption text-grey-600">Leave Type:</div>
                <div class="text-body-2 font-weight-medium">
                  {{
                    leaveCredits?.leave_type || leaveApplicationForm.leaveType
                  }}
                </div>
              </v-col>
              <v-col cols="6">
                <div class="text-caption text-grey-600">Days Requested:</div>
                <div class="text-body-2 font-weight-medium">
                  {{ leaveApplicationForm.workingDays }} day(s)
                </div>
              </v-col>
              <v-col cols="6">
                <div class="text-caption text-grey-600">Available Credits:</div>
                <div
                  class="text-body-2 font-weight-medium"
                  :class="{
                    'text-error':
                      !leaveCredits?.has_credits ||
                      leaveCredits.balance < leaveApplicationForm.workingDays,
                  }"
                >
                  {{ leaveCredits ? leaveCredits.balance.toFixed(2) : "0.00" }}
                  day(s)
                </div>
              </v-col>
              <v-col cols="12" v-if="leaveCredits?.has_credits" class="mt-2">
                <div class="text-caption text-grey-600">Credit History:</div>
                <div class="text-body-2">
                  <div>
                    Total Earned:
                    {{ leaveCredits.total_earned.toFixed(2) }} day(s)
                  </div>
                  <div>
                    Added Credits:
                    {{ leaveCredits.credit_addition.toFixed(2) }} day(s)
                  </div>
                  <div>
                    Used Credits:
                    {{ leaveCredits.credit_deduction.toFixed(2) }} day(s)
                  </div>
                </div>
              </v-col>
              <v-col cols="12" class="mt-2">
                <div class="text-caption text-error font-weight-medium">
                  Note: This leave will be processed as unpaid leave.
                </div>
              </v-col>
            </v-row>
          </v-card-text>
        </v-card>
        <!-- <pre>{{ leaveCredits }}</pre> -->
      </v-card-text>

      <!-- Dialog Actions -->
      <v-card-actions class="pa-0 pt-4">
        <v-spacer></v-spacer>
        <v-btn
          variant="outlined"
          color="grey-darken-1"
          rounded="xl"
          min-width="120"
          @click="insufficientCreditsDialog = false"
          class="mr-2"
        >
          Cancel
        </v-btn>
        <v-btn
          color="starbucks-green"
          variant="flat"
          rounded="xl"
          min-width="120"
          @click="proceedWithApplication()"
        >
          Proceed Anyway
        </v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>

  <!-- <pre>{{ leaveApplicationForm.leaveType }}</pre> -->
</template>
<script>
import SidebarLayout from "@/layouts/SidebarLayout.vue";
import TableWrapper from "@/components/TableWrapper.vue";
import ButtonSuccess from "@/components/ButtonSuccess.vue";
import ButtonMuted from "@/components/ButtonMuted.vue";
import { useForm } from "@inertiajs/vue3";
import { useVuelidate } from "@vuelidate/core";
import {
  required,
  minLength,
  maxLength,
  email,
  sameAs,
  helpers,
} from "@vuelidate/validators";
import LeaveManagementTabs from "@/components/LeaveManagementTabs.vue";
import PageOnBuild from "@/components/Errors/PageOnBuild.vue";
import { router } from "@inertiajs/vue3";
import debounce from "lodash/debounce";

export default {
  layout: SidebarLayout,

  components: {
    PageOnBuild,
    LeaveManagementTabs,
    TableWrapper,
    ButtonSuccess,
    ButtonMuted,
  },

  props: {
    errors: Object,
    employees: Object,
    leaveTypes: Object,
    leaveCredits: Object,
    specialLeaveCredits: Object,
  },

  data() {
    return {
      leaveCreditBalance: null,
      insufficientCreditsDialog: false,
      isPanelOpen: 0,
      activeTab: "Assign Leave",
      pageBeingBuilt: false,
      showLeaveDurationOption: false,
      commutationType: null,
      fromDate: null,
      toDate: null,
      workingDays: 0,
      search: "",

      v$: useVuelidate(),
      leaveApplicationForm: useForm({
        from: "",
        to: "",
        leaveType: "",
        location: "",
        locationType: "",
        sickLeaveType: "",
        workingDays: "",
        reason: "",
        otherPurpose: "",
        commutation: "",
        illness: "",
        location_within_philippines: "",
        location_abroad: "",
        in_hospital: "",
        out_hospital: "",
        study_leave_application: "",
        specialLeaveCreditId: "",
        specialLeaveBenefitsForWomenIllness: "",
        leave_dates: [],
      }),

      leaveTypeOptions: [
        { title: "Full Day", value: "full_day" },
        { title: "Half Day AM", value: "half_day_am" },
        { title: "Half Day PM", value: "half_day_pm" },
      ],
    };
  },

  computed: {
    shouldShowLocationField() {
      const type = this.leaveApplicationForm.leaveType;

      return (
        type === "Vacation Leave" ||
        type === "Mandatory/Forced Leave" ||
        type === "Special Privilege Leave" ||
        type === "Solo Parent Leave" ||
        type === "Special Emergency (Calamity) Leave" ||
        (type === "Others" && this.isCompensatoryTimeOff) ||
        (type === "Others" &&
          this.isWellnessLeave &&
          this.isWellnessLeaveFiledInAdvance)
      );
    },

    isWholeWeekRange() {
      const { from, to } = this.leaveApplicationForm;

      if (!from || !to) return false;

      const start = new Date(from);
      const end = new Date(to);

      // Get start of week (Sunday)
      const startOfWeek = new Date(start);
      startOfWeek.setDate(start.getDate() - start.getDay());

      // Get end of week (Saturday)
      const endOfWeek = new Date(startOfWeek);
      endOfWeek.setDate(startOfWeek.getDate() + 6);

      // Check if "to" is still within the same week
      return end <= endOfWeek;
    },

    showCocDuration() {
      const selected = this.formattedSpecialLeaveCredits.find(
        (item) => item.id === this.leaveApplicationForm.specialLeaveCreditId
      );

      return (
        this.leaveApplicationForm.leaveType === "Others" &&
        selected?.name?.toLowerCase().includes("coc")
      );
    },

    isWellnessLeave() {
      const selected = this.formattedSpecialLeaveCredits.find(
        (item) => item.id === this.leaveApplicationForm.specialLeaveCreditId
      );

      return (
        this.leaveApplicationForm.leaveType === "Others" &&
        selected?.name?.toLowerCase().includes("wl")
      );
    },

    isWellnessLeaveFiledInAdvance() {
      if (!this.leaveApplicationForm.from) return false;

      const today = new Date();
      today.setHours(0, 0, 0, 0);

      const startDate = new Date(this.leaveApplicationForm.from);
      startDate.setHours(0, 0, 0, 0);

      return startDate >= today;
    },

    isCompensatoryTimeOff() {
      const selected = this.formattedSpecialLeaveCredits.find(
        (item) => item.id === this.leaveApplicationForm.specialLeaveCreditId
      );

      return (
        this.leaveApplicationForm.leaveType === "Others" &&
        selected?.name?.toLowerCase().includes("coc")
      );
    },

    formattedSpecialLeaveCredits() {
      // Filter out expired credits and format the remaining ones
      return this.specialLeaveCredits.data
        .filter((item) => {
          // If expiration_date_to is null, consider it as non-expiring (always valid)
          if (!item.expiration_date_to) {
            return true;
          }

          // Check if the expiration date is today or in the future
          const expirationDate = new Date(item.expiration_date_to);
          expirationDate.setHours(0, 0, 0, 0); // Reset time to start of day

          const today = new Date();
          today.setHours(0, 0, 0, 0);

          // Also check is_expired attribute if available (backend check)
          if (item.is_expired === true) {
            return false;
          }

          // Return true if expiration date is today or in the future
          return expirationDate >= today;
        })
        .map((item) => {
          const date = new Date(item.expiration_date_to);
          const formattedDate = date.toLocaleDateString("en-US", {
            year: "numeric",
            month: "long",
            day: "numeric",
          });

          return {
            id: item.id,
            name: `${item.special_leave.shortcut} – ${item.balance} (Expires on ${formattedDate})`,
          };
        });
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

    minimumDateHint() {
      const leaveType = this.leaveApplicationForm.leaveType;

      // Define advance notice requirements for each leave type
      const advanceNoticeRules = {
        "Vacation Leave": {
          days: 5,
          text: "Must be filed at least 5 calendar days in advance",
        },
        "Special Privilege Leave": {
          days: 7,
          text: "Must be filed at least 1 week (7 calendar days) in advance, except in emergency cases",
        },
        "Solo Parent Leave": {
          days: 5,
          text: "Must be filed at least 5 calendar days in advance",
        },
        "Special Leave Benefits for Women": {
          days: 5,
          text: "Must be filed at least 5 calendar days in advance for scheduled surgery",
        },
      };

      // Sick Leave can be filed immediately
      if (leaveType === "Sick Leave") {
        return "Can be filed immediately upon return or in advance";
      }

      // If no advance notice rule exists for this leave type, return empty
      if (!advanceNoticeRules[leaveType]) {
        return "";
      }

      const rule = advanceNoticeRules[leaveType];
      const today = new Date();
      today.setHours(0, 0, 0, 0);

      const minRequiredDate = new Date(today);
      minRequiredDate.setDate(today.getDate() + rule.days);

      const formattedDate = minRequiredDate.toLocaleDateString("en-US", {
        year: "numeric",
        month: "short",
        day: "numeric",
      });

      return `${rule.text}. Earliest date: ${formattedDate}`;
    },

    minimumAllowedDate() {
      const leaveType = this.leaveApplicationForm.leaveType;

      // Define advance notice requirements for each leave type
      const advanceNoticeRules = {
        "Vacation Leave": 5,
        "Special Privilege Leave": 7,
        "Solo Parent Leave": 5,
        "Special Leave Benefits for Women": 5,
      };

      if (leaveType === "Sick Leave") {
        return ""; // removes min restriction
      }

      // If no advance notice rule exists for this leave type, allow today
      if (!advanceNoticeRules[leaveType]) {
        const today = new Date();
        today.setHours(0, 0, 0, 0);
        return today.toISOString().split("T")[0];
      }

      const requiredDays = advanceNoticeRules[leaveType];
      const today = new Date();
      today.setHours(0, 0, 0, 0);

      const minRequiredDate = new Date(today);
      minRequiredDate.setDate(today.getDate() + requiredDays);

      return minRequiredDate.toISOString().split("T")[0];
    },
  },

  validations() {
    return {
      leaveApplicationForm: {
        employeeId: { required },
        // --- DATES ---
        from: {
          required: helpers.withMessage("Start date is required.", required),

          advanceNotice: helpers.withMessage(
            () => {
              const type = this.leaveApplicationForm.leaveType;

              const noAdvance = [
                "Sick Leave",
                "Rehabilitation Privilege",
                "Special Emergency (Calamity) Leave",
              ];

              if (!type || noAdvance.includes(type)) return "";

              return (
                this.checkAdvanceNoticeRequirement(
                  this.leaveApplicationForm.from
                ).message || ""
              );
            },
            (value) => {
              const type = this.leaveApplicationForm.leaveType;
              if (!value || !type) return true;

              const noAdvance = [
                "Sick Leave",
                "Rehabilitation Privilege",
                "Special Emergency (Calamity) Leave",
              ];

              if (noAdvance.includes(type)) return true;

              return this.checkAdvanceNoticeRequirement(value).isValid;
            }
          ),
        },

        to: {
          required: helpers.withMessage("End date is required.", required),
        },

        leaveType: {
          required: helpers.withMessage("Leave type is required.", required),
        },

        // --- LOCATION ---
        location: {
          required: helpers.withMessage("Location is required.", (value) => {
            const type = this.leaveApplicationForm.leaveType;

            const needsLocation = [
              "Vacation Leave",
              "Mandatory/Forced Leave",
              "Special Privilege Leave",
              "Solo Parent Leave",
              "Special Emergency (Calamity) Leave",
            ];

            if (needsLocation.includes(type)) {
              return required.$validator(value);
            }

            // Wellness (ADVANCE)
            if (
              type === "Others" &&
              this.isWellnessLeave &&
              this.isWellnessLeaveFiledInAdvance
            ) {
              return required.$validator(value);
            }

            return true;
          }),
        },

        // --- SICK / REHAB / WELLNESS ---
        illness: {
          required: helpers.withMessage("Illness is required.", (value) => {
            const type = this.leaveApplicationForm.leaveType;

            // Sick + Rehab
            if (["Sick Leave", "Rehabilitation Privilege"].includes(type)) {
              return required.$validator(value);
            }

            // Wellness (FILED AFTER)
            if (
              type === "Others" &&
              this.isWellnessLeave &&
              !this.isWellnessLeaveFiledInAdvance
            ) {
              return required.$validator(value);
            }

            return true;
          }),
        },

        sickLeaveType: {
          required: helpers.withMessage(
            "Please select hospital or outpatient.",
            (value) => {
              const type = this.leaveApplicationForm.leaveType;

              if (["Sick Leave", "Rehabilitation Privilege"].includes(type)) {
                return required.$validator(value);
              }

              // ALSO REQUIRED for wellness (your requirement)
              if (
                type === "Others" &&
                this.isWellnessLeave &&
                !this.isWellnessLeaveFiledInAdvance
              ) {
                return required.$validator(value);
              }

              return true;
            }
          ),
        },

        // --- STUDY LEAVE ---
        study_leave_application: {
          required: helpers.withMessage(
            "Study leave document is required.",
            (value) =>
              this.leaveApplicationForm.leaveType === "Study Leave"
                ? required.$validator(value)
                : true
          ),
        },

        // --- SPECIAL WOMEN ---
        specialLeaveBenefitsForWomen: {
          required: helpers.withMessage("This field is required.", (value) =>
            this.leaveApplicationForm.leaveType ===
            "Special Leave Benefits for Women"
              ? required.$validator(value)
              : true
          ),
        },

        // --- OTHERS ---
        specialLeaveCreditId: {
          required: helpers.withMessage(
            "Please select a leave credit.",
            (value) =>
              this.leaveApplicationForm.leaveType === "Others"
                ? required.$validator(value)
                : true
          ),
        },
      },
    };
  },

  watch: {
    "leaveApplicationForm.leaveType": {
      handler(newVal) {
        // console.log("WIORKING")
        this.getLeaveCredits(newVal);
      },
      immediate: true,
    },

    leaveCredits: {
      handler(newVal) {
        // console.log(newVal.balance);
        this.commutationType =
          newVal.balance > 0 ? "requested" : "notRequested";
      },
      immediate: true,
    },

    "leaveApplicationForm.from"() {
      this.generateLeaveDates();
    },
    "leaveApplicationForm.to"() {
      this.generateLeaveDates();
    },
  },

  methods: {
    generateLeaveDates() {
      this.leaveApplicationForm.leave_dates = [];

      if (!this.leaveApplicationForm.from || !this.leaveApplicationForm.to)
        return;

      const start = new Date(this.leaveApplicationForm.from);
      const end = new Date(this.leaveApplicationForm.to);

      for (let d = new Date(start); d <= end; d.setDate(d.getDate() + 1)) {
        this.leaveApplicationForm.leave_dates.push({
          date: d.toISOString().split("T")[0],
          included: false, // checkbox per date
          type: "full_day", // default duration
        });
      }
    },

    /**
     * Check if the leave start date meets the advance notice requirement
     * @param {string} fromDate - The start date of the leave (YYYY-MM-DD format)
     * @returns {Object} - { isValid: boolean, message: string }
     */
    checkAdvanceNoticeRequirement(fromDate) {
      const leaveType = this.leaveApplicationForm.leaveType;
      const specialLeave = this.leaveApplicationForm.specialLeaveCreditId; // for Others

      const today = new Date();
      today.setHours(0, 0, 0, 0);

      const startDate = new Date(fromDate);
      startDate.setHours(0, 0, 0, 0);

      // Helper: add calendar days
      const addCalendarDays = (date, days) => {
        const result = new Date(date);
        result.setDate(result.getDate() + days);
        return result;
      };

      // Helper: add working days (Mon-Fri)
      const addWorkingDays = (date, days) => {
        let result = new Date(date);
        let added = 0;

        while (added < days) {
          result.setDate(result.getDate() + 1);

          const day = result.getDay();
          if (day !== 0 && day !== 6) {
            added++;
          }
        }

        return result;
      };

      // --- RULES ---
      const rules = {
        "Vacation Leave": {
          type: "calendar",
          days: 5,
          message:
            "Vacation leave must be filed at least 5 calendar days in advance.",
        },

        "Mandatory/Forced Leave": {
          type: "calendar",
          days: 5,
          message:
            "Mandatory Leave must be filed at least 5 working days in advance.",
        },

        "Special Privilege Leave": {
          type: "calendar",
          days: 7,
          message:
            "Special Privilege Leave must be filed at least 7 calendar days in advance.",
        },

        "Solo Parent Leave": {
          type: "calendar",
          days: 5,
          message:
            "Solo Parent Leave must be filed at least 5 calendar days in advance.",
        },

        "Special Leave Benefits for Women": {
          type: "calendar",
          days: 5,
          message:
            "Special Leave Benefits for Women must be filed at least 5 calendar days in advance.",
        },
      };

      // --- HANDLE OTHERS (CTO / WELLNESS) ---
      if (leaveType === "Others") {
        // Example assumption:
        // specialLeaveCreditId identifies subtype
        // You can adjust IDs accordingly

        // CTO → must be filed 5 days before
        if (specialLeave === "CTO") {
          const minDate = addCalendarDays(today, 5);

          return {
            isValid: startDate >= minDate,
            message:
              "Compensatory Overtime Credit must be filed at least 5 days in advance.",
          };
        }

        // Wellness → allow before OR 1 day after return (skip strict validation)
        if (specialLeave === "Wellness Leave") {
          return { isValid: true, message: "" };
        }
      }

      // --- DEFAULT RULE HANDLING ---
      const rule = rules[leaveType];

      if (!rule) {
        return { isValid: true, message: "" };
      }

      let minRequiredDate;

      if (rule.type === "working") {
        minRequiredDate = addWorkingDays(today, rule.days);
      } else {
        minRequiredDate = addCalendarDays(today, rule.days);
      }

      if (startDate < minRequiredDate) {
        return {
          isValid: false,
          message: rule.message,
        };
      }

      return { isValid: true, message: "" };
    },

    fetchEmployee() {
      this.$inertia.post(route("hrmanagement.leave.assignLeave"), {
        only: ["employees"],
      });
    },

    onSearch(val) {
      this.search = val;
    },

    getLeaveCredits(newVal) {
      this.leaveApplicationForm.leaveType = newVal;
      const employeeId = this.leaveApplicationForm.employeeId;
      this.$inertia.post(
        route("hrmanagement.leave.assignLeave"),
        { leaveType: newVal, employee_id: employeeId },
        {
          preserveScroll: true,
          preserveState: true,
          only: ["leaveCredits"],
        }
      );
    },

    onEmployeeChange(employeeId) {
      if (!employeeId) return;

      this.$inertia.get(
        route("hrmanagement.leave.assignLeave"),
        {
          employee_id: employeeId,
          leaveType: this.leaveApplicationForm.leaveType,
        },
        {
          preserveState: true,
          replace: true,
          only: ["specialLeaveCredits", "leaveCredits"],
        }
      );
    },

    checkLeaveCredits() {
      const leaveType = this.leaveApplicationForm.leaveType;

      // Only Vacation Leave and Sick Leave are allowed to be unpaid
      const allowedUnpaidLeaves = ["Vacation Leave", "Sick Leave"];

      if (this.leaveCredits) {
        // Show insufficient credits dialog if balance is less than requested days
        if (this.leaveCredits.balance < this.leaveApplicationForm.workingDays) {
          // Check if this leave type allows unpaid leave
          if (!allowedUnpaidLeaves.includes(leaveType)) {
            // Show error message if leave type doesn't allow unpaid leave
            this.showToast(
              `You have insufficient leave credits for ${leaveType}. Please accumulate more credits first before applying.`,
              "error"
            );
            return;
          }

          // For Vacation Leave and Sick Leave, allow unpaid leave
          this.insufficientCreditsDialog = true;
        } else {
          // If sufficient credits, proceed with application directly
          this.handleSubmit();
        }
      } else {
        // If no leave credits found, check if leave type allows unpaid leave
        if (!allowedUnpaidLeaves.includes(leaveType)) {
          // Show error message if leave type doesn't allow unpaid leave
          this.showToast(
            `You don't have any leave credits for ${leaveType}. Please accumulate credits first before applying.`,
            "error"
          );
          return;
        }

        // For Vacation Leave and Sick Leave, allow unpaid leave
        this.leaveCredits = { balance: 0 };
        this.insufficientCreditsDialog = true;
      }
    },

    resetForm() {
      this.leaveApplicationForm.reset();
      this.commutationType = null;
      this.workingDays = 0;
      this.leaveApplicationForm.leave_duration = null;
      this.leaveApplicationForm.study_leave_application = null;
      this.leaveApplicationForm.specialLeaveBenefitsForWomenIllness = null;
      this.showLeaveDurationOption = false;
    },

    calculateWorkingDays() {
      const from = this.leaveApplicationForm.from;
      const to = this.leaveApplicationForm.to;

      // 🧭 Check if both dates are selected
      if (!from || !to) {
        this.workingDays = 0;
        this.leaveApplicationForm.workingDays = 0;
        this.showLeaveDurationOption = false;
        this.showPartialDaysOption = false;
        return;
      }

      const startDate = new Date(from);
      const endDate = new Date(to);

      // 🚫 Prevent invalid ranges
      if (endDate < startDate) {
        this.workingDays = 0;
        this.leaveApplicationForm.workingDays = 0;
        this.showLeaveDurationOption = false;
        this.showPartialDaysOption = false;
        return;
      }

      let workingDays = 0;
      let currentDate = new Date(startDate);

      // 📅 Count weekdays only (Mon–Fri)
      while (currentDate <= endDate) {
        const day = currentDate.getDay();
        if (day >= 1 && day <= 5) {
          workingDays++;
        }
        // Move to next date (avoid mutating original reference)
        currentDate = new Date(currentDate.setDate(currentDate.getDate() + 1));
      }

      // ✅ Update reactive values
      this.workingDays = workingDays;
      this.leaveApplicationForm.workingDays = workingDays;

      // 🎯 Control UI logic based on duration
      this.showLeaveDurationOption = workingDays === 1;
      this.showPartialDaysOption = workingDays > 1;
    },

    proceedWithApplication() {
      this.insufficientCreditsDialog = false;
      this.handleSubmit();
    },

    handleSubmit() {
      this.leaveApplicationForm.employeeId =
        this.leaveApplicationForm.employeeId.id;
      // Update form with current radio button values
      this.leaveApplicationForm.locationType = this.locationType;
      this.leaveApplicationForm.sickLeaveType = this.sickLeaveType;
      this.leaveApplicationForm.commutation = this.commutationType;
      //   console.log(this.leaveApplicationForm.sickLeaveType);
      if (this.locationType === "within") {
        this.leaveApplicationForm.location_within_philippines =
          this.leaveApplicationForm.location;
      } else {
        this.leaveApplicationForm.location_abroad =
          this.leaveApplicationForm.location;
      }

      if (this.sickLeaveType === "hospital") {
        this.leaveApplicationForm.in_hospital = "true";
        this.leaveApplicationForm.out_hospital = "";
      } else if (this.sickLeaveType === "outPatient") {
        this.leaveApplicationForm.out_hospital = "true";
        this.leaveApplicationForm.in_hospital = "";
      }

      this.v$.leaveApplicationForm.$validate();
      if (!this.v$.leaveApplicationForm.$invalid) {
        // console.log("Form data being submitted:", this.leaveApplicationForm);
        this.leaveApplicationForm.post(
          route("hrmanagement.leave.storeAssignLeave"),
          {
            onSuccess: () => {
              this.showToast(
                "Leave application submitted successfully",
                "success"
              );
              this.resetForm();
            },
            onError: (errors) => {
              const errorMessages = Object.values(errors).flat().join(" ");
              this.showToast(`${errorMessages}`, "error");
            },
          }
        );
      } else {
        console.log(this.v$.leaveApplicationForm.$invalid);
      }

      // // Update form with additional data
      // this.form.locationType = this.locationType;
      // this.form.sickLeaveType = this.sickLeaveType;
      // this.form.workingDays = this.workingDays;

      // // Submit the form
      // this.form.post(route('self-service.my-leaves.store'), {
      //   onSuccess: () => {
      //     this.resetForm();
      //   },
      //   onError: (errors) => {
      //     console.error('Form submission errors:', errors);
      //   }
      // });
    },
  },
};
</script>
