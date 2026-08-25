<template>
  <EmployeeTabs v-model:activeTab="activeTab" />
  <PageOnBuild v-if="pageIsOnBuild" />
  <v-card>
    <v-card-text>
      <v-form @submit.prevent="generateReport()">
        <v-row>
          <v-col cols="12" md="6">
            <v-select
              variant="outlined"
              density="compact"
              rounded="lg"
              label="Operating Unit"
              :items="operatingUnits"
              item-title="name"
              item-value="id"
              v-model="form.operating_unit_id"
              :readonly="authUser === false"
              @update:model-value="form.department_id = null"
              :clearable="authUser === true"
            ></v-select>
          </v-col>

          <v-col cols="12" md="6">
            <v-autocomplete
              variant="outlined"
              density="compact"
              rounded="lg"
              hide-details
              label="Department"
              :items="filteredDepartments"
              item-title="name"
              item-value="id"
              v-model="form.department_id"
            ></v-autocomplete>
          </v-col>

          <v-col cols="12" md="3">
            <v-select
              label="Employment Status"
              variant="outlined"
              density="compact"
              :items="jobStatus"
              item-title="name"
              item-value="id"
              v-model="form.job_status"
            ></v-select>
          </v-col>
          <v-col cols="12" md="3">
            <v-select
              label="Employee Type"
              variant="outlined"
              density="compact"
              :items="employeeType"
              v-model="form.employee_type"
            ></v-select>
          </v-col>

          <!-- <v-col cols="12" md="6">
              <v-autocomplete
                label="Position"
                variant="outlined"
                density="compact"
              ></v-autocomplete>
            </v-col>
            <v-col cols="12" md="3">
              <v-autocomplete
                label="Salary Grade"
                variant="outlined"
                density="compact"
              ></v-autocomplete>
            </v-col>
            <v-col cols="12" md="3">
              <v-select
                label="Salary Step"
                variant="outlined"
                density="compact"
              ></v-select>
            </v-col> -->

          <v-col cols="12">
            <div class="v-card-title h6">Personal Information</div>
            <v-row>
              <v-col
                v-for="column in personalInformation"
                :key="column.value"
                cols="12"
                md="2"
              >
                <v-checkbox
                  v-model="selectedPersonalInformation"
                  :label="column.title"
                  :value="column.value"
                ></v-checkbox>
              </v-col>
            </v-row>
          </v-col>

          <v-col cols="12">
            <!-- <div class="v-card-title h6">Family Background</div>
            <v-row>
              <v-col
                v-for="column in familyBackground"
                :key="column.value"
                cols="12"
                md="2"
              >
                <v-checkbox
                  v-model="selectedFamilyBackground"
                  :label="column.title"
                  :value="column.value"
                ></v-checkbox>
              </v-col>
            </v-row> -->

            <v-col cols="12">
              <div class="v-card-title h6">Job Details</div>
              <v-row>
                <v-col
                  v-for="column in jobDetails"
                  :key="column.value"
                  cols="12"
                  md="2"
                >
                  <v-checkbox
                    v-model="selectedJobDetails"
                    :label="column.title"
                    :value="column.value"
                  ></v-checkbox>
                </v-col>
              </v-row>
            </v-col>
          </v-col>

          <v-divider class="my-4" style="border: 1px black solid"></v-divider>
          <v-col cols="12" class="mb-4">
            <div class="d-flex align-center justify-end">
              <ButtonMuted name="Reset" class="mr-2" @click="resetFilter()" />
              <ButtonSuccess name="Generate Report" type="submit" />
            </div>
          </v-col>
        </v-row>
      </v-form>
    </v-card-text>
  </v-card>

  <!-- <pre>{{ $page.props.auth.roles[0] == 'superadmin'  }}</pre> -->
</template>
<script>
import SidebarLayout from "@/layouts/SidebarLayout.vue";
import PageOnBuild from "@/components/Errors/PageOnBuild.vue";
import EmployeeTabs from "@/components/EmployeeTabs.vue";
import FilterWrapper from "@/components/FilterWrapper.vue";

import { useForm } from "@inertiajs/vue3";
import useVuelidate from "@vuelidate/core";
import { required } from "@vuelidate/validators";
import ButtonMuted from "@/components/ButtonMuted.vue";
import ButtonSuccess from "@/components/ButtonSuccess.vue";
import { debounce } from "lodash";
import { employeeType } from "@/utils/EmployeeType";

export default {
  layout: SidebarLayout,
  components: {
    PageOnBuild,
    EmployeeTabs,
    FilterWrapper,
    ButtonMuted,
    ButtonSuccess,
  },
  props: {
    operatingUnits: {
      type: Array,
      default: () => [],
    },

    departments: {
      type: Array,
      default: () => [],
    },

    jobStatus: Object,
  },
  data() {
    return {
      employeeType,

      authUser:
        this.$page.props.auth.roles[0] === "superadmin" ||
        this.$page.props.auth.roles[0] === "hr_director"
          ? true
          : false,

      authRole: this.$page.props.auth.roles[0],

      pageIsOnBuild: false,
      activeTab: "reports",
      isPanelOpen: [0],
      personalInformation: [
        { title: "Last name", value: "lastname" },
        { title: "first name", value: "firstname" },
        { title: "middle name", value: "middlename" },
        { title: "suffix", value: "suffix" },
        { title: "date of birth", value: "date_of_birth" },
        { title: "place of birth", value: "place_of_birth" },
        { title: "sex", value: "sex" },
        { title: "civil status", value: "civil_status" },
        { title: "telephone number", value: "telephone_no" },
        { title: "mobile number", value: "mobile_no" },
        { title: "email", value: "email" },
        { title: "gsis id no", value: "gsis_id_no" },
        { title: "pag ibig no", value: "pag_ibig_id_no" },
        { title: "philhealth id no", value: "philhealth_id_no" },
        { title: "sss id no", value: "sss_id_no" },
        { title: "tin no", value: "tin_id_no" },
      ],

      // familyBackground: [
      //   { title: "spouse last name", value: "spouse_lastname" },
      //   { title: "spouse first name", value: "spouse_firstname" },
      //   { title: "spouse middle name", value: "spouse_middlename" },
      //   { title: "spouse name extension", value: "spouse_suffix" },
      //   { title: "spouse occupation", value: "occupation" },
      //   { title: "fathers last name", value: "father_lastname" },
      //   { title: "fathers first name", value: "father_firstname" },
      //   { title: "fathers middle name", value: "father_middlename" },
      //   { title: "fathers name extension", value: "fathers_suffix" },
      //   { title: "mothers maiden last name", value: "mother_lastname"},
      //   { title: "mothers first name", value: "mother_firstname" },
      //   { title: "mothers maiden middle name", value: "mothers_maiden_middle_name"},
      // ],

      jobDetails: [
        { title: "Employee number", value: "employee_number" },
        { title: "operating unit", value: "operating_unit" },
        { title: "department", value: "department" },
        { title: "detailed", value: "detailed_at" },
        { title: "position title", value: "position_title" },
        { title: "plantilla item number", value: "plantilla_item_number" },
        { title: "parenthetical title", value: "parenthetical_title" },
        { title: "salary grade", value: "salary_grade" },
        { title: "salary step", value: "salary_step" },
        { title: "salary amount", value: "amount" },
        { title: "custom hourly rate", value: "custom_hourly_rate" },
        { title: "custom daily rate", value: "custom_daily_rate" },
        { title: "immediate supervisor", value: "immediate_supervisor" },
        { title: "next higher supervisor", value: "higher_supervisor" },
      ],

      form: useForm({
        operating_unit_id: this.$page.props.auth.roles[0] === "superadmin" || this.$page.props.auth.roles[0] === "hr_director"
         ? null : this.operatingUnits[0].id,
        // operating_unit_id: null,
        department_id: null,
        job_status: null,
        personal_information: [],
        family_background: [],
        job_details: [],
      }),

      selectedPersonalInformation: [],
      selectedFamilyBackground: [],
      selectedJobDetails: [],
    };
  },

  computed: {
    filteredDepartments() {
      if (this.form.operating_unit_id) {
        // alert(this.form.operating_unit_id);
        return this.departments.filter(
          (dept) => dept.operating_unit_id === this.form.operating_unit_id
        );
      }

      //   console.log(this.departments);
    },
  },

  methods: {
    resetFilter() {
      this.form.operating_unit_id = null;
      this.form.department_id = null;
      this.form.checklist = [];
    },

    generateReport() {
      const queryParams = new URLSearchParams({
        operating_unit_id: this.form.operating_unit_id ?? "",
        department_id: this.form.department_id ?? "",
        job_status_id: this.form.job_status_id ?? "",
        employee_type: this.form.employee_type ?? "",
      });

      this.selectedPersonalInformation.forEach((item) =>
        queryParams.append("personal_information[]", item)
      );
      this.selectedFamilyBackground.forEach((item) =>
        queryParams.append("family_background[]", item)
      );
      this.selectedJobDetails.forEach((item) =>
        queryParams.append("job_details[]", item)
      );

      // ✅ Open in new tab
      window.open(
        route("hrmanagement.employee.report.generate") +
          "?" +
          queryParams.toString(),
        "_blank"
      );
    },
  },
};
</script>
