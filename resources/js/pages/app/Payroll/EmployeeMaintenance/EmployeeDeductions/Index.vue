<template>
  <PayrollEmployeeMaintenanceTabs :activeTab="activeTab" @update:activeTab="activeTab = $event" />
  <FilterWrapper v-model="isPanelOpen" >
    <v-form>
      <v-row>
        <v-col cols="12" md="4">
          <v-select
            label="Job Status"
            variant="outlined"
            density="compact"
            rounded="lg"
            :items="jobStatus"
            item-title="name"
            item-value="id"
            v-model="filterForm.job_status"
            @update:modelValue="onFilterChange"
          ></v-select>
        </v-col>
        <v-col cols="12" md="4">
          <v-select
            label="Employee Type"
            variant="outlined"
            density="compact"
            rounded="lg"
            :items="employeeType"
            v-model="filterForm.employee_type"
            @update:modelValue="onFilterChange"
          ></v-select>
        </v-col>
        <v-col cols="12" md="4">
          <v-select
            label="Items per page"
            variant="outlined"
            density="compact"
            rounded="lg"
            :items="[5, 10, 25, 50, 100]"
            v-model="filterForm.per_page"
            @update:modelValue="onFilterChange"
          ></v-select>
        </v-col>
      </v-row>
      <v-row>
        <v-col cols="12">
          <v-text-field
            label="Search Employee"
            variant="outlined"
            density="compact"
            rounded="lg"
            v-model="searchQuery"
            prepend-inner-icon="mdi-magnify"
            clearable
            placeholder="Search by name or employee number..."
          ></v-text-field>
        </v-col>
      </v-row>
    </v-form>
  </FilterWrapper>
  <TableWrapper>
    <v-table>
      <thead>
        <tr>
          <th>Employee Number</th>
          <th>Employee Name</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <tr v-if="!employees || !employees.data || employees.data.length === 0">
          <td colspan="5" class="text-center pa-4">
            <p class="text-grey">No employees found. Please select filters to search.</p>
          </td>
        </tr>
        <tr v-else-if="filteredEmployees.length === 0">
          <td colspan="5" class="text-center pa-4">
            <p class="text-grey">No employees match your search criteria.</p>
          </td>
        </tr>
        <tr v-for="employee in filteredEmployees" :key="employee.id">
          <td>{{ employee.employee_number }}</td>
          <td>{{ employee.personal_information.full_name_desc }}</td>
          <td>
            <Link :href="route('payroll.employee.maintenance.deductions.view', {id:employee.id})">
              <v-btn
                size="x-small"
                variant="tonal"
                color="primary"
                icon="mdi-eye"
              >
              </v-btn>
            </Link>
          </td>
        </tr>
      </tbody>
    </v-table>
    <Pagination 
      v-if="employees && employees.meta" 
      :meta="employees.meta" 
      :partials="['employees']"
      :customClickHandler="true"
      @page-click="goToPage"
    />
  </TableWrapper>
  <!-- <pre>{{ employees }}</pre> -->
</template>
<script>
  import Layout from '@/layouts/SidebarLayout.vue'
  import PayrollEmployeeMaintenanceTabs from '@/components/Payroll/PayrollEmployeeMaintenanceTabs.vue'
  import FilterWrapper from '@/components/FilterWrapper.vue'
  import TableWrapper from '@/components/TableWrapper.vue'
  import { employeeType } from '@/utils/EmployeeType'
  import { useForm } from '@inertiajs/vue3'
  import Pagination from '@/components/Pagination.vue'
  export default {
    layout: Layout,
    components: {
      PayrollEmployeeMaintenanceTabs,
      FilterWrapper,
      TableWrapper,
      Pagination,
    },
    props: {
      jobStatus: Object,
      employees: Object,
    },
    data(){
      return {
        activeTab: 'deductions',
        employeeType,
        isPanelOpen: [0],
        searchQuery: '',
        filterForm: useForm({
          job_status: null,
          employee_type: employeeType[0],
          per_page: 10,
          page: 1,
        })
      }
    },
    computed: {
      filteredEmployees() {
        if (!this.employees || !this.employees.data) {
          return [];
        }

        if (!this.searchQuery.trim()) {
          return this.employees.data;
        }

        const query = this.searchQuery.toLowerCase().trim();
        return this.employees.data.filter(employee => {
          const employeeNumber = employee.employee_number?.toLowerCase() || '';
          const firstName = employee.personal_information?.firstname?.toLowerCase() || '';
          const lastName = employee.personal_information?.lastname?.toLowerCase() || '';
          const fullName = `${firstName} ${lastName}`.trim();
          
          return employeeNumber.includes(query) || 
                 fullName.includes(query) ||
                 firstName.includes(query) || 
                 lastName.includes(query);
        });
      }
    },
    mounted() {
      // Set the first job status as default when component is mounted
      if (this.jobStatus && this.jobStatus.length > 0) {
        this.filterForm.job_status = this.jobStatus[0].id;
        // Fire the method on load after setting initial values
        this.getEmployeeDeductions();
      }
    },
    methods: {
      getEmployeeDeductions() {
        // Use POST request for security (hides data from URL)
        this.filterForm.post(route('payroll.employee.maintenance.deductions.index'), {
          preserveScroll: true,
          preserveState: true,
          only: ['employees'],
        });
      },
      getJobStatusName(jobStatusId) {
        const jobStatus = this.jobStatus.find(status => status.id === jobStatusId);
        return jobStatus ? jobStatus.name : 'Unknown';
      },
      onFilterChange() {
        // Reset to first page when filters change
        this.filterForm.page = 1;
        this.getEmployeeDeductions();
      },
      goToPage(url) {
        // Extract page number from URL and update form
        const urlObj = new URL(url);
        const page = urlObj.searchParams.get('page') || 1;
        this.filterForm.page = parseInt(page);
        
        // Make POST request with updated page
        this.getEmployeeDeductions();
      }
    }
  }
</script>