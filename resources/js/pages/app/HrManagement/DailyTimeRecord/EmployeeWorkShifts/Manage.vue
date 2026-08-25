<template>
  <DailyTimeRecordTabs :activeTab="activeTab" />
  
  <!-- Weekly Shift Template Selection -->
  <v-card class="mb-2" rounded="lg">
    <v-card-text>
      <div class="v-card-title text-h6 font-weight-medium">
        Weekly Shift Template
      </div>
      <v-divider class="my-2" 
        style="border:1px solid black;"
      ></v-divider>
      
      <!-- Template Information -->
      <v-row class="mb-4">
        <v-col cols="12" md="6">
          <div class="d-flex align-center mb-2">
            <v-icon icon="mdi-calendar" class="mr-2" color="starbucks-green"></v-icon>
            <span class="font-weight-medium">Template Name:</span>
            <span class="ml-2">{{ weeklyShiftTemplate.name }}</span>
          </div>
          <div class="d-flex align-center mb-2">
            <v-icon icon="mdi-text" class="mr-2" color="starbucks-green"></v-icon>
            <span class="font-weight-medium">Description:</span>
            <span class="ml-2">{{ weeklyShiftTemplate.description || 'No description provided' }}</span>
          </div>
        </v-col>
        <v-col cols="12" md="6">
          <div class="d-flex align-center mb-2">
            <v-icon icon="mdi-clock-outline" class="mr-2" color="starbucks-green"></v-icon>
            <span class="font-weight-medium">Total Days:</span>
            <span class="ml-2">{{ weeklyShiftTemplate.days?.length || 0 }} days</span>
          </div>
          <div class="d-flex align-center mb-2">
            <v-icon icon="mdi-calendar-check" class="mr-2" color="starbucks-green"></v-icon>
            <span class="font-weight-medium">Status:</span>
            <v-chip 
              :color="weeklyShiftTemplate.days?.length > 0 ? 'success' : 'warning'" 
              size="small" 
              class="ml-2"
            >
              {{ weeklyShiftTemplate.days?.length > 0 ? 'Active' : 'Incomplete' }}
            </v-chip>
          </div>
        </v-col>
      </v-row>

      <!-- Weekly Schedule -->
      <div v-if="weeklyShiftTemplate.days && weeklyShiftTemplate.days.length > 0">
        <v-divider class="my-3"></v-divider>
        <div class="d-flex align-center mb-3">
          <v-icon icon="mdi-calendar-week" class="mr-2" color="starbucks-green"></v-icon>
          <span class="text-h6 font-weight-medium text-starbucks-green">Weekly Schedule</span>
        </div>
        
        <v-row
          class="d-flex align-center justify-center"
        >
          <v-col 
            v-for="day in sortedDays" 
            :key="day.id" 
            cols="12" 
            sm="4" 
            md="2" 
            lg="2"
            xl="2"
            class="mb-2"
          >
            <v-card 
              variant="outlined" 
              :color="getDayCardColor(day.daily_shift_schedule.day_of_week)"
              class="pa-4"
              height="100%"
            >
              <div class="text-center">
                <!-- Day of Week -->
                <div class="text-subtitle-1 font-weight-bold mb-3" :style="{ color: getDayTextColor(day.daily_shift_schedule.day_of_week) }">
                  {{ day.daily_shift_schedule.day_of_week }}
                </div>
                
                <!-- Time Information -->
                <div class="mb-2">
                  <div class="d-flex justify-space-between align-center mb-1">
                    <span class="text-body-2 font-weight-medium text-primary">Time In:</span>
                    <span class="text-body-2 font-weight-medium text-primary">{{ formatTime(day.daily_shift_schedule.time_in) }}</span>
                  </div>
                  <div class="d-flex justify-space-between align-center">
                    <span class="text-body-2 font-weight-medium text-primary">Time Out:</span>
                    <span class="text-body-2 font-weight-medium text-primary">{{ formatTime(day.daily_shift_schedule.time_out) }}</span>
                  </div>
                </div>
                
                <!-- Break Information -->
                <v-divider class="my-2"></v-divider>
                <div class="mb-2">
                  <div class="d-flex justify-space-between mb-1">
                    <span class="text-body-2 font-weight-medium text-success">Break:</span>
                  </div>
                  <div class="d-flex justify-space-between align-center mb-1">
                    <span class="text-caption text-success">Start:</span>
                    <span class="text-body-2 font-weight-medium text-success">{{ formatTime(day.daily_shift_schedule.break_start) }}</span>
                  </div>
                  <div class="d-flex justify-space-between align-center">
                    <span class="text-caption text-success">End:</span>
                    <span class="text-body-2 font-weight-medium text-success">{{ formatTime(day.daily_shift_schedule.break_end) }}</span>
                  </div>
                </div>
                
                
              </div>
            </v-card>
          </v-col>
        </v-row>
      </div>
      
      <!-- No Schedule Message -->
      <div v-else class="text-center pa-4">
        <v-icon icon="mdi-calendar-remove" size="48" color="grey" class="mb-2"></v-icon>
        <p class="text-grey">No schedule configured for this template</p>
      </div>
    </v-card-text>
  </v-card>


  <!-- Filter Section -->
  <FilterWrapper v-model="isPanelOpen" class="mb-2">
    <v-form @submit.prevent="handleFilter()">
      <v-row>
        <v-col cols="12" md="3">
          <v-autocomplete
            label="Operating Unit"
            variant="outlined"
            density="compact"
            rounded="lg"
            v-model="filterForm.operating_unit"
            :items="operatingUnitsWithAll"
            item-title="name"
            item-value="id"
            hide-details
            :disabled="!isAdmin"
            @update:model-value="onOperatingUnitChange"
          ></v-autocomplete>
        </v-col>
        <v-col cols="12" md="3">
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
        <v-col cols="12" md="3">
          <v-select
            label="Employee Status"
            variant="outlined"
            density="compact"
            rounded="lg"
            v-model="filterForm.employee_status"
            :items="employeeStatusWithAll"
            item-title="name"
            item-value="id"
            hide-details
          ></v-select>
        </v-col>
        <v-col cols="12" md="3">
          <v-select
            label="Employee Type"
            variant="outlined"
            density="compact"
            rounded="lg"
            v-model="filterForm.employee_type"
            :items="employeeTypeWithAll"
            hide-details
          ></v-select>
        </v-col>
      </v-row>
      <v-divider
        class="my-4"
        style="border:1px solid black;"
      ></v-divider>
      <div class="d-flex align-center justify-end">
        <ButtonMuted name="Reset" @click="resetFilter()"/>
        <ButtonSuccess name="Search" type="submit" class="ml-2" />
      </div>
    </v-form>
  </FilterWrapper>

  <!-- Employee Management Tables -->
  <v-row>
    <v-col cols="12" md="5">
      <TableWrapper>
        <div class="d-flex justify-space-between align-center mb-2">
          <div class="d-flex align-center gap-2">
            <v-checkbox density="compact" hide-details v-model="selectAllLeft" @change="toggleSelectAllLeft"></v-checkbox>
            <span class="text-body-2">Select all</span>
          </div>
          <v-text-field
            v-model="leftFilter"
            density="compact"
            variant="outlined"
            hide-details
            rounded="lg"
            placeholder="Filter employees..."
            style="max-width: 240px;"
          />
        </div>
        <div class="v-card-title text-h6 font-weight-medium">
          Employees Without Work Shift
        </div>
        <v-table fixed-header height="400">
          <thead>
            <tr>
              <th></th>
              <th>Employee ID</th>
              <th>Employee Name</th>
              <th v-if="this.$page.props.auth.roles[0] == 'superadmin' || this.$page.props.auth.roles[0] == 'hr_director'">Operating Unit</th>
            </tr>
          </thead>
            <tbody>
              <tr v-for="employee in filteredLeftEmployees" :key="employee.id">
                <td><v-checkbox v-model="form.employees_to_add" :value="employee.id"></v-checkbox></td>
                <td>{{ employee.employee_number }}</td>
                <td>{{ employee.personal_information?.full_name_desc }}</td>
                <td v-if="this.$page.props.auth.roles[0] == 'superadmin' || this.$page.props.auth.roles[0] == 'hr_director'">{{ employee.operating_unit?.name }}</td>
              </tr>
            </tbody>
        </v-table>
      </TableWrapper>
    </v-col>
    
    <v-col cols="12" md="2" class="d-flex align-center justify-center">
      <div class="d-flex flex-column align-center gap-2">
        <v-btn
          icon="mdi-greater-than"
          color="starbucks-green"
          variant="outlined"
          @click="addSelected()" 
          :disabled="!canAdd"
        ></v-btn>
        
        <v-btn
          icon="mdi-less-than"
          color="starbucks-green"
          variant="outlined"
          @click="removeSelected()" 
          :disabled="!canRemove"
        ></v-btn>
      </div>
    </v-col>
    
    <v-col cols="12" md="5">
      <TableWrapper>
        <div class="d-flex justify-space-between align-center mb-2">
          <div class="d-flex align-center gap-2">
              <v-checkbox 
                density="compact" 
                hide-details 
                v-model="selectAllRight" 
                @change="toggleSelectAllRight"
              ></v-checkbox>
              <span class="text-body-2">Select all</span>
            </div>
            <v-text-field
              v-model="rightFilter"
              density="compact"
              variant="outlined"
              hide-details
              rounded="lg"
              placeholder="Filter scheduled..."
              style="max-width: 240px;"
            />
        </div>
        <div class="v-card-title text-h6 font-weight-medium">
          Assigned Employees
        </div>
        <v-table fixed-header height="400">
          <thead>
            <tr>
              <th></th>
              <th>Employee ID</th>
              <th>Employee Name</th>
              <th v-if="this.$page.props.auth.roles[0] == 'superadmin' || this.$page.props.auth.roles[0] == 'hr_director'">Operating Unit</th>
            </tr>
          </thead>
            <tbody>
              <tr v-for="employee in filteredRightEmployees" :key="employee.id">
                <td><v-checkbox v-model="form.employees_to_remove" :value="employee.id"></v-checkbox></td>
                <td>{{ employee.employee_number }}</td>
                <td>{{ employee.personal_information?.full_name_desc }}</td>
                <td v-if="this.$page.props.auth.roles[0] == 'superadmin' || this.$page.props.auth.roles[0] == 'hr_director'">{{ employee.operating_unit?.name }}</td>
              </tr>
            </tbody>
        </v-table>
      </TableWrapper> 
    </v-col>
  </v-row>

  <!-- Floating Action Button -->
  <v-btn
    icon="mdi-arrow-left"
    size="large"
    color="starbucks-green"
    @click="goToIndex()"
    class="floating-back-btn"
    style="position: fixed; bottom: 24px; right: 24px; z-index: 1000;"
  >
  </v-btn>
</template>
<script>
  import SidebarLayout from '@/layouts/SidebarLayout.vue';
  import DailyTimeRecordTabs from '@/components/DailyTimeRecordTabs.vue';
  import TableWrapper from '@/components/TableWrapper.vue';
  import ButtonSuccess from '@/components/ButtonSuccess.vue';
  import ButtonMuted from '@/components/ButtonMuted.vue';
  import Pagination from '@/components/Pagination.vue';
  import { useForm } from '@inertiajs/vue3';
  import DeleteDialog from '@/components/DeleteDialog.vue';
  import FilterWrapper from '@/components/FilterWrapper.vue';
  import { employeeType } from '@/utils/EmployeeType';

  export default {
    layout: SidebarLayout,
    components: {
      DailyTimeRecordTabs,
      TableWrapper,
      ButtonSuccess,
      ButtonMuted,
      Pagination,
      DeleteDialog,
      FilterWrapper,
    },
    props:{
      errors: Object,
      employeesWithoutWorkShift: {
        type: Array,
        default: () => []
      },
      employeesWithWorkShift: {
        type: Array,
        default: () => []
      },
      operating_units: Object,
      employee_status: Object,
      weeklyShiftTemplate: Object,
      departments: Object,
    },
    data(){
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
        isAdmin: this.$page.props.auth.roles[0] == 'superadmin' || this.$page.props.auth.roles[0] == 'hr_director',
        activeTab: "shifts",
        isPanelOpen: [0],
        cacheKey: 'employee_work_shift_manage',
        filterForm: useForm({
          operating_unit: defaultOperatingUnitId,
          department: null,
          employee_status: null,
          employee_type: null,
        }),
        formOptions: {
          'employeeType': employeeType,
        },

        form: useForm({
          weekly_shift_template_id: this.weeklyShiftTemplate.id,
          employees_to_add: [],
          employees_to_remove: [],
        }),
        leftFilter: '',
        rightFilter: '',
      }
    },
   
    computed: {
      operatingUnitsWithAll(){
        return [
          { id: 'all', name: 'All'},
          ...this.operating_units
        ]
      },
      employeeStatusWithAll() {
        return [
          { id: 'all', name: 'All' },
          ...this.employee_status
        ];
      },
      employeeTypeWithAll() {
        return [
          { value: 'all', title: 'All' },
          ...this.formOptions.employeeType.map(type => ({
            value: type,
            title: type
          }))
        ];
      },


      selectAllLeft: {
        get() {
          return this.filteredLeftEmployees.length > 0 && 
            this.filteredLeftEmployees.every(emp => this.form.employees_to_add.includes(emp.id));
        },
        set(value) {
          if (value) {
            // Add all filtered employees to selection
            const filteredIds = this.filteredLeftEmployees.map(emp => emp.id);
            this.form.employees_to_add = [...new Set([...this.form.employees_to_add, ...filteredIds])];
          } else {
            // Remove all filtered employees from selection
            const filteredIds = this.filteredLeftEmployees.map(emp => emp.id);
            this.form.employees_to_add = this.form.employees_to_add.filter(id => !filteredIds.includes(id));
          }
        }
      },
      selectAllRight: {
        get() {
          return this.filteredRightEmployees.length > 0 && 
            this.filteredRightEmployees.every(emp => this.form.employees_to_remove.includes(emp.id));
        },
        set(value) {
          if (value) {
            // Add all filtered employees to selection
            const filteredIds = this.filteredRightEmployees.map(emp => emp.id);
            this.form.employees_to_remove = [...new Set([...this.form.employees_to_remove, ...filteredIds])];
          } else {
            // Remove all filtered employees from selection
            const filteredIds = this.filteredRightEmployees.map(emp => emp.id);
            this.form.employees_to_remove = this.form.employees_to_remove.filter(id => !filteredIds.includes(id));
          }
        }
      },
    
      canAdd(){
        return !!this.form.weekly_shift_template_id && this.form.employees_to_add.length > 0;
      },
      canRemove(){
        return !!this.form.weekly_shift_template_id && this.form.employees_to_remove.length > 0;
      },
      
      filteredLeftEmployees(){
        const employees = Array.isArray(this.employeesWithoutWorkShift?.data) ? this.employeesWithoutWorkShift.data : [];
        if(!this.leftFilter){ return employees; }
        const q = this.leftFilter.toLowerCase();
        return employees.filter(emp => {
          const name = `${emp.personal_information?.full_name_desc ?? ''}`.toLowerCase();
          const empNo = `${emp.employee_number ?? ''}`.toLowerCase();
          const ou = `${emp.operating_unit?.name ?? ''}`.toLowerCase();
          return name.includes(q) || empNo.includes(q) || ou.includes(q);
        });
      },

      filteredRightEmployees(){
        const employees = Array.isArray(this.employeesWithWorkShift?.data) ? this.employeesWithWorkShift.data : [];
        if(!this.rightFilter){ return employees; }
        const q = this.rightFilter.toLowerCase();
        return employees.filter(emp => {
          const name = `${emp.personal_information?.full_name_desc ?? ''}`.toLowerCase();
          const empNo = `${emp.employee_number ?? ''}`.toLowerCase();
          const ou = `${emp.operating_unit?.name ?? ''}`.toLowerCase();
          return name.includes(q) || empNo.includes(q) || ou.includes(q);
        });
      },
      
      sortedDays() {
        if (!this.weeklyShiftTemplate.days) return [];
        
        const dayOrder = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
        
        return this.weeklyShiftTemplate.days.sort((a, b) => {
          const dayA = dayOrder.indexOf(a.daily_shift_schedule.day_of_week);
          const dayB = dayOrder.indexOf(b.daily_shift_schedule.day_of_week);
          return dayA - dayB;
        });
      }
    },
    watch: {
      'filterForm.operating_unit': {
        immediate: true,
        handler(newVal){
          if(newVal){
            this.fetchDepartments(newVal);
          }
        }
      }
    },
    
    mounted() {
      // For non-admin users, automatically load departments for their operating unit
      if (!this.isAdmin && this.filterForm.operating_unit) {
        this.fetchDepartments(this.filterForm.operating_unit);
      }
    },
    methods: {
      toggleSelectAllLeft() {
        // This method is called by the checkbox @change event
        // The actual logic is handled by the computed property setter
      },
      
      toggleSelectAllRight() {
        // This method is called by the checkbox @change event
        // The actual logic is handled by the computed property setter
      },
      
      onOperatingUnitChange(newVal) {
        if (newVal) {
          this.fetchDepartments(newVal);
        }
      },

      addSelected(){
        if(!this.canAdd){ return; }
        const payload = {
          employee_ids: this.form.employees_to_add,
          weekly_shift_template_id: this.form.weekly_shift_template_id,
        };
        this.form.transform(() => payload).post(route('hrmanagement.dailytimerecord.employeeWorkShifts.assign'), {
          preserveScroll: true,
          preserveState: true,
          onSuccess: () => {
            this.form.employees_to_add = [];
            this.fetchEmployees();
          },
          onError: (errors) => {
            const errorMessages = Object.values(errors).flat().join(" ");
            this.showToast(`${errorMessages}`, "error");
          }
        });
      },

      removeSelected(){
        if(!this.canRemove){ return; }
        const payload = {
          employee_ids: this.form.employees_to_remove,
        };
        this.form.transform(() => payload).post(route('hrmanagement.dailytimerecord.employeeWorkShifts.remove'), {
          preserveScroll: true,
          preserveState: true,
          onSuccess: () => {
            this.form.employees_to_remove = [];
            this.fetchEmployees();
          },
          onError: (errors) => {
            const errorMessages = Object.values(errors).flat().join(" ");
            this.showToast(`${errorMessages}`, "error");
          }
        });
      },

      handleFilter(){
        // Get the current URL with signature parameters
        const currentUrl = new URL(window.location.href);
        const signature = currentUrl.searchParams.get('signature');
        const expires = currentUrl.searchParams.get('expires');

        // Build the route with signature parameters
        const routeUrl = route('hrmanagement.dailytimerecord.employeeWorkShifts.manage', { 
          id: this.weeklyShiftTemplate.id,
          signature: signature,
          expires: expires
        });

        this.filterForm.post(routeUrl, {
          preserveState: true,
          preserveScroll: true,
          only: ['employeesWithoutWorkShift', 'employeesWithWorkShift'],
        });
      },

      resetFilter() {
        this.filterForm.operating_unit = null;
        this.filterForm.department = null;
        this.filterForm.employee_status = null;
        this.filterForm.employee_type = null;
        this.handleFilter(); // same as before
      },

      goToIndex(){
        this.$inertia.visit(route('hrmanagement.dailytimerecord.employeeWorkShifts.index'));
      },
      
      fetchDepartments(newVal){
        // Get the current URL with signature parameters
        const currentUrl = new URL(window.location.href);
        const signature = currentUrl.searchParams.get('signature');
        const expires = currentUrl.searchParams.get('expires');
        
        // Build the route with signature parameters
        const routeUrl = route('hrmanagement.dailytimerecord.employeeWorkShifts.manage', { 
          id: this.weeklyShiftTemplate.id,
          signature: signature,
          expires: expires
        });
        
        this.$inertia.post(routeUrl,
        {
            operatingUnitId: newVal
          },
        {
          preserveState: true,
          preserveScroll: true,
          only: ['departments']
        });
      },
      
      formatTime(timeString) {
        if (!timeString) return 'N/A';
        const time = new Date(`2000-01-01T${timeString}`);
        return time.toLocaleTimeString('en-US', { 
          hour: '2-digit', 
          minute: '2-digit',
          hour12: true 
        });
      },
      
      calculateWorkingHours(timeIn, timeOut, breakStart, breakEnd) {
        if (!timeIn || !timeOut) return '0';
        
        const start = new Date(`2000-01-01T${timeIn}`);
        const end = new Date(`2000-01-01T${timeOut}`);
        const breakStartTime = new Date(`2000-01-01T${breakStart}`);
        const breakEndTime = new Date(`2000-01-01T${breakEnd}`);
        
        const totalMinutes = (end - start) / (1000 * 60);
        const breakMinutes = (breakEndTime - breakStartTime) / (1000 * 60);
        const workingMinutes = totalMinutes - breakMinutes;
        
        return (workingMinutes / 60).toFixed(1);
      },
      
      getDayCardColor(dayOfWeek) {
        const colors = {
          'Monday': 'blue-lighten-5',
          'Tuesday': 'green-lighten-5',
          'Wednesday': 'orange-lighten-5',
          'Thursday': 'purple-lighten-5',
          'Friday': 'red-lighten-5',
          'Saturday': 'grey-lighten-5',
          'Sunday': 'pink-lighten-5'
        };
        return colors[dayOfWeek] || 'grey-lighten-5';
      },
      
      getDayTextColor(dayOfWeek) {
        const colors = {
          'Monday': '#1976d2',
          'Tuesday': '#388e3c',
          'Wednesday': '#f57c00',
          'Thursday': '#7b1fa2',
          'Friday': '#d32f2f',
          'Saturday': '#616161',
          'Sunday': '#c2185b'
        };
        return colors[dayOfWeek] || '#616161';
      }
    }
  }
</script>

<style scoped>
.floating-back-btn {
  position: fixed !important;
  bottom: 24px !important;
  right: 24px !important;
  z-index: 1000 !important;
  box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2) !important;
  border-radius: 50% !important;
}
</style>