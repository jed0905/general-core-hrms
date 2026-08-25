<template>
  <MyLeavesTabs v-model:activeTab="activeTab" :tabs="tabs" />
  <PageOnBuild v-if="pageBeingBuilt === true" />

  <v-row v-else>

    <v-col cols="12">
      <v-card rounded="xl">
        <v-card-title class="text-h6 ma-2">Apply Leave</v-card-title>
        <v-divider></v-divider>
        <v-card-text>
          <v-form>
            <v-row>
              <v-col cols="12" md="6">
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
                      @update:model-value="calculateWorkingDays"
                      :error-messages="
                        v$.leaveApplicationForm.from.$error
                          ? v$.leaveApplicationForm.from.$errors[0].$message
                          : ''
                      "
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
                      @update:model-value="calculateWorkingDays"
                      :error-messages="
                        v$.leaveApplicationForm.to.$error ? v$.leaveApplicationForm.to.$errors[0].$message : ''
                      "
                      hide-details="auto"
                    ></v-text-field>
                  </v-col>

                </v-row>
              </v-col>

              <v-col cols="12" md="5">
                <p class="mb-3">Type Of Leave to Be Availed Of</p>
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
                <v-textarea
                  class="mt-3"
                  v-if="leaveApplicationForm.leaveType === 'Others'"
                  label="Reason"
                  variant="outlined"
                  density="compact"
                  rounded="lg"
                  v-model="leaveApplicationForm.reason"
                  :error-messages="
                    v$.leaveApplicationForm.reason.$error
                      ? v$.leaveApplicationForm.reason.$errors[0].$message
                      : ''
                  "
                  hide-details="auto"
                ></v-textarea>
              </v-col>

              <v-col cols="12" md="1">
                <p class="mb-3">Leave Balance</p>
                <p class="d-flex align-center">0.00 Day(s)</p>
              </v-col>

              <v-col cols="12" >
                <v-row>
                  <!-- This form will show only if the duration of Day is 1 -->
                  <v-col cols="12" md="3" v-if="showLeaveDurationOption == true">
                    <v-select
                      label="Duration"
                      variant="outlined"
                      density="compact"
                      rounded="lg"
                      :items="duration"
                      item-title="value"
                      item-value="title"
                      v-model="leaveApplicationForm.leave_duration"
                    ></v-select>
                  </v-col>

                  <v-col
                    cols="12"
                    md="6"
                    v-if="showPartialDaysOption == true"
                  >
                    <v-select
                      label="Partial Days"
                      variant="outlined"
                      density="compact"
                      rounded="lg"
                      :items="partialDays"
                      item-title="value"
                      item-value="title"
                      v-model="leaveApplicationForm.partial_days"
                    ></v-select>
                  </v-col>

                  <v-col
                    cols="12"
                    md="3"
                    v-if="leaveApplicationForm.partial_days === 'Start Date Only' || leaveApplicationForm.partial_days === 'Start and End Date'"
                  >
                    <v-select
                      label="Start Day"
                      variant="outlined"
                      density="compact"
                      rounded="lg"
                      :items="dayList"
                      item-title="value"
                      item-value="title"
                      v-model="leaveApplicationForm.start_day"
                    ></v-select>
                  </v-col>
                  <v-col
                    cols="12"
                    md="3"
                    v-if="leaveApplicationForm.partial_days === 'End Date Only' || leaveApplicationForm.partial_days === 'Start and End Date'">
                    <v-select
                      label="End Day"
                      variant="outlined"
                      density="compact"
                      rounded="lg"
                      :items="dayList"
                      item-title="value"
                      item-value="title"
                      v-model="leaveApplicationForm.end_day"
                    ></v-select>
                  </v-col>

                </v-row>

              </v-col>

              <!-- <v-col cols="12">
                <v-row>
                  <v-col cols="12" md="4">
                    <v-text-field
                      label="Number of Working Days Applied For"
                      variant="outlined"
                      density="compact"
                      rounded="lg"
                      readonly
                      :model-value="workingDays"
                      hide-details
                    ></v-text-field>
                  </v-col>
                </v-row>
              </v-col> -->

              <v-col
                cols="12"
                md="6"
                v-if="
                  leaveApplicationForm.leaveType === 'Vacation Leave' ||
                  leaveApplicationForm.leaveType === 'Special Privilege Leave' ||
                  leaveApplicationForm.leaveType === 'Mandatory/Forced Leave' ||
                  leaveApplicationForm.leaveType === 'Special Emergency (Calamity) Leave' ||
                  leaveApplicationForm.leaveType === 'Rehabilitation Privilege' ||
                  leaveApplicationForm.leaveType === 'Solo Parent Leave' ||
                  leaveApplicationForm.leaveType === 'Adoption Leave' ||
                  leaveApplicationForm.leaveType === 'Maternity Leave' ||
                  leaveApplicationForm.leaveType === 'Paternity Leave'
                "
              >
                <p class="mb-3">Details of Leave</p>
                <v-row>
                  <v-col cols="12">
                    <p class="mb-3" style="font-style: Italic">
                      In case of Vacation/Special Privilege Leave
                    </p>

                    <v-radio-group v-model="locationType" inline>
                      <v-radio
                        label="Within The Philippines"
                        value="within"
                        v-model="leaveApplicationForm.locationType"
                      ></v-radio>
                      <v-radio
                        label="Abroad (Specify)"
                        value="abroad"
                        v-model="leaveApplicationForm.locationType"
                      ></v-radio>
                    </v-radio-group>

                    <v-text-field
                      v-if="locationType === 'within'"
                      label="Location"
                      variant="outlined"
                      density="compact"
                      rounded="lg"
                      v-model="leaveApplicationForm.location"
                      :error-messages="
                        v$.leaveApplicationForm.location.$error
                          ? v$.leaveApplicationForm.location.$errors[0].$message
                          : ''
                      "
                      hide-details="auto"
                    ></v-text-field>

                     <v-text-field
                      v-if="locationType === 'abroad'"
                      label="Location"
                      variant="outlined"
                      density="compact"
                      rounded="lg"
                      v-model="leaveApplicationForm.location"
                      :error-messages="
                        v$.leaveApplicationForm.location.$error
                          ? v$.leaveApplicationForm.location.$errors[0].$message
                          : ''
                      "
                      hide-details="auto"
                    ></v-text-field>

                    <!-- <v-checkbox
                      label="Within The Philippines"
                      v-model="locationType"
                      :model-value="locationType === 'within'"
                      @update:model-value="
                        (value) => (locationType = value ? 'within' : null)
                      "
                    ></v-checkbox>
                    <v-text-field
                      v-if="locationType === 'within'"
                      label="Location"
                      variant="outlined"
                      density="compact"
                      v-model="form.location"
                      :error-messages="
                        v$.form.location.$error
                          ? v$.form.location.$errors[0].$message
                          : ''
                      "
                      hide-details="auto"
                    ></v-text-field> -->
                    <!-- <v-checkbox
                      label="Abroad (Specify)"
                      v-model="locationType"
                      :model-value="locationType === 'abroad'"
                      @update:model-value="
                        (value) => (locationType = value ? 'abroad' : null)
                      "
                    ></v-checkbox> -->

                  </v-col>
                </v-row>
              </v-col>
              <v-col cols="12" md="6" v-if="leaveApplicationForm.leaveType === 'Sick Leave'">
                <p class="mb-3">Details of Leave</p>
                <v-row>
                  <v-col cols="12">
                    <p class="mb-3" style="font-style: Italic">
                      In case of Sick Leave:
                    </p>
                    <v-checkbox
                      label="In Hospital  (Specify Illness)"
                      v-model="sickLeaveType"
                      :model-value="sickLeaveType === 'hospital'"
                      @update:model-value="
                        (value) => (sickLeaveType = value ? 'hospital' : null)
                      "
                    ></v-checkbox>
                    <v-text-field
                      v-if="sickLeaveType === 'hospital'"
                      label="Illness"
                      variant="outlined"
                      density="compact"
                      rounded="lg"
                      v-model="leaveApplicationForm.illness"
                      :error-messages="
                        v$.leaveApplicationForm.illness.$error
                          ? v$.leaveApplicationForm.illness.$errors[0].$message
                          : ''
                      "
                      hide-details="auto"
                    ></v-text-field>
                    <v-checkbox
                      label="Out Patient (Specify Illness)"
                      v-model="sickLeaveType"
                      :model-value="sickLeaveType === 'outPatient'"
                      @update:model-value="
                        (value) => (sickLeaveType = value ? 'outPatient' : null)
                      "
                    ></v-checkbox>
                    <v-text-field
                      v-if="sickLeaveType === 'outPatient'"
                      label="Illness"
                      variant="outlined"
                      density="compact"
                      rounded="lg"
                      v-model="leaveApplicationForm.illness"
                      :error-messages="
                        v$.leaveApplicationForm.illness.$error
                          ? v$.leaveApplicationForm.illness.$errors[0].$message
                          : ''
                      "
                      hide-details="auto"
                    ></v-text-field>
                  </v-col>
                </v-row>
              </v-col>

              <v-col
                cols="12"
                md="6"
                v-if="leaveApplicationForm.leaveType === 'Special Leave Benefits for Women'"
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
                      hide-details
                    ></v-text-field>
                  </v-col>
                </v-row>
              </v-col>

              <v-col cols="12" md="6" v-if="leaveApplicationForm.leaveType === 'Study Leave'">
                <p class="mb-3">Details of Leave</p>
                <v-row>
                  <v-col cols="12">
                    <p class="mb-3" style="font-style: Italic">
                      In case of Study Leave:
                    </p>
                    <v-checkbox label="Completion of Master's Degree">
                    </v-checkbox>
                    <v-checkbox
                      label="BAR/Board Examination Review"
                    ></v-checkbox>
                  </v-col>
                </v-row>
              </v-col>

              <v-col cols="12" md="6" v-if="leaveApplicationForm.leaveType === 'Others'">
                <p class="mb-3">Details of Leave</p>
                <v-row>
                  <v-col cols="12">
                    <p class="mb-3" style="font-style: Italic">
                      Other Purpose:
                    </p>
                    <v-checkbox
                      label="Monetization of Leave Credits"
                    ></v-checkbox>
                    <v-checkbox label="Terminal Leave"> </v-checkbox>
                  </v-col>
                </v-row>
              </v-col>

              <v-col cols="12" md="6" v-if="leaveApplicationForm.leaveType !== ''">
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
                  <ButtonSuccess name="Apply" @click="handleSubmit()" />
                </div>
              </v-col>
            </v-row>
          </v-form>
        </v-card-text>
      </v-card>
    </v-col>
  </v-row>
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
} from "@vuelidate/validators";
import MyLeavesTabs from "@/components/MyLeavesTabs.vue";
import PageOnBuild from "@/components/Errors/PageOnBuild.vue";

export default {
  layout: SidebarLayout,

  components: {
    PageOnBuild,
    MyLeavesTabs,
    TableWrapper,
    ButtonSuccess,
    ButtonMuted,
  },
  props:{
    errors: Object,
    leaveTypes: Object,
    leaveApplication: Object,
  },
  data() {
    return {
      isPanelOpen: 0,
      activeTab: "apply",
      pageBeingBuilt: false,
      showLeaveDurationOption: false,
      showPartialDaysOption: false,
      duration: ['Full Day', 'Half Day - Morning', 'Half Day - Afternoon'],
      partialDays: ['All Days', 'Start Date Only', 'End Date Only', 'Start and End Date'],
      dayList: ['Half Day - Morning', 'Half Day - Afternoon'],

      leaveType: null,
      locationType: null,
      sickLeaveType: null,
      commutationType: null,
      fromDate: null,
      toDate: null,
      workingDays: 0,

      v$: useVuelidate(),
      leaveApplicationForm: useForm({
        id: this.leaveApplication.id,
        from: this.leaveApplication.from,
        to: this.leaveApplication.to,
        leaveType: this.leaveApplication.leave_id,
        locationType: "",
        sickLeaveType: "",
        workingDays: "",
        reason: "",
        otherPurpose: "",
        commutation: "",
        illness: "",
        location: "",
        location_within_philippines: "",
        location_abroad: "",
        in_hospital: "",
        out_hospital: "",
        leave_duration: "",
        study_leave_application: "",
        partial_days: "",
        start_day: "",
        end_day: "",
      }),
    };
  },

  validations() {
    return {
      leaveApplicationForm: {
        from: { required },
        to: { required },
        leaveType: { required },
        reason: {
          required: (value) =>
            this.leaveApplicationForm.leaveType === "Others" ? required.$validator(value) : true,
        },
        location: {
          required: (value) =>
            this.locationType ? required.$validator(value) : true,
        },
        illness: {
          required: (value) =>
            this.sickLeaveType ? required.$validator(value) : true,
        },
        leave_duration: {
          required: (value) =>
            this.leaveApplicationForm.leave_duration ? required.$validator(value) : true,
        },
        study_leave_application: {
          required: (value) =>
            this.leaveApplicationForm.leaveType === "Study Leave" ? required.$validator(value) : true,
        },
      },
    };
  },



  methods: {


    resetForm() {
      this.leaveApplicationForm.reset();
      this.locationType = null;
      this.sickLeaveType = null;
      this.commutationType = null;
      this.workingDays = 0;
      this.leaveApplicationForm.leave_duration = null;
      this.leaveApplicationForm.study_leave_application = null;
      this.leaveApplicationForm.partial_days = null;
      this.leaveApplicationForm.start_day = null;
      this.leaveApplicationForm.end_day = null;
      this.showLeaveDurationOption = false;
      this.showPartialDaysOption = false;
      this.v$.$reset();
    },

    calculateWorkingDays() {

      if (!this.leaveApplicationForm.from || !this.leaveApplicationForm.to) {
        this.workingDays = 0;
        return;
      }

      const startDate = new Date(this.leaveApplicationForm.from);
      const endDate = new Date(this.leaveApplicationForm.to);

      // Check if end date is before start date
      if (endDate < startDate) {
        this.workingDays = 0;
        return;
      }

      let workingDays = 0;
      const currentDate = new Date(startDate);

      while (currentDate <= endDate) {
        // Get day of week (0 = Sunday, 6 = Saturday)
        const dayOfWeek = currentDate.getDay();

        // Only count weekdays (Monday = 1, Tuesday = 2, ..., Friday = 5)
        if (dayOfWeek >= 1 && dayOfWeek <= 5) {
          workingDays++;
        }

        // Move to next day
        currentDate.setDate(currentDate.getDate() + 1);
      }

      this.workingDays = workingDays;
      this.leaveApplicationForm.workingDays = workingDays;

      if(workingDays === 1){
        this.showLeaveDurationOption = true;
      }else{
        this.showLeaveDurationOption = false;
        this.showPartialDaysOption = true;
      }

    },

    handleSubmit() {
      // Update form with current radio button values
      this.leaveApplicationForm.locationType = this.locationType;
      this.leaveApplicationForm.sickLeaveType = this.sickLeaveType;
      this.leaveApplicationForm.commutation = this.commutationType;
      console.log(this.leaveApplicationForm.sickLeaveType);
      if(this.locationType === 'within'){
        this.leaveApplicationForm.location_within_philippines = this.leaveApplicationForm.location;
      }else{
        this.leaveApplicationForm.location_abroad = this.leaveApplicationForm.location;
      }

      if(this.sickLeaveType === 'hospital'){
        this.leaveApplicationForm.in_hospital = 'true';
        this.leaveApplicationForm.out_hospital = '';
      }else if(this.sickLeaveType === 'outPatient'){
        this.leaveApplicationForm.out_hospital = 'true';
        this.leaveApplicationForm.in_hospital = '';
      }

      this.v$.leaveApplicationForm.$validate();
      if (!this.v$.leaveApplicationForm.$invalid) {
        console.log('Form data being submitted:', this.leaveApplicationForm);
        this.leaveApplicationForm.post(route('self-service.my-leaves.store'), {
          onSuccess: () => {
            this.showToast('Leave application submitted successfully', 'success');
            this.resetForm();
          },
          onError: (errors) => {
            const errorMessages = Object.values(errors).flat().join(" ");
            this.showToast(`${errorMessages}`, "error");
          }
        });
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
