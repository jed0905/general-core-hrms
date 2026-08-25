<template>
  <LeaveManagementTabs v-model:activeTab="activeTab"  />
  <FilterWrapper v-model="isPanelOpen">
    <v-form @submit.prevent="handleFilter()">
      <v-row>
        <v-col cols="12" md="3">
          <v-text-field
            label="Search"
            variant="outlined"
            density="compact"
            rounded="lg"
            v-model="filterForm.search"
          ></v-text-field>
        </v-col>
        <v-col cols="12" md="3" >
          <v-select
            label="Employee Status"
            variant="outlined"
            density="compact"
            rounded="lg"
            :items="jobStatuses"
            item-title="name"
            item-value="id"
            v-model="filterForm.job_status_id"
          ></v-select>
        </v-col>
        <v-col cols="12" md="3">
          <v-select
            label="Employee Type"
            variant="outlined"
            density="compact"
            rounded="lg"
            :items="optionForm.employeeType"
            v-model="filterForm.employee_type"
          ></v-select>
        </v-col>
        <v-col cols="12" md="3">
          <v-select
            label="Leave Type"
            variant="outlined"
            density="compact"
            rounded="lg"
            :items="leaves"
            item-title="name"
            item-value="id"
            v-model="filterForm.leave_type"
          ></v-select>
        </v-col>
        <v-col  cols="12" md="3" sm="12">
          <v-select
            label="Operating Unit"
            variant="outlined"
            density="compact"
            rounded="lg"
            :items="operatingUnits"
            item-title="name"
            item-value="id"
            v-model="filterForm.operating_unit"
            :disabled="!isPrivileged"
          ></v-select>
        </v-col>
        <v-col cols="12" md="2">
          <v-select
            label="Direction"
            variant="outlined"
            density="compact"
            rounded="lg"
            :items="optionForm.defaultDirections"
            item-title="title"
            item-value="value"
            v-model="this.filterForm.direction"
          ></v-select>
        </v-col>
        <v-col cols="12" md="1">
          <v-select
            label="Size"
            variant="outlined"
            density="compact"
            rounded="lg"
            :items="optionForm.defaultSizes"
            item-title="title"
            item-value="value"
            v-model="this.filterForm.size"
          ></v-select>
        </v-col>
        
      </v-row>
      <v-divider
        class="my-4"
        style="border: 1px solid black;"
      ></v-divider>
      <div class="d-flex align-center justify-end">
        <v-btn
          color="grey-lighten-1"
          variant="outlined"
          rounded="xl"
          prepend-icon="mdi-refresh"
          min-width="120"
          @click="resetFilter()"
        >Reset</v-btn>
        <v-btn
          color="starbucks-green"
          rounded="xl"
          min-width="120"
          class="ml-4"
          prepend-icon="mdi-magnify"
          type="submit"
        >Search</v-btn>
        

      </div>
    </v-form>
  </FilterWrapper>
  <TableWrapper>
    <div class="d-flex justify-space-between align-center">
      <p class="text-h6 font-weight-bold">Leave Scheduler</p>
      <div class="d-flex align-center gap-2">
        <v-btn
          v-if="selectedItems.length > 0"
          color="red-darken-1"
          variant="tonal"
          prepend-icon="mdi-delete"
          @click="deleteAllConfirmationDialog = true"
          rounded="xl"
          min-width="120"
        >
          Delete All Selected ({{ selectedItems.length }})
        </v-btn>
        <Link :href="route('hrmanagement.leave.scheduler.manage')">
          <ButtonSuccess prepend-icon="mdi-account-group" name="Manage Employee Leave Scheduler" />
        </Link>
      </div>
    </div>
    <v-divider
      class="my-4"
      style="border: 1px solid black;"
    ></v-divider>
    <v-table>
      <thead>
        <tr>
          <th>
            <v-checkbox
              density="compact"
              hide-details
              :model-value="isAllSelected"
              :indeterminate="isIndeterminate"
              @click="toggleSelectAll"
            ></v-checkbox>
          </th>
          <th>Employee ID</th>
          <th>Employee Name</th>
          <th>Employee Status</th>
          <th>Employee Type</th>
          <th>Leave Type</th>
          <th>Run Day</th>
          <th>Credits to Add</th>
          <th>Action</th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="employeeLeaveScheduler in employeeLeaveSchedulers.data" :key="employeeLeaveScheduler.id">
          <th><v-checkbox
            density="compact"
            hide-details
            :model-value="selectedItems.includes(employeeLeaveScheduler.id)"
            @click="toggleSelectItem(employeeLeaveScheduler.id)"
          ></v-checkbox></th>
          <td>{{ employeeLeaveScheduler.employee?.employee_number }}</td>
          <td>{{ employeeLeaveScheduler.employee?.personal_information.full_name_desc }}</td>
          <td>{{ employeeLeaveScheduler.employee?.job_status.name }}</td>
          <td>{{ employeeLeaveScheduler.employee?.employee_type }}</td>
          <td>{{ employeeLeaveScheduler.leave?.name }}</td>
          <td>{{ employeeLeaveScheduler.run_day }}{{ employeeLeaveScheduler.run_day % 10 === 1 && employeeLeaveScheduler.run_day !== 11 ? 'st' : employeeLeaveScheduler.run_day % 10 === 2 && employeeLeaveScheduler.run_day !== 12 ? 'nd' : employeeLeaveScheduler.run_day % 10 === 3 && employeeLeaveScheduler.run_day !== 13 ? 'rd' : 'th' }} day of the month</td>
          <td>{{ employeeLeaveScheduler.credits_to_add }}</td>
          <td>
            <v-btn
              icon="mdi-trash-can"
              size="x-small"
              color="red-darken-1"
              variant="tonal"
              @click="deleteConfirmationDialog = true; id = employeeLeaveScheduler.id"
            ></v-btn>
            <Link
              :href="employeeLeaveScheduler.edit_link"
              class="text-decoration-none"
            >
              <v-btn 
                icon="mdi-pencil" 
                size="x-small" 
                variant="tonal" 
                color="green-darken-1"
                class="ml-2"
              ></v-btn>
            </Link>
          </td>
        </tr>
      </tbody>
    </v-table>
    <Pagination
      class="mt-3" 
      :meta="employeeLeaveSchedulers.meta"
      :custom-click-handler="true"
      @page-click="handlePageClick"
    />
  </TableWrapper>

  <DeleteDialog
    v-model="deleteConfirmationDialog"
    message="Are you sure you want to delete this position?"
    :loading="loading"
    @confirm="handleDelete()"
    @cancel="deleteConfirmationDialog = false"
  />
  <DeleteDialog
    v-model="deleteAllConfirmationDialog"
    message="Are you sure you want to delete all selected leave schedulers?"
    :loading="deleteAllLoading"
    @confirm="handleDeleteAll()"
    @cancel="deleteAllConfirmationDialog = false"
  />
</template>
<script>
  import SidebarLayout from '@/layouts/SidebarLayout.vue';
  import LeaveManagementTabs from '@/components/LeaveManagementTabs.vue';
  import TableWrapper from '@/components/TableWrapper.vue';
  import ButtonSuccess from '@/components/ButtonSuccess.vue';
  import FilterWrapper from '@/components/FilterWrapper.vue';
  import { employeeType } from '@/utils/EmployeeType';
  import { defaultSizes, defaultDirections } from '@/utils/filters'
  import Pagination from '@/components/Pagination.vue';
  import { useForm, Link } from '@inertiajs/vue3';
  import DeleteDialog from '@/components/DeleteDialog.vue';

  export default {
    layout: SidebarLayout,
    components: {
      LeaveManagementTabs,
      TableWrapper,
      ButtonSuccess,
      FilterWrapper,
      Pagination,
      DeleteDialog,
    },
    props: {
      errors: Object,
      jobStatuses: Object,
      leaves: Object,
      employeeLeaveSchedulers: Object,
      operatingUnits: Object,
    },
    data(){
      return {
        activeTab: 'configure',
        isPanelOpen: [0],
        optionForm:{
          employeeType: employeeType,
          defaultSizes: defaultSizes,
          defaultDirections: defaultDirections,
        },
        isPrivileged: this.$page.props.auth.roles[0] == 'superadmin' || this.$page.props.auth.roles[0] == 'hr_director',
       
        filterForm: useForm({
          search: null,
          job_status_id: null,
          employee_type: null,
          leave_type: null,
          page: 1,
          direction: 'Ascending' ?? null,
          size: null,
          operating_unit: null,
        }),
        
        deleteConfirmationDialog: false,
        deleteAllConfirmationDialog: false,
        loading: false,
        deleteAllLoading: false,
        selectedItems: [],
      }
    },
    computed: {
      isAllSelected() {
        return this.employeeLeaveSchedulers.data.length > 0 && 
               this.selectedItems.length === this.employeeLeaveSchedulers.data.length;
      },
      isIndeterminate() {
        return this.selectedItems.length > 0 && 
               this.selectedItems.length < this.employeeLeaveSchedulers.data.length;
      }
    },
    methods: {
      handleFilter(){
        this.selectedItems = []; // Clear selections when filtering
        this.filterForm.post(route('hrmanagement.leave.scheduler.index'), {
          preserveState: true,
          preserveScroll: true,
          only: ['employeeLeaveSchedulers'],
        });
      },
      resetFilter(){
        // Clear selected items first
        this.selectedItems = [];
        
        // Reset form fields
        this.filterForm.search = null;
        this.filterForm.job_status_id = null;
        this.filterForm.employee_type = null;
        this.filterForm.leave_type = null;
        this.filterForm.operating_unit = null;
        this.filterForm.page = 1; // Reset to first page
      
        // Ensure default values are set after reset
        this.filterForm.size = 10;
        this.filterForm.direction = 'Ascending';
        
       
        this.handleFilter();
      },
      handlePageClick(url) {
        // Extract page parameter from URL.
        const urlObj = new URL(url);
        const page = urlObj.searchParams.get('page');
        
        // Clear selections when changing pages
        this.selectedItems = [];
        
        // Create form data with current filters and new page
        const formData = {
          ...this.filterForm.data(),
          page: page
        };
        
        // Make POST request with preserved filters
        this.filterForm.transform(() => formData).post(route("hrmanagement.leave.scheduler.index"), {
          preserveState: true,
          preserveScroll: true,
          only: ['employeeLeaveSchedulers'],
        });
      },
      handleDelete(){
        this.loading = true;
        this.$inertia.delete(route("hrmanagement.leave.scheduler.destroy", {
          id: this.id
        }), {
          preserveScroll: true,
          preserveState: true,
          onSuccess: () => {
            this.showToast('Leave scheduler deleted successfully', 'success');
            this.loading = false;
            this.deleteConfirmationDialog = false;
          },
          onError: (errors) => {
            const errorMessages = Object.values(errors).flat().join(" ");
            this.showToast(`${errorMessages}`, "error");
            this.loading = false;
            this.deleteConfirmationDialog = false;
          },
        });
      },
      toggleSelectAll(){
        if (this.isAllSelected) {
          this.selectedItems = [];
        } else {
          this.selectedItems = this.employeeLeaveSchedulers.data.map(item => item.id);
        }
      },
      toggleSelectItem(id) {
        const index = this.selectedItems.indexOf(id);
        if (index > -1) {
          this.selectedItems.splice(index, 1);
        } else {
          this.selectedItems.push(id);
        }
      },
      handleDeleteAll() {
        this.deleteAllLoading = true;
        this.$inertia.post(route("hrmanagement.leave.scheduler.destroyAll"), {
          ids: this.selectedItems
        }, {
          preserveScroll: true,
          preserveState: true,
          onSuccess: () => {
            this.showToast('Selected leave schedulers deleted successfully', 'success');
            this.deleteAllConfirmationDialog = false;
            this.selectedItems = [];
            this.deleteAllLoading = false;
          },
          onError: (errors) => {
            const errorMessages = Object.values(errors).flat().join(" ");
            this.showToast(`${errorMessages}`, "error");
            this.deleteAllConfirmationDialog = false;
            this.deleteAllLoading = false;
          },
        });
      }
    }
  }
</script>
