<template>
  <!-- Work Experience Form -->
  <v-form @submit.prevent="submitForm()">
    <v-row>
      <v-col cols="12">
        <v-card-title class="d-flex align-center justify-space-between">
          Work Experience
          <v-btn
            class="starbucks-green"
            size="x-small"
            icon
            @click="addWorkExperience"
            :disabled="isFormEditable"
          >
            <v-icon>mdi-plus</v-icon>
          </v-btn>
        </v-card-title>
      </v-col>
      <v-col
        v-for="(work, index) in workExperienceForm.workExperiences"
        :key="index"
        cols="12"
      >
        <div
          class="pa-4 rounded-lg mb-4"
          :class="index % 2 === 0 ? 'bg-white' : 'bg-grey-lighten-4'"
        >
          <v-row>
            <input type="hidden" v-model="work.id" />

            <v-col cols="12" md="6">
              <v-text-field
                density="compact"
                variant="outlined"
                type="date"
                :label="`From`"
                v-model="work.from"
                :disabled="isFormEditable"
                rounded="lg"
                :error-messages="
                  v$.workExperienceForm.workExperiences.$each[
                    index
                  ]?.from?.$errors.map((e) => e.$message) || []
                "
              />
            </v-col>

            <v-col cols="12" md="6">
              <v-text-field
                v-if="!work.isPresent"
                density="compact"
                variant="outlined"
                type="date"
                :label="`To`"
                v-model="work.to"
                :disabled="isFormEditable"
                rounded="lg"
                :error-messages="
                  v$.workExperienceForm.workExperiences.$each[
                    index
                  ]?.to?.$errors.map((e) => e.$message) || []
                "
              />
              <v-text-field
                v-else
                density="compact"
                variant="outlined"
                value="PRESENT"
                readonly
                rounded="lg"
              />
              <v-checkbox
                v-model="work.isPresent"
                label="Currently employed here"
                :disabled="isFormEditable"
                hide-details
                density="compact"
              />
            </v-col>

            <v-col cols="12" md="6">
              <v-text-field
                density="compact"
                variant="outlined"
                :label="`Position Title`"
                v-model="work.position_title"
                :disabled="isFormEditable"
                rounded="lg"
                :error-messages="
                  v$.workExperienceForm.workExperiences.$each[
                    index
                  ]?.position_title?.$errors.map((e) => e.$message) || []
                "
              />
            </v-col>

            <v-col cols="12" md="6">
              <v-text-field
                density="compact"
                variant="outlined"
                :label="`Department/Agency/Office/Company`"
                v-model="work.department_agency"
                :disabled="isFormEditable"
                rounded="lg"
                :error-messages="
                  v$.workExperienceForm.workExperiences.$each[
                    index
                  ]?.department_agency?.$errors.map((e) => e.$message) || []
                "
              />
            </v-col>

            <v-col cols="12" md="3">
              <v-text-field
                density="compact"
                variant="outlined"
                :label="`Monthly Salary`"
                v-model="work.monthly_salary"
                :disabled="isFormEditable"
                rounded="lg"
                :error-messages="
                  v$.workExperienceForm.workExperiences.$each[
                    index
                  ]?.monthly_salary?.$errors.map((e) => e.$message) || []
                "
              />
            </v-col>

            <v-col cols="12" md="3">
              <v-text-field
                density="compact"
                variant="outlined"
                :label="`Salary/Job/Pay Grade`"
                v-model="work.salary_grade"
                :disabled="isFormEditable"
              />
            </v-col>

            <v-col cols="12" md="3">
              <v-select
                density="compact"
                variant="outlined"
                label="Status of Appointment"
                v-model="work.status_of_appointment"
                :items="statusOfAppointmentOptions"
                :disabled="isFormEditable"
                rounded="lg"
                :error-messages="
                  v$.workExperienceForm.workExperiences.$each[
                    index
                  ]?.status_of_appointment?.$errors.map((e) => e.$message) || []
                "
              />
            </v-col>

            <v-col cols="12" md="2">
              <v-select
                density="compact"
                variant="outlined"
                :label="`GOV'T SERVICE (Y/N)`"
                v-model="work.government_service"
                :disabled="isFormEditable"
                :items="govtService"
                item-title="title"
                item-value="value"
                rounded="lg"
                :error-messages="
                  v$.workExperienceForm.workExperiences.$each[
                    index
                  ]?.government_service?.$errors.map((e) => e.$message) || []
                "
              />
            </v-col>

            <v-col cols="12" md="1" class="d-flex align-center justify-end">
              <v-btn
                variant="tonal"
                color="error"
                size="x-small"
                icon
                @click="removeWorkExperience(index)"
                :disabled="isFormEditable"
              >
                <v-icon>mdi-delete</v-icon>
              </v-btn>
            </v-col>
          </v-row>
        </div>
      </v-col>
    </v-row>

    <v-row>
      <v-col cols="12">
        <div class="d-flex align-center justify-end">
          <v-btn
            type="submit"
            rounded="xl"
            class="starbucks-green"
            min-width="120"
            :disabled="isFormEditable"
          >
            Save
          </v-btn>
        </div>
      </v-col>
    </v-row>
  </v-form>

  <!-- Delete Dialog -->
  <DeleteDialog
    v-model="isDeleteDialog"
    message="Are you sure you want to delete this work experience?"
    :loading="loading"
    @confirm="handleDeleteWorkExperience"
    @cancel="isDeleteDialog = false"
  />

  <!-- <pre>{{ employeeWorkExperiences }}</pre> -->
</template>
<script>
import { useForm } from "@inertiajs/vue3";
import { helpers, minLength } from "@vuelidate/validators";
import useVuelidate from "@vuelidate/core";
import DeleteDialog from "@/components/DeleteDialog.vue";

export default {
  props: {
    isFormEditable: {
      type: Boolean,
      default: null,
    },

    employee_id: String,
    employeeWorkExperiences: Object,
  },

  components: {
    DeleteDialog,
  },

  data() {
    const experiences = this.employeeWorkExperiences?.data || [];

    return {
      v$: useVuelidate(),

      statusOfAppointmentOptions: [
        "Permanent",
        "Temporary",
        "Substitute",
        "Coterminous",
        "Fixed Term",
        "Contractual",
        "Casual",
        "Contract of Service",
        "Job Order",
        "Probationary",
        "Project-Based",
        "Seasonal",
      ],

      isDeleteDialog: false,
      selectedWorkExperience: null,

      govtService: [
        {
          title: "Yes",
          value: "Y",
        },
        {
          title: "No",
          value: "N",
        },
      ],

      workExperienceForm: useForm({
        workExperiences: experiences.length
          ? experiences.map((e) => ({
              id: e.id || "",
              from: e.from || "",
              to: e.to == "PRESENT" ? "" : e.to || "",
              isPresent: e.to == "PRESENT" ? true : false,
              position_title: e.position_title || "",
              department_agency: e.department_agency || "",
              monthly_salary: e.monthly_salary || "",
              salary_grade: e.salary_grade || "",
              status_of_appointment: e.status_of_appointment || "",
              government_service: e.government_service || "",
            }))
          : [
              {
                id: "",
                from: "",
                to: "",
                isPresent: false,
                position_title: "",
                department_agency: "",
                monthly_salary: "",
                salary_grade: "",
                status_of_appointment: "",
                government_service: "",
              },
            ],
      }),
    };
  },

  watch: {
    employeeWorkExperiences: {
      handler(newVal) {
        const data = newVal?.data || [];
        this.workExperienceForm.workExperiences = data.length
          ? data.map((e) => ({
              id: e.id || "",
              from: e.from || "",
              to: e.to == "PRESENT" ? "" : e.to || "",
              isPresent: e.to == "PRESENT" ? true : false,
              position_title: e.position_title || "",
              department_agency: e.department_agency || "",
              monthly_salary: e.monthly_salary || "",
              salary_grade: e.salary_grade || "",
              status_of_appointment: e.status_of_appointment || "",
              government_service: e.government_service || "",
            }))
          : [
              {
                id: "",
                from: "",
                to: "",
                isPresent: false,
                position_title: "",
                department_agency: "",
                monthly_salary: "",
                salary_grade: "",
                status_of_appointment: "",
                government_service: "",
              },
            ];
      },
      deep: true,
      immediate: true,
    },
  },

  validations() {
    return {
      workExperienceForm: {
        workExperiences: {
          $each: helpers.forEach({
            from: {
              minLength: minLength(0),
            },
            to: {
              minLength: minLength(0),
            },
            position_title: {
              minLength: minLength(0),
            },
            department_agency: {
              minLength: minLength(0),
            },
            monthly_salary: {
              minLength: minLength(0),
            },
            salary_grade: {
              minLength: minLength(0),
            },
            status_of_appointment: {
              minLength: minLength(0),
            },
            government_service: {
              minLength: minLength(0),
            },
          }),
        },
      },
    };
  },

  methods: {
    addWorkExperience() {
      this.workExperienceForm.workExperiences.push({
        id: "",
        from: "",
        to: "",
        position_title: "",
        department_agency: "",
        monthly_salary: "",
        salary_grade: "",
        status_of_appointment: "",
        government_service: "",
      });
    },

    submitForm() {
      if (
        this.workExperienceForm.workExperiences[0].from === "" &&
        this.workExperienceForm.workExperiences.length === 1
      ) {
        this.showToast("Please add at least one work experience.", "error");
        return;
      }

      // If checkbox is checked, set 'to' to 'PRESENT' string
      this.workExperienceForm.workExperiences.forEach((work) => {
        if (work.isPresent) {
          work.to = "PRESENT";
        }
      });

      if (
        this.workExperienceForm.workExperiences.some(
          (work) =>
            work.from === "" ||
            work.to === "" ||
            work.position_title === "" ||
            work.department_agency === "" ||
            work.monthly_salary === "" ||
            work.salary_grade === "" ||
            work.status_of_appointment === "" ||
            work.government_service === ""
        )
      ) {
        this.v$.$touch();
        this.showToast("Please fill in all required fields.", "error");
        return;
      }
      if (route().current().startsWith("self-service.")) {
        this.workExperienceForm.put(
          route(
            "self-service.my-profile.updateEmployeeWorkExperience",
            { id: this.employee_id } // <-- this is the second argument to route()
          ),
          {
            onSuccess: () => {
              this.showToast("Work Experience updated successfully", "success");
              this.$emit("workExperienceRefresh");
            },
            onError: (errors) => {
              const errorMessages = Object.values(errors).flat().join(" ");
              this.showToast(`${errorMessages}`, "error");
            },
          }
        );
      } else {
        this.workExperienceForm.put(
          route(
            "hrmanagement.employee.updateEmployeeWorkExperience",
            { id: this.employee_id } // <-- this is the second argument to route()
          ),
          {
            onSuccess: () => {
              this.showToast("Work Experience updated successfully", "success");
              this.$emit("workExperienceRefresh");
            },
            onError: (errors) => {
              const errorMessages = Object.values(errors).flat().join(" ");
              this.showToast(`${errorMessages}`, "error");
            },
          }
        );
      }
    },

    removeWorkExperience(index) {
      const workExperienceId =
        this.workExperienceForm.workExperiences[index].id;

      if (workExperienceId) {
        this.selectedWorkExperience = workExperienceId;
        this.isDeleteDialog = true;
      } else {
        this.workExperienceForm.workExperiences.splice(index, 1);
      }
    },

    handleDeleteWorkExperience() {
      this.$inertia.delete(
        route("hrmanagement.employee.deleteEmployeeWorkExperience", {
          id: this.selectedWorkExperience,
        }),
        {
          onSuccess: () => {
            this.showToast("Work Experience successfully deleted", "success");
            this.isDeleteDialog = false;
            this.$emit("workExperienceRefresh");
          },
          onError: (errors) => {
            this.isDeleteDialog = false;
            const errorMessages = Object.values(errors).flat().join(" ");
            this.showToast(errorMessages, "error");
          },
        }
      );
    },
  },
};
</script>
