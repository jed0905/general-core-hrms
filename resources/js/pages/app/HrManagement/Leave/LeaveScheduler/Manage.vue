<template>
  <LeaveManagementTabs v-model:activeTab="activeTab" />
  <v-row>
    <v-col cols="12">
      <v-card>
        <v-card-text>
          <v-row>
            <v-col cols="12" md="4"  v-if="this.$page.props.auth.roles[0] == 'superadmin' || this.$page.props.auth.roles[0] == 'hr_director'">
              <v-select
               
                :items="operatingUnitsWithAll"
                item-title="name"
                item-value="id"
                v-model="form.operating_unit_id"
                label="Operating Unit"
                variant="outlined"
                density="compact"
                hide-details
                rounded="lg"
                @input="saveToCache()"
              ></v-select>
            </v-col>
             <v-col cols="12" md="4">
              <v-select 
                :items="leaves" 
                item-title="name"
                item-value="id"
                v-model="form.leave_id" 
                label="Leave" 
                variant="outlined"
                density="compact"
                hide-details
                rounded="lg"
                @input="saveToCache()"
              />
            </v-col>
            <v-col cols="12" md="4">
              <v-select
                :items="jobStatusesWithAll"
                item-title="name"
                item-value="id"
                v-model="form.job_status_id"
                label="Employee Status"
                variant="outlined"
                density="compact"
                hide-details
                rounded="lg"
                @input="saveToCache()"
              ></v-select>
            </v-col>
            <v-col cols="12" md="4">
              <v-select
                :items="employeeTypeWithAll"
                item-title="title"
                item-value="value"
                v-model="form.employee_type"
                label="Employee Type"
                variant="outlined"
                density="compact"
                hide-details
                rounded="lg"
                @input="saveToCache()"
              ></v-select>
            </v-col>
            
            <v-col cols="12" class="d-flex justify-end">
              <v-btn @click="resetFilter()" min-width="120" color="grey-darken-1" variant="outlined" rounded="xl">Reset Filter</v-btn>
              <v-btn @click="fetchEmployees()" class="ml-4"   color="starbucks-green" prepend-icon="mdi-magnify" rounded="xl" :disabled="!form.leave_id">Fetch Employees</v-btn>
            </v-col>
          </v-row>
        </v-card-text>
      </v-card>
    </v-col>
    <v-col cols="12">
      <v-card>
        <v-card-text>
          <v-row>
             <v-col cols="12" md="6">
              <v-select
                label="Day to Run"
                variant="outlined"
                density="compact"
                hide-details
                rounded="lg"
                :items="days"
                v-model="form.run_day"
                @input="saveToCache()"
              ></v-select>
            </v-col>
            <v-col cols="12" md="6">
              <v-text-field
                label="Credits to Add"
                variant="outlined"
                density="compact"
                hide-details
                rounded="lg"
                type="number"
                v-model="form.credits_to_add"
                @input="saveToCache()"
              ></v-text-field>
            </v-col>
          </v-row>
        </v-card-text>
      </v-card>
    </v-col>
    <v-col cols="12" md="5">
      <v-card>
        <v-card-text>
          
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
            <!-- <v-btn color="starbucks-green" prepend-icon="mdi-content-save" rounded="xl" @click="addSelected" :disabled="!canAdd">Add Selected</v-btn> -->
          </div>
          <v-table fixed-header height="400">
            <thead>
              <tr>
                <th></th>
                <th>Employee ID</th>
                <th>Employee Name</th>
                <th
                  v-if="this.$page.props.auth.roles[0] == 'superadmin' || this.$page.props.auth.roles[0] == 'hr_director'"
                >Operating Unit</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="employee in filteredLeftEmployees" :key="employee.id">
                <td><v-checkbox v-model="form.employees_to_add" :value="employee.id"></v-checkbox></td>
                <td>{{ employee.employee_number }}</td>
                <td>{{ employee.personal_information?.lastname }} {{ employee.personal_information?.suffix ?? '' }}, {{ employee.personal_information?.firstname }} {{ employee.personal_information?.middlename ?? '' }}</td>
                <td v-if="this.$page.props.auth.roles[0] == 'superadmin' || this.$page.props.auth.roles[0] == 'hr_director'">{{ employee.operating_unit?.name }}</td>
             
              </tr>
            </tbody>
          </v-table>
          
        </v-card-text>
      </v-card>
    </v-col>
    
   <v-col cols="12" md="2" class="d-flex align-center justify-center">
    <div class="d-flex flex-column align-center gap-2">
      <v-btn
        icon="mdi-greater-than"
        color="starbucks-green"
        variant="outlined"
        @click="addSelected()" :disabled="!canAdd"
      ></v-btn>
      <v-btn
        icon="mdi-less-than"
        color="starbucks-green"
        variant="outlined"
        @click="removeSelected()" :disabled="!canRemove"
      ></v-btn>
    </div>
   </v-col>
    
    <v-col cols="12" md="5">
      <v-card>
        <v-card-text>
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
            <!-- <v-btn color="error" prepend-icon="mdi-delete" rounded="xl" @click="removeSelected" :disabled="!canRemove">Remove Selected</v-btn> -->
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
                <td>{{ employee.personal_information?.lastname }} {{ employee.personal_information?.suffix ?? '' }}, {{ employee.personal_information?.firstname }} {{ employee.personal_information?.middlename ?? '' }}</td>
                <td v-if="this.$page.props.auth.roles[0] == 'superadmin' || this.$page.props.auth.roles[0] == 'hr_director'">{{ employee.operating_unit?.name }}</td>
              </tr>
            </tbody>
          </v-table>
        </v-card-text>
      </v-card>
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
  import LeaveManagementTabs from '@/components/LeaveManagementTabs.vue';
  import { useForm } from '@inertiajs/vue3';
  import useVuelidate from '@vuelidate/core';
  import { required, minLength } from '@vuelidate/validators';
  import { employeeType } from '@/utils/EmployeeType';

  export default {
    layout: SidebarLayout,
    components: {
      LeaveManagementTabs,
    },
    props: {
      errors: Object,
      jobStatuses: {
        type: Object,
        default: () => []
      },
      leaves: {
        type: Object,
        default: () => []
      },
      employees: {
        type: Array,
        default: () => []
      },
      employeeThatHasLeaveScheduler: {
        type: Array,
        default: () => []
      },
      leaveId: {
        type: [Number, String, null],
        default: null
      },
      jobStatusId: {
        type: [Number, String, null],
        default: null
      },
      employeeType: {
        type: [String, null],
        default: null
      },
      operatingUnits: {
        type: Object,
        default: () => []
      }
    },
    data(){
      return{
        activeTab: 'configure',
        days: ['1','2','3','4','5','6','7','8','9','10','11','12','13','14','15','16','17','18','19','20','21','22','23','24','25','26','27','28','29','30','31'],
        v$: useVuelidate(),
        form: useForm({
          leave_id: this.leaveId ?? null,
          job_status_id: this.jobStatusId ?? null,
          employee_type: this.employeeType ?? null,
          operating_unit_id: null,
          run_day: null,
          credits_to_add: null,
          employees_to_add: [],
          employees_to_remove: [],
        }),
        formOptions: {
          'employeeType' : employeeType,
        },

        cacheKey: 'leave_scheduler_manage',
        leftEmployees: [...this.employees],
        rightEmployees: [...this.employeeThatHasLeaveScheduler],
        leftFilter: '',
        rightFilter: '',
      }
    },
     mounted(){
       // Check if we have URL parameters from a redirect (prioritize these over cache)
       const hasUrlParams = this.leaveId || this.jobStatusId || this.employeeType;
       
       if(hasUrlParams){
         // Update cache with URL parameters
         const urlParams = {
           leave_id: this.leaveId,
           job_status_id: this.jobStatusId,
           employee_type: this.employeeType,
           run_day: null,
           credits_to_add: null,
           employees_to_add: [],
           employees_to_remove: [],
         };
         localStorage.setItem(this.cacheKey, JSON.stringify(urlParams));
         // Manually assign values to form properties
         this.form.leave_id = this.leaveId;
         this.form.job_status_id = this.jobStatusId;
         this.form.employee_type = this.employeeType;
         this.form.run_day = null;
         this.form.credits_to_add = null;
         this.form.employees_to_add = [];
         this.form.employees_to_remove = [];
         // Fetch employees with the URL parameters
         if(this.form.leave_id){
           this.fetchEmployees();
         }
       } else {
         // No URL parameters, try to load from cache
         const cached = localStorage.getItem(this.cacheKey);
         if(cached){
           const cachedData = JSON.parse(cached);
           // Manually assign cached values to form properties
           this.form.leave_id = cachedData.leave_id;
           this.form.job_status_id = cachedData.job_status_id;
           this.form.employee_type = cachedData.employee_type;
           this.form.run_day = cachedData.run_day;
           this.form.credits_to_add = cachedData.credits_to_add;
           this.form.employees_to_add = cachedData.employees_to_add || [];
           this.form.employees_to_remove = cachedData.employees_to_remove || [];
           // If we have a leave_id in the cached data, fetch employees to match the filters
           if(this.form.leave_id){
             this.fetchEmployees();
           }
         }
       }
     },

    computed: {
      operatingUnitsWithAll(){
        return [
          { id: 'all', name: 'All'},
          ...this.operatingUnits
        ]
      },
      jobStatusesWithAll() {
        return [
          { id: 'all', name: 'All' },
          ...this.jobStatuses
        ];
      },
      employeeTypeWithAll() {
        return [
          { value: 'all', title: 'All' },
          ...this.formOptions.employeeType.map(type => ({
            value: type, // Match DB values exactly (e.g., 'Teaching', 'Non-Teaching')
            title: type
          }))
        ];
      },
      selectAllLeft: {
        get() {
          return this.leftEmployees.length > 0 && this.form.employees_to_add.length === this.leftEmployees.length;
        },
        set(value) {
          if (value) {
            this.form.employees_to_add = this.leftEmployees.map(emp => emp.id);
          } else {
            this.form.employees_to_add = [];
          }
        }
      },
      selectAllRight: {
        get() {
          return this.rightEmployees.length > 0 && this.form.employees_to_remove.length === this.rightEmployees.length;
        },
        set(value) {
          if (value) {
            this.form.employees_to_remove = this.rightEmployees.map(emp => emp.id);
          } else {
            this.form.employees_to_remove = [];
          }
        }
      },
      
      canAdd(){
        return !!this.form.leave_id && !!this.form.run_day && this.form.credits_to_add !== null && this.form.employees_to_add.length > 0;
      },

      canRemove(){
        return !!this.form.leave_id && this.form.employees_to_remove.length > 0;
      },

      filteredLeftEmployees(){
        if(!this.leftFilter){ return this.leftEmployees; }
        const q = this.leftFilter.toLowerCase();
        return this.leftEmployees.filter(emp => {
          const name = `${emp.personal_information?.lastname ?? ''}, ${emp.personal_information?.firstname ?? ''} ${emp.personal_information?.middlename ?? ''}`.toLowerCase();
          const empNo = `${emp.employee_number ?? ''}`.toLowerCase();
          const ou = `${emp.operating_unit?.name ?? ''}`.toLowerCase();
          return name.includes(q) || empNo.includes(q) || ou.includes(q);
        });
      },

      filteredRightEmployees(){
        if(!this.rightFilter){ return this.rightEmployees; }
        const q = this.rightFilter.toLowerCase();
        return this.rightEmployees.filter(emp => {
          const name = `${emp.personal_information?.lastname ?? ''}, ${emp.personal_information?.firstname ?? ''} ${emp.personal_information?.middlename ?? ''}`.toLowerCase();
          const empNo = `${emp.employee_number ?? ''}`.toLowerCase();
          const ou = `${emp.operating_unit?.name ?? ''}`.toLowerCase();
          return name.includes(q) || empNo.includes(q) || ou.includes(q);
        });
      }
      
    },
  
    watch: {
      employees: {
        immediate: true,
        handler(newVal){
          this.leftEmployees = [...(newVal || [])];
        }
      },
      employeeThatHasLeaveScheduler: {
        immediate: true,
        handler(newVal){
          this.rightEmployees = [...(newVal || [])];
        }
      }
    },

    methods: {

      saveToCache(){
        // Save to localStorage on each input
        localStorage.setItem(this.cacheKey, JSON.stringify(this.form));
      },


      fetchEmployees(){
        this.form.post(route('hrmanagement.leave.scheduler.manage'), {
          preserveState: true,
          preserveScroll: true,
          only: ['employees', 'employeeThatHasLeaveScheduler', 'leaveId'],
          onSuccess: (page) => {
            // Sync reactive arrays with latest props
            this.leftEmployees = [...(page.props.employees || [])];
            this.rightEmployees = [...(page.props.employeeThatHasLeaveScheduler || [])];
          }
        });
      },

       resetFilter(){
        localStorage.removeItem(this.cacheKey);
        this.form.employees_to_add = [];
        this.form.employees_to_remove = [];
        this.form.run_day = null;
        this.form.credits_to_add = null;
        this.form.leave_id = null;
        this.form.job_status_id = null;
        this.form.employee_type = null;
        this.form.operating_unit_id = null;
        // Persist cleared state so mounted() won't reload stale cache
        this.saveToCache();
        this.form.clearErrors();
        // Immediately clear tables
        this.leftEmployees = [];
        this.rightEmployees = [];
        this.fetchEmployees();
       },

    

      toggleSelectAllLeft() {},
      toggleSelectAllRight() {},

      addSelected(){
        if(!this.canAdd){ return; }
        const payload = {
          leave_id: this.form.leave_id,
          run_day: this.form.run_day,
          credits_to_add: this.form.credits_to_add,
          employees_to_add: this.form.employees_to_add,
          job_status_id: this.form.job_status_id,
          employee_type: this.form.employee_type,
        };
        this.form.transform(() => payload).post(route('hrmanagement.leave.scheduler.store'), {
          onSuccess: () => {
            this.form.employees_to_add = [];
            this.form.run_day = null;
            this.form.credits_to_add = null;
            // Don't call fetchEmployees here as the redirect will handle it
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
          leave_id: this.form.leave_id,
          employees_to_remove: this.form.employees_to_remove,
          job_status_id: this.form.job_status_id,
          employee_type: this.form.employee_type,
        };
        this.form.transform(() => payload).post(route('hrmanagement.leave.scheduler.remove'), {
          onSuccess: () => {
            this.form.employees_to_remove = [];
            // Don't call fetchEmployees here as the redirect will handle it
          },
          onError: (errors) => {
            const errorMessages = Object.values(errors).flat().join(" ");
            this.showToast(`${errorMessages}`, "error");
          }
        });
      },

      goToIndex(){
        this.$inertia.visit(route('hrmanagement.leave.scheduler.index'));
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