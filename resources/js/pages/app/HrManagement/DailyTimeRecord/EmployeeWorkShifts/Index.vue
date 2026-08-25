<template>
  <DailyTimeRecordTabs :activeTab="activeTab" />
  
  <!-- Toggle Switch -->
  <v-card class="mb-4" rounded="lg">
    <v-card-text>
      <div class="d-flex align-center justify-center">
        <span class="text-body-1 me-4">Weekly Shift Templates</span>
        <v-switch
          v-model="viewMode"
          color="primary"
          hide-details
          @change="onViewModeChange"
        ></v-switch>
        <span class="text-body-1 ms-4">Employee Names</span>
      </div>
    </v-card-text>
  </v-card>

  <FilterWrapper v-if="viewMode" v-model="isPanelOpen" class="mb-2">
    <v-form @submit.prevent="handleFilter()">
      <v-row>
        <v-col cols="12" md="4" sm="12">
          <v-text-field
            label="Search"
            variant="outlined"
            density="compact"
            rounded="lg"
            v-model="filterForm.search"
            hide-details
          ></v-text-field>
        </v-col>
         <v-col cols="12" md="4" sm="12">
           <v-select
             label="Operating Unit"
             variant="outlined"
             density="compact"
             rounded="lg"
             v-model="filterForm.operating_unit"
             :items="operatingUnits"
             item-title="name"
             item-value="id"
             :disabled="!isAdmin"
             hide-details
           ></v-select>
         </v-col>
         <v-col cols="12" md="4" sm="12">
           <v-autocomplete
             label="Department"
             variant="outlined"
             density="compact"
             rounded="lg"
             v-model="filterForm.department"
             :items="departments"
             item-title="name"
             item-value="id"
             hide-details
           ></v-autocomplete>
         </v-col>
        <v-col cols="12" md="4" sm="12">
          <v-select
            label="Employee Status"
            variant="outlined"
            density="compact"
            rounded="lg"
            v-model="filterForm.employee_status"
            :items="employeeStatus"
            item-title="name"
            item-value="id"
            hide-details
          ></v-select>
        </v-col>
        <v-col cols="12" md="4" sm="12">
          <v-select
            label="Employee Type"
            variant="outlined"
            density="compact"
            rounded="lg"
            v-model="filterForm.employee_type"
            :items="filterOptions.employeeType"
            hide-details
          ></v-select>
        </v-col>
        
        <v-col cols="12" md="2" sm="12">
          <v-select
            label="Direction"
            variant="outlined"
            density="compact"
            rounded="lg"
            :items="filterOptions.direction"
            v-model="filterForm.direction"
          ></v-select>
        </v-col>
        <v-col cols="12" md="2" sm="12">
          <v-select
            label="Size"
            variant="outlined"
            density="compact"
            rounded="lg"
            :items="filterOptions.size"
            v-model="filterForm.size"
          ></v-select>
        </v-col>
      </v-row>
      <v-divider
        class="my-4"
        style="border:1px solid black;"
      ></v-divider>
      <div class="d-flex align-center justify-end">
        <ButtonMuted name="Reset" @click="resetFilter()" />
        <ButtonSuccess name="Search" type="submit" class="ml-2" />
      </div>
    </v-form>
  </FilterWrapper>
  <!-- <pre>{{ employees }}</pre> -->
  <!-- Weekly Shift Templates View -->
  <TableWrapper v-if="!viewMode">
    <v-table>
      <thead>
        <tr>
          <th>Schedule Name</th>
          <th>Description</th>
          <th class="text-center">Actions</th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="(weeklyShiftTemplate, index) in weeklyShiftTemplates.data" :key="index">
          <td>{{ weeklyShiftTemplate.name }}</td>
          <td>{{ weeklyShiftTemplate.description }}</td>
          <td class="text-center">
            <Link :href="weeklyShiftTemplate.manage_link">
              <v-tooltip text="Manage Employee Work Shifts" location="top">
                <template v-slot:activator="{ props }">
                  <v-btn
                    v-bind="props"
                    color="blue"
                    variant="tonal"
                    size="x-small"
                    icon="mdi-account-multiple"
                    class="ml-2"
                  />
                </template>
              </v-tooltip>
            </Link>
          </td>
        </tr>
      </tbody>
    </v-table>
    <Pagination
      class="mt-2"
      :meta="weeklyShiftTemplates.meta"
    />
  </TableWrapper>

  <!-- Employee Names View -->
  <TableWrapper v-else>
    <v-table>
      <thead>
        <tr>
          <th>Employee Name</th>
          <th>Employee Number</th>
          <th
            v-if="isAdmin"
          >Operating Unit</th>
          <th>Department</th>
          <th>Current Work Shift</th>
          <th class="text-center">Actions</th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="(employee, index) in employees.data" :key="index">
          <td>{{ employee.personal_information.full_name_desc }}</td>
          <td>{{ employee.employee_number }}</td>
          <td v-if="isAdmin">{{ employee.operating_unit?.name }}</td>
          <td>{{ employee.department?.name || 'N/A' }}</td>
          <td>{{ employee.weekly_shift_template?.name || 'Not Assigned' }}</td>
          <td class="text-center">
            <v-btn
              color="primary"
              variant="tonal"
              size="x-small"
              icon="mdi-cog"
              @click="assignWorkShift(employee)"
              class="ml-2"
            >
            </v-btn>
          </td>
        </tr>
      </tbody>
    </v-table>
    <Pagination
      class="mt-2"
      :meta="employees.meta"
    />
  </TableWrapper>


  <!-- Work Shift Assignment Dialog -->
  <v-dialog v-model="assignmentDialog" max-width="600px" persistent>
    <v-card>
        <v-card-title class="text-h6 font-weight-medium">Assign Work Shift</v-card-title>
      
      <v-card-text>
        <div class="mb-4">
          <strong>Employee:</strong> {{ selectedEmployee?.personal_information?.full_name_desc }}
        </div>


        <v-skeleton-loader
          v-if="!weeklyShiftTemplates"
          type="table"
          class="mx-auto mt-8"
        >
        </v-skeleton-loader>

        <v-table
          v-else
        >
          <thead>
            <tr>
              <th></th>
              <th>Work Shift</th>
              <th>Description</th>
            </tr>
          </thead>
           <tbody>
             <tr v-for="(workShift, index) in weeklyShiftTemplates.data" :key="index">
               <td>
                 <v-radio-group
                   v-model="selectedWorkShift"
                   hide-details
                 >
                   <v-radio
                     :value="workShift.id"
                     density="compact"
                   ></v-radio>
                 </v-radio-group>
               </td>
               <td>{{ workShift.name }}</td>
               <td>{{ workShift.description }}</td>
             </tr>
           </tbody>
        </v-table>
      </v-card-text>
      <v-card-actions>
        <v-spacer></v-spacer>
        <v-btn min-width="120" color="grey" variant="text" @click="assignmentDialog = false">Cancel</v-btn>
        <v-btn min-width="120" color="primary" @click="saveWorkShiftAssignment">Assign</v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>
</template>
<script>
  import SidebarLayout from "@/layouts/SidebarLayout.vue";
  import DailyTimeRecordTabs from "@/components/DailyTimeRecordTabs.vue";
  import TableWrapper from "@/components/TableWrapper.vue";
  import ButtonSuccess from "@/components/ButtonSuccess.vue";
  import ButtonMuted from "@/components/ButtonMuted.vue";
  import Pagination from "@/components/Pagination.vue";
  import FilterWrapper from '@/components/FilterWrapper.vue';
  import { useForm } from '@inertiajs/vue3';
  import { employeeType } from '@/utils/EmployeeType';
  import { defaultSizes, defaultDirections } from '@/utils/filters';
  
  export default {
    layout: SidebarLayout,
    components: {
      DailyTimeRecordTabs,
      TableWrapper,
      ButtonSuccess,
      ButtonMuted,
      Pagination,
      FilterWrapper,
    },
      props: {
        weeklyShiftTemplates: Object,
        employees: Array,
        employeeStatus: Object,
        operatingUnits: Object,
        departments: Object,
        userOperatingUnit: Number,
        isAdmin: Boolean,
      },
    data(){
      return {
        activeTab: "shifts",
        isPanelOpen: [0],
        viewMode: false, // false = weekly shift templates, true = employee names
        assignmentDialog: false,
        selectedEmployee: null,
        selectedWorkShift: null,
        filterOptions: {
          size: defaultSizes,
          direction: defaultDirections,
          employeeType: employeeType,
        },
        filterForm: useForm({
          search: null,
          operating_unit: null,
          department: null,
          employee_status: null,
          employee_type: null,
          size: null,
          direction: 'Ascending',
        }),
      }
    },
    mounted() {
      this.initializeForm();
    },
    methods: {
       initializeForm() {
         // Set default operating unit for non-admin users
         if (!this.isAdmin && this.userOperatingUnit) {
           this.filterForm.operating_unit = this.userOperatingUnit;
         }
       },
       
       handleFilter() {
         this.filterForm.post(route('hrmanagement.dailytimerecord.employeeWorkShifts.index'), {
           preserveState: true,
           preserveScroll: true,
           only: ['employees'],
         });
       },

       resetFilter() {
         this.filterForm.search = null;
         this.filterForm.operating_unit = this.isAdmin ? null : this.userOperatingUnit;
         this.filterForm.department = null;
         this.filterForm.employee_status = null;
         this.filterForm.employee_type = null;
         this.filterForm.size = 10;
         this.filterForm.direction = 'Ascending';
         this.handleFilter();
       },
      
      onViewModeChange() {
        // You can add any logic here when the view mode changes
        console.log('View mode changed to:', this.viewMode ? 'Employee Names' : 'Weekly Shift Templates');
      },
      assignWorkShift(employee) {
        this.selectedEmployee = employee;
        this.selectedWorkShift = employee.weekly_shift_template?.id || null;
        this.assignmentDialog = true;
      },
      closeAssignmentDialog() {
        this.assignmentDialog = false;
        this.selectedEmployee = null;
        this.selectedWorkShift = null;
      },

      saveWorkShiftAssignment() {
        console.log(this.selectedEmployee, this.selectedWorkShift);

        if (this.selectedEmployee && this.selectedWorkShift) {
          this.$inertia.post(route('hrmanagement.dailytimerecord.employeeWorkShifts.assignPerEmployee'), {
            employee_id: this.selectedEmployee.id,
            work_shift_id: this.selectedWorkShift
          },{
            preserveState: true,
            preserveScroll: true,
            onSuccess: () => {
              this.closeAssignmentDialog();
              this.showToast('Work shift assigned successfully', 'success');
            },
            onError: (errors) => {
              const errorMessages = Object.values(errors).flat().join(" ");
              this.showToast(`${errorMessages}`, 'error');
              this.closeAssignmentDialog();
            }
          });
        }
      }
    }
  }
</script>