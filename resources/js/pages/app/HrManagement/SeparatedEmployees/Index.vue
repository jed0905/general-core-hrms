<template>
  <EmployeeTabs v-model:activeTab="activeTab" />
  <FilterWrapper :model-value="0">
    <v-form @submit.prevent="handleFilter()">
      <v-row>
        <v-col cols="12" md="6">
          <v-text-field
            label="Search"
            variant="outlined"
            density="compact"
            rounded="lg"
            v-model="filterForm.search"
            hide-details
          ></v-text-field>
        </v-col>
        <v-col cols="12" sm="12" md="6">
          <v-autocomplete
            label="Operating Unit"
            variant="outlined"
            density="compact"
            hide-details
            rounded="lg"
            :items="operatingUnits"
            item-title="name"
            item-value="id"
            v-model="filterForm.operating_unit"
            :disabled="disableOperatingUnitInput"
          ></v-autocomplete>
        </v-col>
        <v-col cols="12" sm="12" md="6">
          <v-autocomplete
            label="Department"
            variant="outlined"
            density="compact"
            hide-details
            rounded="lg"
            :items="departments"
            item-title="name"
            item-value="id"
            v-model="filterForm.department"
          ></v-autocomplete>
        </v-col>
        <v-col cols="12" md="6">
          <v-select
            label="Employment Status"
            variant="outlined"
            density="compact"
            rounded="lg"
            hide-details
            :items="jobStatuses"
            item-title="name"
            item-value="id"
            v-model="filterForm.employment_status"
          ></v-select>
        </v-col>

        <v-col cols="12" sm="6" md="6">
          <v-select
            label="Employee Type"
            variant="outlined"
            density="compact"
            :items="employeeType"
            hide-details
            rounded="lg"
            v-model="filterForm.employee_type"
          ></v-select>
        </v-col>
        <v-col cols="12" sm="6" md="3">
          <v-select
            label="Size"
            variant="outlined"
            density="compact"
            hide-details
            rounded="lg"
            :items="filterOptions.size"
            v-model="filterForm.size"
          ></v-select>
        </v-col>
        <v-col cols="12" sm="6" md="3">
          <v-select
            label="Direction"
            variant="outlined"
            density="compact"
            hide-details
            rounded="lg"
            :items="filterOptions.direction"
            v-model="filterForm.direction"
          ></v-select>
        </v-col>

        <v-col cols="12">
          <div class="d-flex align-center justify-end">
            <ButtonMuted name="Reset" class="mr-2" @click="resetFilter()" />
            <ButtonSuccess name="Search" type="submit" />
          </div>
        </v-col>
      </v-row>
    </v-form>
  </FilterWrapper>

  <TableWrapper>
    <v-table>
      <thead>
        <tr>
          <th>ID</th>
          <th>Name</th>
          <th>Department</th>
          <th>Position</th>
          <th>Email</th>
          <th class="text-center">
            <v-icon>mdi-lightning-bolt-outline</v-icon>
          </th>
        </tr>
      </thead>
      <tbody>
        <tr
          v-for="employee in employees.data"
          :key="employee.id"
          :class="{ 'bg-red-lighten-5': hasEndedEmployment(employee) }"
        >
          <td>{{ employee.employee_number }}</td>
          <td>{{ employee.personal_information.full_name_desc }}</td>
          <td>{{ employee.department?.name }}</td>
          <td>{{ employee.position?.name }}</td>
          <td>{{ employee.personal_information.email }}</td>
          <td class="text-center">
            <Link :href="employee.edit_link">
              <v-btn
                icon="mdi-eye-outline"
                size="x-small"
                variant="tonal"
                color="green-darken-1"
              ></v-btn>
            </Link>

          </td>
        </tr>
      </tbody>
    </v-table>
    <Pagination
      class="mt-3"
      :meta="employees.meta"
      :filters="filterForm.data()"
    />
  </TableWrapper>


</template>

<script>
import SidebarLayout from "@/layouts/SidebarLayout.vue";
import PrimaryButton from "@/components/PrimaryButton.vue";
import Breadcrumbs from "@/components/Breadcrumbs.vue";
import TableWrapper from "@/components/TableWrapper.vue";
import FilterWrapper from "@/components/FilterWrapper.vue";
import EmployeeTabs from "@/components/EmployeeTabs.vue";
import ButtonSuccess from "@/components/ButtonSuccess.vue";
import ButtonMuted from "@/components/ButtonMuted.vue";
import Pagination from "@/components/Pagination.vue";
import { useForm } from "@inertiajs/vue3";
import { employeeType } from "@/utils/EmployeeType";
import { defaultSizes, defaultDirections } from "@/utils/filters";


export default {
  layout: SidebarLayout,

  components: {
    EmployeeTabs,
    PrimaryButton,
    Breadcrumbs,
    TableWrapper,
    FilterWrapper,
    ButtonSuccess,
    ButtonMuted,
    Pagination,
  },

  props: {
    employees: Object,
    roles: Object,
    jobStatuses: Object,
    departments: Object,
    operatingUnits: Object,
    filter: Object,
  },

  data() {
    const roles = (this.$page?.props?.auth?.roles) || [];
    const isSuperAdmin = roles.includes('superadmin');
    const isHrDirector = roles.includes('hr_director');
    const isCampusHr = roles.includes('campus_hr');
    const isCampusHrStaff = roles.includes('campus_hr_staff');
    const isPrivileged = isSuperAdmin || isHrDirector;
    const defaultOperatingUnitId = !isPrivileged
      ? (this.$page?.props?.auth?.user?.employee?.operating_unit_id ?? null)
      : null;

    return {
      disableOperatingUnitInput: isCampusHr || isCampusHrStaff,
      isPrivileged,
      initialOperatingUnitId: defaultOperatingUnitId,
      initialSize: this.filter?.size ?? 10,
      isResetting: false,
      activeTab: "separatedEmployee",
      employeeType,
      filterForm: useForm({
        search: null,
        employment_status: null,
        department: null,
        employee_type: null,
        size: this.filter?.size ?? 10,
        direction: 'Ascending',
        operating_unit: defaultOperatingUnitId,
      }),

      filterOptions:{
        size: defaultSizes,
        direction: defaultDirections,
      },
      terminateEmploymentDialog:false,
      terminationForm: useForm({
        terminationDate: null,
        terminationReason: null,
        reason: null,
      }),

      terminationReason: [
        {
          value: "separation",
          label: "Separation",
        },

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
          value: "suspension",
          label: "Suspension",
        },
      ],

    };
  },



  watch:{
    'filterForm.operating_unit': function(newVal, oldVal){
      if(!this.isResetting && newVal !== oldVal){
        // Reset department when operating unit changes
        this.filterForm.department = null;
        this.fetchOperatingUnitDepartments(newVal);
      }
    }
  },

  methods: {
    canManageEmployment() {
      const allowedRoles = [
        "superadmin",
        "hr_director",
        "campus_hr",
        "campus_hr_staff",
      ];
      const roles = this.$page?.props?.auth?.roles || [];
      return roles.some((role) => allowedRoles.includes(role));
    },

    hasEndedEmployment(employee) {
      if (!employee?.employee_movement?.length) {
        return false;
      }

      const endTypes = [
        "separation",
        "termination",
        "resignation",
        "retirement",
        "suspension",
      ];
      const latestMovement =
        employee.employee_movement[employee.employee_movement.length - 1];

      return endTypes.includes(latestMovement?.movement_type);
    },

    handleFilter() {
        this.filterForm.post(route("hrmanagement.separated-employees.index"), {
        preserveState: true,
        preserveScroll: true,
        only: ["employees"]
      });
    },

    resetFilter() {
      this.isResetting = true;
      this.filterForm.search = null;
      this.filterForm.department = null;
      this.filterForm.employment_status = null;
      this.filterForm.employee_type = null;
      this.filterForm.size = this.initialSize;
      this.filterForm.direction = 'Ascending';
      this.filterForm.operating_unit = this.isPrivileged ? null : this.initialOperatingUnitId;
      this.handleFilter();
    },

    handlePageClick(url) {
      // Extract page parameter from URL.
      const urlObj = new URL(url);
      const page = urlObj.searchParams.get('page');

      // Create form data with current filters and new page
      const formData = {
        ...this.filterForm.data(),
        page: page
      };

      // Make POST request with preserved filters
      this.filterForm.transform(() => formData).post(route("hrmanagement.separated-employees.index"), {
        preserveState: true,
        preserveScroll: true,
        only: ["employees"],
      });
    },

    fetchOperatingUnitDepartments(newVal){
      if(newVal){
        this.$inertia.post(route("hrmanagement.separated-employees.index"),
        {
          operating_unit: newVal,
          ...this.filterForm.data()
        },{
          preserveScroll:true,
          preserveState: true,
          only: ["departments"]
        })
      }
    },

    openTerminateEmploymentDialog(employeeId){
      this.terminateEmploymentDialog = true;
      this.terminationForm.employeeId = employeeId;
    },
  },

  mounted(){
    if(!this.isPrivileged && this.filterForm.operating_unit){
      this.fetchOperatingUnitDepartments(this.filterForm.operating_unit);
    }
  }
};
</script>
