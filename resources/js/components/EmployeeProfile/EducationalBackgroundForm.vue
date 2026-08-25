<template>
  <!-- Educational Background Form -->
  <v-form @submit.prevent="submitForm()">
    <v-row>
      <v-col cols="12">
        <v-card-title class="d-flex align-center justify-space-between"
          >Elementary
          <v-btn
            variant="tonal"
            icon="mdi-plus"
            size="x-small"
            class="starbucks-green"
            @click="addSchool('elementary')"
            :disabled="isFormEditable"
          ></v-btn>
        </v-card-title>
      </v-col>
      <template
        v-for="(school, index) in educationalBackgroundForm.elementary"
        :key="index"
      >
        <v-row
          :class="index % 2 === 0 ? 'bg-white' : 'bg-grey-lighten-4'"
          class="pa-2 rounded-lg"
        >
          <v-col cols="12" md="6">
            <v-text-field
              density="compact"
              variant="outlined"
              label="Name of School"
              rounded="lg"
              v-model="school.name_of_school"
              :disabled="isFormEditable"
            />
          </v-col>
          <v-col cols="12" md="6">
            <v-text-field
              density="compact"
              variant="outlined"
              label="Basic Education/Degree/Course"
              rounded="lg"
              v-model="school.degree_course"
              :disabled="isFormEditable"
            />
          </v-col>

          <v-col cols="12" md="3">
            <v-text-field
              density="compact"
              variant="outlined"
              label="From (Year)"
              rounded="lg"
              v-model="school.period_from"
              :disabled="isFormEditable"
            />
          </v-col>
          <v-col cols="12" md="3">
            <v-text-field
              density="compact"
              variant="outlined"
              label="To (Year)"
              rounded="lg"
              v-model="school.period_to"
              :disabled="isFormEditable"
            />
          </v-col>

          <v-col cols="12" md="6">
            <v-text-field
              density="compact"
              variant="outlined"
              label="Highest Level/Units Earned (If not Graduated)"
              rounded="lg"
              v-model="school.highest_level"
              :disabled="isFormEditable"
            />
          </v-col>
          <v-col cols="12" md="6">
            <v-text-field
              density="compact"
              variant="outlined"
              label="Year Graduated"
              rounded="lg"
              v-model="school.year_graduated"
              :disabled="isFormEditable"
            />
          </v-col>

          <v-col cols="12" md="5">
            <v-text-field
              density="compact"
              variant="outlined"
              label="Scholarship / Academic Honors Received"
              rounded="lg"
              v-model="school.academic_award"
              :disabled="isFormEditable"
            />
          </v-col>

          <v-col cols="12" md="1" class="d-flex align-center">
            <v-btn
              variant="tonal"
              icon="mdi-delete"
              size="x-small"
              color="red-darken-4"
              @click="removeSchool('elementary', index)"
              :disabled="isFormEditable"
            />
          </v-col>
        </v-row>
      </template>

      <v-col cols="12">
        <v-card-title class="d-flex align-center justify-space-between">
          Secondary
          <v-btn
            variant="tonal"
            icon="mdi-plus"
            size="x-small"
            class="starbucks-green"
            @click="addSchool('secondary')"
            :disabled="isFormEditable"
          ></v-btn>
        </v-card-title>
      </v-col>

      <template
        v-for="(school, index) in educationalBackgroundForm.secondary"
        :key="index"
      >
        <v-row
          :class="index % 2 === 0 ? 'bg-white' : 'bg-grey-lighten-4'"
          class="pa-2 rounded-lg"
        >
          <v-col cols="12" md="6">
            <v-text-field
              density="compact"
              variant="outlined"
              label="Name of School"
              rounded="lg"
              v-model="school.name_of_school"
              :disabled="isFormEditable"
            ></v-text-field>
          </v-col>
          <v-col cols="12" md="6">
            <v-text-field
              density="compact"
              variant="outlined"
              label="Basic Education/Degree/Course"
              rounded="lg"
              v-model="school.degree_course"
              :disabled="isFormEditable"
            ></v-text-field>
          </v-col>
          <v-col cols="12" md="3">
            <v-text-field
              density="compact"
              variant="outlined"
              label="From (Year)"
              rounded="lg"
              v-model="school.period_from"
              :disabled="isFormEditable"
            ></v-text-field>
          </v-col>
          <v-col cols="12" md="3">
            <v-text-field
              density="compact"
              variant="outlined"
              label="To (Year)"
              rounded="lg"
              v-model="school.period_to"
              :disabled="isFormEditable"
            ></v-text-field>
          </v-col>
          <v-col cols="12" md="6">
            <v-text-field
              density="compact"
              variant="outlined"
              label="Highest Level/Units Earned(If not Graduated)"
              rounded="lg"
              v-model="school.highest_level"
              :disabled="isFormEditable"
            ></v-text-field>
          </v-col>
          <v-col cols="12" md="6">
            <v-text-field
              density="compact"
              variant="outlined"
              label="Year Graduated"
              rounded="lg"
              v-model="school.year_graduated"
              :disabled="isFormEditable"
            ></v-text-field>
          </v-col>
          <v-col cols="12" md="5">
            <v-text-field
              density="compact"
              variant="outlined"
              label="Scholarship / Academic Honors Received"
              rounded="lg"
              v-model="school.academic_award"
              :disabled="isFormEditable"
            ></v-text-field>
          </v-col>

          <v-col cols="12" md="1" :disabled="isFormEditable">
            <v-btn
              variant="tonal"
              icon="mdi-delete"
              size="x-small"
              color="red-darken-4"
              @click="removeSchool('secondary', index)"
              :disabled="isFormEditable"
            ></v-btn>
          </v-col>
        </v-row>
      </template>
      <v-divider
        v-if="index < educationalBackgroundForm.secondary.length - 1"
        style="border: 1px solid green"
      />

      <v-col cols="12">
        <v-card-title class="d-flex align-center justify-space-between">
          Vocational/Trade Course
          <v-btn
            variant="tonal"
            icon="mdi-plus"
            size="x-small"
            class="starbucks-green"
            @click="addSchool('vocational')"
            :disabled="isFormEditable"
          ></v-btn>
        </v-card-title>
      </v-col>
      <template
        v-for="(school, index) in educationalBackgroundForm.vocational"
        :key="index"
      >
        <v-row
          :class="index % 2 === 0 ? 'bg-white' : 'bg-grey-lighten-4'"
          class="pa-2 rounded-lg"
        >
          <v-col cols="12" md="6">
            <v-text-field
              density="compact"
              variant="outlined"
              label="Name of School"
              rounded="lg"
              v-model="school.name_of_school"
              :disabled="isFormEditable"
            ></v-text-field>
          </v-col>
          <v-col cols="12" md="6">
            <v-text-field
              density="compact"
              variant="outlined"
              label="Basic Education/Degree/Course"
              rounded="lg"
              v-model="school.degree_course"
              :disabled="isFormEditable"
            ></v-text-field>
          </v-col>
          <v-col cols="12" md="3">
            <v-text-field
              density="compact"
              variant="outlined"
              label="From (Year)"
              rounded="lg"
              v-model="school.period_from"
              :disabled="isFormEditable"
            ></v-text-field>
          </v-col>
          <v-col cols="12" md="3">
            <v-text-field
              density="compact"
              variant="outlined"
              label="To (Year)"
              rounded="lg"
              v-model="school.period_to"
              :disabled="isFormEditable"
            ></v-text-field>
          </v-col>
          <v-col cols="12" md="6">
            <v-text-field
              density="compact"
              variant="outlined"
              label="Highest Level/Units Earned(If not Graduated)"
              rounded="lg"
              v-model="school.highest_level"
              :disabled="isFormEditable"
            ></v-text-field>
          </v-col>
          <v-col cols="12" md="6">
            <v-text-field
              density="compact"
              variant="outlined"
              label="Year Graduated"
              rounded="lg"
              v-model="school.year_graduated"
              :disabled="isFormEditable"
            ></v-text-field>
          </v-col>
          <v-col cols="12" md="5">
            <v-text-field
              density="compact"
              variant="outlined"
              label="Scholarship / Academic Honors Received"
              rounded="lg"
              v-model="school.academic_award"
              :disabled="isFormEditable"
            ></v-text-field>
          </v-col>

          <v-col cols="12" md="1" :disabled="isFormEditable">
            <v-btn
              variant="tonal"
              icon="mdi-delete"
              size="x-small"
              color="error"
              @click="removeSchool('vocational', index)"
              :disabled="isFormEditable"
            ></v-btn>
          </v-col>
        </v-row>
      </template>

      <v-divider
        v-if="index < educationalBackgroundForm.vocational.length - 1"
        style="border: 1px solid green"
      />

      <v-col cols="12">
        <v-card-title class="d-flex align-center justify-space-between">
          College
          <v-btn
            variant="tonal"
            icon="mdi-plus"
            size="x-small"
            class="starbucks-green"
            @click="addSchool('college')"
            :disabled="isFormEditable"
          ></v-btn>
        </v-card-title>
      </v-col>
      <template
        v-for="(school, index) in educationalBackgroundForm.college"
        :key="index"
      >
        <v-row
          :class="index % 2 === 0 ? 'bg-white' : 'bg-grey-lighten-4'"
          class="pa-2 rounded-lg"
        >
          <v-col cols="12" md="6">
            <v-text-field
              density="compact"
              variant="outlined"
              label="Name of School"
              rounded="lg"
              v-model="school.name_of_school"
              :disabled="isFormEditable"
            ></v-text-field>
          </v-col>
          <v-col cols="12" md="6">
            <v-text-field
              density="compact"
              variant="outlined"
              label="Basic Education/Degree/Course"
              rounded="lg"
              v-model="school.degree_course"
              :disabled="isFormEditable"
            ></v-text-field>
          </v-col>
          <v-col cols="12" md="3">
            <v-text-field
              density="compact"
              variant="outlined"
              label="From (Year)"
              rounded="lg"
              v-model="school.period_from"
              :disabled="isFormEditable"
            ></v-text-field>
          </v-col>
          <v-col cols="12" md="3">
            <v-text-field
              density="compact"
              variant="outlined"
              label="To (Year)"
              rounded="lg"
              v-model="school.period_to"
              :disabled="isFormEditable"
            ></v-text-field>
          </v-col>
          <v-col cols="12" md="6">
            <v-text-field
              density="compact"
              variant="outlined"
              label="Highest Level/Units Earned(If not Graduated)"
              rounded="lg"
              v-model="school.highest_level"
              :disabled="isFormEditable"
            ></v-text-field>
          </v-col>
          <v-col cols="12" md="6">
            <v-text-field
              density="compact"
              variant="outlined"
              label="Year Graduated"
              rounded="lg"
              v-model="school.year_graduated"
              :disabled="isFormEditable"
            ></v-text-field>
          </v-col>
          <v-col cols="12" md="5">
            <v-text-field
              density="compact"
              variant="outlined"
              label="Scholarship / Academic Honors Received"
              rounded="lg"
              v-model="school.academic_award"
              :disabled="isFormEditable"
            ></v-text-field>
          </v-col>
          <v-col cols="12" md="1" :disabled="isFormEditable">
            <v-btn
              variant="tonal"
              icon="mdi-delete"
              size="x-small"
              color="error"
              @click="removeSchool('college', index)"
              :disabled="isFormEditable"
            ></v-btn>
          </v-col>
        </v-row>
      </template>

      <v-divider
        v-if="index < educationalBackgroundForm.college.length - 1"
        style="border: 1px solid green"
      />

      <v-col cols="12">
        <v-card-title class="d-flex align-center justify-space-between">
          Graduate Studies
          <v-btn
            variant="tonal"
            icon="mdi-plus"
            size="x-small"
            class="starbucks-green"
            @click="addSchool('graduate_studies')"
            :disabled="isFormEditable"
          ></v-btn>
        </v-card-title>
      </v-col>
      <template
        v-for="(study, index) in educationalBackgroundForm.graduate_studies"
        :key="index"
      >
        <v-row
          :class="index % 2 === 0 ? 'bg-white' : 'bg-grey-lighten-4'"
          class="pa-2 rounded-lg"
        >
          <v-col cols="12" md="6">
            <v-text-field
              density="compact"
              variant="outlined"
              label="Name of School"
              rounded="lg"
              v-model="study.name_of_school"
              :disabled="isFormEditable"
            ></v-text-field>
          </v-col>
          <v-col cols="12" md="6">
            <v-text-field
              density="compact"
              variant="outlined"
              label="Basic Education/Degree/Course"
              rounded="lg"
              v-model="study.degree_course"
              :disabled="isFormEditable"
            ></v-text-field>
          </v-col>
          <v-col cols="12" md="3">
            <v-text-field
              density="compact"
              variant="outlined"
              label="From (Year)"
              rounded="lg"
              v-model="study.period_from"
              :disabled="isFormEditable"
            ></v-text-field>
          </v-col>
          <v-col cols="12" md="3">
            <v-text-field
              density="compact"
              variant="outlined"
              label="To (Year)"
              rounded="lg"
              v-model="study.period_to"
              :disabled="isFormEditable"
            ></v-text-field>
          </v-col>
          <v-col cols="12" md="6">
            <v-text-field
              density="compact"
              variant="outlined"
              label="Highest Level/Units Earned(If not Graduated)"
              rounded="lg"
              v-model="study.highest_level"
              :disabled="isFormEditable"
            ></v-text-field>
          </v-col>
          <v-col cols="12" md="6">
            <v-text-field
              density="compact"
              variant="outlined"
              label="Year Graduated"
              rounded="lg"
              v-model="study.year_graduated"
              :disabled="isFormEditable"
            ></v-text-field>
          </v-col>
          <v-col cols="12" md="5">
            <v-text-field
              density="compact"
              variant="outlined"
              label="Scholarship / Academic Honors Received"
              rounded="lg"
              v-model="study.academic_award"
              :disabled="isFormEditable"
            ></v-text-field>
          </v-col>
          <v-col cols="12" md="1" :disabled="isFormEditable">
            <v-btn
              variant="tonal"
              icon="mdi-delete"
              size="x-small"
              color="error"
              @click="removeSchool('graduate_studies', index)"
              :disabled="isFormEditable"
            ></v-btn>
          </v-col>
        </v-row>
      </template>
      <v-divider
        v-if="index < educationalBackgroundForm.graduate_studies.length - 1"
        style="border: 1px solid green"
      />

      <v-col cols="12">
        <div class="d-flex align-center justify-end">
          <v-btn
            type="submit"
            rounded="xl"
            class="starbucks-green"
            min-width="120"
            :disabled="isFormEditable"
            >Save</v-btn
          >
        </div>
      </v-col>
    </v-row>
  </v-form>

  <DeleteDialog
    v-model="isDeleteDialogVisible"
    message="Are you sure to delete this school?"
    :loading="loading"
    @confirm="handleDeleteSchool"
    @cancel="isDeleteDialogVisible = false"
  />
</template>
<script>
import { useForm } from "@inertiajs/vue3";
import useVuelidate from "@vuelidate/core";
import { required, minLength, maxLength, helpers } from "@vuelidate/validators";
import onBuildLogo from "../../../images/coming-soon-design.png";
import DeleteDialog from "@/components/DeleteDialog.vue";

function defaultEntry() {
  return {
    id: "",
    name_of_school: "",
    degree_course: "",
    period_from: "",
    period_to: "",
    highest_level: "",
    year_graduated: "",
    academic_award: "",
  };
}

function mapEducation(arr) {
  return arr?.length
    ? arr.map((item) => ({
        id: item.id || "",
        name_of_school: item.name_of_school || "",
        degree_course: item.degree_course || "",
        period_from: item.period_from || "",
        period_to: item.period_to || "",
        highest_level: item.highest_level || "",
        year_graduated: item.year_graduated || "",
        academic_award: item.academic_award || "",
      }))
    : [defaultEntry()];
}

export default {
  props: {
    isFormEditable: {
      type: Boolean,
      default: null,
    },

    employee_id: String,

    employeeEducationalBackground: Object,
  },

  components: {
    DeleteDialog,
  },

  data() {
    const el = this.employeeEducationalBackground?.data.elementary || {};
    const se = this.employeeEducationalBackground?.data.secondary || {};
    const vo = this.employeeEducationalBackground?.data.vocational || {};
    const co = this.employeeEducationalBackground?.data.college || {};
    const gs = this.employeeEducationalBackground?.data.graduate_studies || {};

    return {
      v$: useVuelidate(),

      //   isFormEditable: true, //temporary

      isDeleteDialogVisible: false,
      selectedSchool: null,

      educationalBackgroundForm: useForm({
        elementary: mapEducation(el),
        secondary: mapEducation(se),
        vocational: mapEducation(vo),
        college: mapEducation(co),
        graduate_studies: mapEducation(gs),
      }),
    };
  },

  watch: {
    employeeEducationalBackground: {
      handler(newVal) {
        if (!newVal?.data) return;

        const levels = [
          "elementary",
          "secondary",
          "vocational",
          "college",
          "graduate_studies",
        ];

        levels.forEach((level) => {
          this.educationalBackgroundForm[level] = mapEducation(
            newVal.data[level] || []
          );
        });
      },
      deep: true,
      immediate: true,
    },
  },

  methods: {
    addSchool(level) {
      const newSchool = {
        id: null,
        name_of_school: null,
        degree_course: null,
        period_from: null,
        period_to: null,
        highest_level: null,
        year_graduated: null,
        academic_award: null,
      };

      if (this.educationalBackgroundForm[level]) {
        this.educationalBackgroundForm[level].push(newSchool);
      }
    },

    removeSchool(level, index) {
      const school = this.educationalBackgroundForm[level][index];

      if (school.id) {
        // Open dialog for backend deletion
        this.isDeleteDialogVisible = true;
        this.selectedSchool = { level, id: school.id }; // store both level + id
      } else {
        // Just remove from local form array
        this.educationalBackgroundForm[level].splice(index, 1);
      }
    },

    submitForm() {
      if (route().current().startsWith("self-service.")) {
        this.educationalBackgroundForm.put(
          route("self-service.my-profile.updateEmployeeEducationalBackground", {
            id: this.employee_id,
          }),
          {
            onSuccess: () => {
              this.showToast(
                "Educational Background successfully updated.",
                "success"
              );
              this.$emit("educationalBackgroundRefresh");
            },

            onError: (errors) => {
              const errorMessages = Object.values(errors).flat().join(" ");
              this.showToast(errorMessages, "error");
            },
          }
        );
      } else {
        this.educationalBackgroundForm.put(
          route("hrmanagement.employee.updateEmployeeEducationalBackground", {
            id: this.employee_id,
          }),
          {
            onSuccess: () => {
              this.showToast(
                "Educational Background successfully updated.",
                "success"
              );
              this.$emit("educationalBackgroundRefresh");
            },

            onError: (errors) => {
              const errorMessages = Object.values(errors).flat().join(" ");
              this.showToast(errorMessages, "error");
            },
          }
        );
      }
    },

    handleDeleteSchool() {
      this.$inertia.delete(
        route("hrmanagement.employee.deleteEmployeeEducationalBackground", {
          id: this.selectedSchool.id,
          level: this.selectedSchool.level,
        }),
        {
          onSuccess: () => {
            this.isDeleteDialogVisible = false;
            this.showToast("School successfully deleted", "success");
            this.$emit("educationalBackgroundRefresh");
          },

          onError: (errors) => {
            this.isDeleteDialogVisible = false;
            const errorMessages = Object.values(errors).flat().join(" ");

            this.showToast(`${errorMessages}`, "error");
          },
        }
      );
    },
  },
};
</script>
