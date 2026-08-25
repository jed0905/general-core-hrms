<template>
  <LeaveManagementTabs v-model:activeTab="activeTab" />

  <!-- On filter should show table  -->

  <FilterWrapper :model-value="[0, 1]">
    <v-form @submit.prevent="handleFilter()">
      <v-row>
        <v-col cols="12" md="4">
          <!-- Will search employee name, employee id -->
          <v-text-field
            label="Search"
            variant="outlined"
            density="compact"
            hide-details
            rounded="lg"
            v-model="filterForm.search"
            prepend-inner-icon="mdi-magnify"
          ></v-text-field>
        </v-col>
        <v-col cols="12" md="4">
          <v-select
            label="Employee Status"
            variant="outlined"
            density="compact"
            hide-details
            rounded="lg"
            v-model="filterForm.employment_status"
            :items="employementStatus"
            item-title="name"
            item-value="id"
          ></v-select>
        </v-col>
        <v-col cols="12" md="4">
          <v-select
            label="Employee Type"
            variant="outlined"
            density="compact"
            hide-details
            rounded="lg"
            v-model="filterForm.employee_type"
            :items="employeeType"

          ></v-select>
        </v-col>
        <v-col cols="12" md="4">
          <v-autocomplete
            label="Operating Unit"
            variant="outlined"
            density="compact"
            hide-details
            rounded="lg"
            v-model="filterForm.operating_unit"
            :items="operatingUnits"
            item-title="name"
            item-value="id"
            :disabled="this.$page.props.auth.roles[0] != 'superadmin' && this.$page.props.auth.roles[0] != 'hr_director'"
          ></v-autocomplete>
        </v-col>

        <v-col cols="12" md="4">
          <v-autocomplete
            label="Department"
            variant="outlined"
            density="compact"
            hide-details
            rounded="lg"
            :items="departments"
            item-title="name"
            item-value="id"
          ></v-autocomplete>
        </v-col>

        <v-col cols="12" md="3">
          <v-select
            label="Direction"
            variant="outlined"
            density="compact"
            hide-details
            rounded="lg"
            :items="defaultDirections"
            v-model="filterForm.direction"
          ></v-select>
        </v-col>

        <v-col cols="12" md="1">
          <v-select
            label="Size"
            variant="outlined"
            density="compact"
            hide-details
            rounded="lg"
            :items="defaultSizes"
            v-model="filterForm.size"
          ></v-select>
        </v-col>



        <v-col cols="12">
          <div class="d-flex align-center justify-end">
            <ButtonMuted name="Reset" class="mr-2" @click="resetFilter()" />
            <ButtonSuccess name="Filter" type='submit' />
          </div>
        </v-col>
      </v-row>
    </v-form>
  </FilterWrapper>

  <TableWrapper v-if="employees.data.length > 0">
    <v-table>
      <thead>
        <tr>
          <th>Employee ID</th>
          <th>Employee Name</th>
          <th
            v-if="this.$page.props.auth.roles[0] == 'superadmin' || this.$page.props.auth.roles[0] == 'hr_director'"
          >Operating Unit</th>
          <th>Employee Department</th>
          <th>Employement Status</th>
          <th class="text-center">
            <v-icon>mdi-lightning-bolt-outline</v-icon>
          </th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="employee in employees.data" :key="employee.id">
          <td>{{ employee.employee_number }}</td>
          <td>{{ employee.fullname_desc }}</td>
          <td
            v-if="this.$page.props.auth.roles[0] == 'superadmin'
            || this.$page.props.auth.roles[0] == 'hr_director'">
            {{ employee.operating_unit?.name }}
          </td>
          <td>{{ employee.department?.name }}</td>
          <td>{{ employee.job_status?.name }}</td>
          <td class="text-center">
            <Link :href="route('hrmanagement.leave.viewEmployeeLeaveEntitlements', { id: employee.id })">
              <v-btn
                icon="mdi-eye-outline"
                size="x-small"
                color="green-darken-1"
                variant="tonal"
              ></v-btn>
            </Link>
          </td>
        </tr>
      </tbody>
    </v-table>
    <Pagination
      class="mt-3" :meta="employees.meta"
      :partials="['employees']"
      :custom-click-handler="true"
      @page-click="handlePageClick"
    />
  </TableWrapper>
</template>

<script>
import SidebarLayout from "@/layouts/SidebarLayout.vue";
import PrimaryButton from "@/components/PrimaryButton.vue";
import Breadcrumbs from "@/components/Breadcrumbs.vue";
import TableWrapper from "@/components/TableWrapper.vue";
import FilterWrapper from "@/components/FilterWrapper.vue";
// import LeavesTabs from '@/components/LeavesTabs.vue';
import ButtonSuccess from '@/components/ButtonSuccess.vue';
import ButtonMuted from '@/components/ButtonMuted.vue';
import Pagination from '@/components/Pagination.vue';
import LeaveManagementTabs from '@/components/LeaveManagementTabs.vue';
import { useForm } from '@inertiajs/vue3';
import { defaultSizes, defaultDirections } from '@/utils/filters';
import { employeeType } from '@/utils/EmployeeType';

export default {
  layout: SidebarLayout,

  components: {
    PrimaryButton,
    Breadcrumbs,
    TableWrapper,
    FilterWrapper,
    LeaveManagementTabs,
    ButtonSuccess,
    ButtonMuted,
    Pagination,
  },

  props: {
    employees: Object,
    employementStatus: Object,
    operatingUnits: Object,
  },

  data() {
    return {
      // Define any local state here if needed
      activeTab: 'entitlements',
      defaultSizes,
      employeeType,
      defaultDirections,
      filterForm: useForm({
        search: null,
        employment_status: null,
        employee_type: null,
        operating_unit: null,
        department: null,
        size: null,
        direction: 'Ascending',
      })
    };
  },

  watch: {
    'filterForm.operating_unit': function(newVal, oldVal){
      if(!this.isResetting && newVal !== oldVal){
        // Reset department when operating unit changes
        this.filterForm.department = null;
        this.fetchOperatingUnitDepartments(newVal);
      }
    },


  },

  methods: {

    fetchOperatingUnitDepartments(newVal){
      if(newVal){
        this.$inertia.post(route('hrmanagement.leave.index'), {
          operating_unit: newVal,
        },{
        },{
          preserveScroll:true,
          preserveState: true,
          only: ["departments"]
        })
      }
    },

    handleFilter() {
      this.filterForm.post(route('hrmanagement.leave.index'), {
        preserveState: true,
        preserveScroll: true,
        only: ['employees'],
      });
    },

    resetFilter() {
      console.log('Reset Filter');
      this.filterForm.search = null;
      this.filterForm.employment_status = null;
      this.filterForm.operating_unit = null;
      this.filterForm.size = 10;
      this.filterForm.employee_type = null;
      this.filterForm.direction = 'Ascending';
      this.handleFilter();
    },

    handlePageClick(url) {
      // Extract page parameter from URL
      const urlObj = new URL(url);
      const page = urlObj.searchParams.get('page');

      // Create form data with current filters and new page
      const formData = {
        ...this.filterForm.data(),
        page: page
      };

      // Make POST request with preserved filters
      this.filterForm.transform(() => formData).post(route('hrmanagement.leave.index'), {
        preserveState: true,
        preserveScroll: true,
        only: ['employees'],
      });
    }
  },
};
</script>

