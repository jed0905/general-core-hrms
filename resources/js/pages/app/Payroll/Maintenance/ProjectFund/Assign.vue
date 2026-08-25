<template>
  <PayrollMaintenanceTabs :activeTab="activeTab" @update:activeTab="updateActiveTab" />
  
  <!-- Project Fund Information -->
  <v-card class="mb-6" elevation="2">
    <v-card-title class="d-flex align-center">
      <v-icon class="mr-3" color="primary">mdi-folder-account</v-icon>
      Project Fund Assignment
    </v-card-title>
    <v-card-text>
      <v-row>
        <v-col cols="12" md="3">
          <div class="text-body-2 text-grey-darken-1 mb-1">Project Name</div>
          <div class="text-h6 font-weight-medium">{{ payrollProjectFund.name }}</div>
        </v-col>
        <v-col cols="12" md="3">
          <div class="text-body-2 text-grey-darken-1 mb-1">Allocation</div>
          <div class="text-h6 font-weight-medium">{{ payrollProjectFund.allocation || 'N/A' }}</div>
        </v-col>
        <v-col cols="12" md="3">
          <div class="text-body-2 text-grey-darken-1 mb-1">Employee Status</div>
          <div class="text-h6 font-weight-medium">{{ payrollProjectFund.job_status?.name || 'N/A' }}</div>
        </v-col>
        <v-col cols="12" md="3">
          <div class="text-body-2 text-grey-darken-1 mb-1">Employee Type</div>
          <div class="text-h6 font-weight-medium">{{ payrollProjectFund.employee_type || 'N/A' }}</div>
        </v-col>
      </v-row>
    </v-card-text>
  </v-card>

  <v-row>
    <!-- Available Employees -->
    <v-col cols="12" md="6">
      <v-card elevation="2">
        <v-card-title class="d-flex align-center justify-space-between">
          <div class="d-flex align-center">
            <v-icon class="mr-3" color="success">mdi-account-plus</v-icon>
            Available Employees
          </div>
          <div class="d-flex align-center gap-3">
            <v-chip color="success" variant="tonal">
              {{ employees.length }} available
            </v-chip>
            <v-btn
              v-if="selectedAvailableEmployees.length > 0"
              color="success"
              variant="tonal"
              size="small"
              @click="showBulkAssignDialog"
              :loading="assigningSelected"
            >
              <v-icon>mdi-plus</v-icon>
              Assign Selected ({{ selectedAvailableEmployees.length }})
            </v-btn>
          </div>
        </v-card-title>
        <v-card-text>
          <v-text-field
            v-model="searchAvailable"
            prepend-inner-icon="mdi-magnify"
            label="Search employees..."
            variant="outlined"
            density="compact"
            clearable
            class="mb-4"
          ></v-text-field>
          
          <!-- Select All Checkbox -->
          <div class="d-flex align-center mb-3">
            <v-checkbox
              v-model="selectAllAvailable"
              :indeterminate="isIndeterminateAvailable"
              @change="toggleSelectAllAvailable"
              density="compact"
              hide-details
            ></v-checkbox>
            <span class="ml-2 text-body-2">
              Select All ({{ selectedAvailableEmployees.length }}/{{ filteredAvailableEmployees.length }})
            </span>
          </div>
          
          <div style="max-height: 500px; overflow-y: auto;">
            <v-list>
              <v-list-item
                v-for="employee in filteredAvailableEmployees"
                :key="employee.id"
                class="mb-2 border rounded"
              >
                <template v-slot:prepend>
                  <v-checkbox
                    v-model="selectedAvailableEmployees"
                    :value="employee.employee_number"
                    density="compact"
                    hide-details
                    class="mr-2"
                  ></v-checkbox>
                 
                  <span></span>
                  
                </template>
                
                <v-list-item-title class="font-weight-medium">
                  {{ getFullName(employee.personal_information) }}
                </v-list-item-title>
                <v-list-item-subtitle>
                  {{ employee.employee_number }} • {{ employee.position?.name }}
                </v-list-item-subtitle>
                
                <template v-slot:append>
                  <v-btn
                    color="success"
                    variant="tonal"
                    size="small"
                    @click="showAssignDialog(employee)"
                    :loading="assigningEmployee === employee.employee_number"
                  >
                    <v-icon>mdi-plus</v-icon>
                    Assign
                  </v-btn>
                </template>
              </v-list-item>
              
              <v-list-item v-if="filteredAvailableEmployees.length === 0" class="text-center text-grey">
                <v-list-item-title>No available employees found</v-list-item-title>
              </v-list-item>
            </v-list>
          </div>
        </v-card-text>
      </v-card>
    </v-col>

    <!-- Assigned Employees -->
    <v-col cols="12" md="6">
      <v-card elevation="2">
        <v-card-title class="d-flex align-center justify-space-between">
          <div class="d-flex align-center">
            <v-icon class="mr-3" color="primary">mdi-account-check</v-icon>
            Assigned Employees
          </div>
          <div class="d-flex align-center gap-3">
            <v-chip color="primary" variant="tonal">
              {{ assignedEmployees.length }} assigned
            </v-chip>
            <v-btn
              v-if="selectedAssignedEmployees.length > 0"
              color="error"
              variant="tonal"
              size="small"
              @click="showBulkRemoveDialog"
              :loading="removingSelected"
            >
              <v-icon>mdi-minus</v-icon>
              Remove Selected ({{ selectedAssignedEmployees.length }})
            </v-btn>
          </div>
        </v-card-title>
        <v-card-text>
          <v-text-field
            v-model="searchAssigned"
            prepend-inner-icon="mdi-magnify"
            label="Search assigned employees..."
            variant="outlined"
            density="compact"
            clearable
            class="mb-4"
          ></v-text-field>
          
          <!-- Select All Checkbox -->
          <div class="d-flex align-center mb-3">
            <v-checkbox
              v-model="selectAllAssigned"
              :indeterminate="isIndeterminateAssigned"
              @change="toggleSelectAllAssigned"
              density="compact"
              hide-details
            ></v-checkbox>
            <span class="ml-2 text-body-2">
              Select All ({{ selectedAssignedEmployees.length }}/{{ filteredAssignedEmployees.length }})
            </span>
          </div>
          
          <div style="max-height: 500px; overflow-y: auto;">
            <v-list>
              <v-list-item
                v-for="employee in filteredAssignedEmployees"
                :key="employee.id"
                class="mb-2 border rounded"
              >
                <template v-slot:prepend>
                  <v-checkbox
                    v-model="selectedAssignedEmployees"
                    :value="employee.employee_number"
                    density="compact"
                    hide-details
                    class="mr-2"
                  ></v-checkbox>
               
                </template>
                
                <v-list-item-title class="font-weight-medium">
                  {{ getFullName(employee.personal_information) }}
                </v-list-item-title>
                <v-list-item-subtitle>
                  {{ employee.employee_number }} • {{ employee.position?.name }}
                </v-list-item-subtitle>
                
                <template v-slot:append>
                  <v-btn
                    color="error"
                    variant="tonal"
                    size="small"
                    @click="showRemoveDialog(employee)"
                    :loading="removingEmployee === employee.employee_number"
                  >
                    <v-icon>mdi-minus</v-icon>
                    Remove
                  </v-btn>
                </template>
              </v-list-item>
              
              <v-list-item v-if="filteredAssignedEmployees.length === 0" class="text-center text-grey">
                <v-list-item-title>No employees assigned yet</v-list-item-title>
              </v-list-item>
            </v-list>
          </div>
        </v-card-text>
      </v-card>
    </v-col>
  </v-row>

  <!-- Floating Back Button -->
  <Link :href="route('payroll.maintenance.projectfund.index')">
    <v-btn
      color="primary"
      icon="mdi-arrow-left"
      size="large"
      class="floating-back-btn"
      elevation="8"
    >
    </v-btn>
  </Link>

  <!-- Confirmation Dialogs -->
  <v-dialog v-model="assignDialog" max-width="500">
    <v-card>
      <v-card-title class="d-flex align-center">
        <v-icon class="mr-3" color="success">mdi-account-plus</v-icon>
        Confirm Assignment
      </v-card-title>
      <v-card-text>
        <p>Are you sure you want to assign <strong>{{ assignDialogEmployee?.personalInformation ? getFullName(assignDialogEmployee.personalInformation) : 'this employee' }}</strong> to the project fund <strong>{{ payrollProjectFund.name }}</strong>?</p>
        <v-alert type="info" variant="tonal" class="mt-3">
          <v-alert-title>Note:</v-alert-title>
          If this employee is already assigned to another project fund, they will be automatically moved to this one.
        </v-alert>
      </v-card-text>
      <v-card-actions>
        <v-spacer></v-spacer>
        <v-btn
          color="grey"
          variant="text"
          @click="assignDialog = false"
        >
          Cancel
        </v-btn>
        <v-btn
          color="success"
          @click="confirmAssignEmployee"
          :loading="assigningEmployee === assignDialogEmployee?.employee_number"
        >
          Yes, Assign
        </v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>

  <v-dialog v-model="removeDialog" max-width="500">
    <v-card>
      <v-card-title class="d-flex align-center">
        <v-icon class="mr-3" color="error">mdi-account-minus</v-icon>
        Confirm Removal
      </v-card-title>
      <v-card-text>
        <p>Are you sure you want to remove <strong>{{ removeDialogEmployee?.personalInformation ? getFullName(removeDialogEmployee.personalInformation) : 'this employee' }}</strong> from the project fund <strong>{{ payrollProjectFund.name }}</strong>?</p>
        <v-alert type="warning" variant="tonal" class="mt-3">
          <v-alert-title>Warning:</v-alert-title>
          This action cannot be undone. The employee will be completely unassigned from any project fund.
        </v-alert>
      </v-card-text>
      <v-card-actions>
        <v-spacer></v-spacer>
        <v-btn
          color="grey"
          variant="text"
          @click="removeDialog = false"
        >
          Cancel
        </v-btn>
        <v-btn
          color="error"
          @click="confirmRemoveEmployee"
          :loading="removingEmployee === removeDialogEmployee?.employee_number"
        >
          Yes, Remove
        </v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>

  <v-dialog v-model="bulkAssignDialog" max-width="600">
    <v-card>
      <v-card-title class="d-flex align-center">
        <v-icon class="mr-3" color="success">mdi-account-multiple-plus</v-icon>
        Confirm Bulk Assignment
      </v-card-title>
      <v-card-text>
        <p>Are you sure you want to assign <strong>{{ selectedAvailableEmployees.length }} employee(s)</strong> to the project fund <strong>{{ payrollProjectFund.name }}</strong>?</p>
        
        <v-alert type="info" variant="tonal" class="mt-3">
          <v-alert-title>Note:</v-alert-title>
          If any of these employees are already assigned to other project funds, they will be automatically moved to this one.
        </v-alert>

        <div class="mt-4">
          <h4 class="text-subtitle-1 mb-2">Selected Employees:</h4>
          <v-list density="compact" class="bg-grey-lighten-5 rounded">
            <v-list-item
              v-for="employeeNumber in selectedAvailableEmployees"
              :key="employeeNumber"
              class="py-1"
            >
              <template v-slot:prepend>
                <v-avatar size="24" color="primary">
                  <span class="text-caption text-white">
                    {{ getInitialsForEmployee(employeeNumber) }}
                  </span>
                </v-avatar>
              </template>
              <v-list-item-title class="text-body-2">
                {{ getEmployeeName(employeeNumber) }}
              </v-list-item-title>
              <v-list-item-subtitle class="text-caption">
                {{ employeeNumber }}
              </v-list-item-subtitle>
            </v-list-item>
          </v-list>
        </div>
      </v-card-text>
      <v-card-actions>
        <v-spacer></v-spacer>
        <v-btn
          color="grey"
          variant="text"
          @click="bulkAssignDialog = false"
        >
          Cancel
        </v-btn>
        <v-btn
          color="success"
          @click="confirmBulkAssign"
          :loading="assigningSelected"
        >
          Yes, Assign All ({{ selectedAvailableEmployees.length }})
        </v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>

  <v-dialog v-model="bulkRemoveDialog" max-width="600">
    <v-card>
      <v-card-title class="d-flex align-center">
        <v-icon class="mr-3" color="error">mdi-account-multiple-minus</v-icon>
        Confirm Bulk Removal
      </v-card-title>
      <v-card-text>
        <p>Are you sure you want to remove <strong>{{ selectedAssignedEmployees.length }} employee(s)</strong> from the project fund <strong>{{ payrollProjectFund.name }}</strong>?</p>
        
        <v-alert type="warning" variant="tonal" class="mt-3">
          <v-alert-title>Warning:</v-alert-title>
          This action cannot be undone. All selected employees will be completely unassigned from any project fund.
        </v-alert>

        <div class="mt-4">
          <h4 class="text-subtitle-1 mb-2">Selected Employees:</h4>
          <v-list density="compact" class="bg-grey-lighten-5 rounded">
            <v-list-item
              v-for="employeeNumber in selectedAssignedEmployees"
              :key="employeeNumber"
              class="py-1"
            >
              <template v-slot:prepend>
                <v-avatar size="24" color="primary">
                  <span class="text-caption text-white">
                    {{ getInitialsForEmployee(employeeNumber) }}
                  </span>
                </v-avatar>
              </template>
              <v-list-item-title class="text-body-2">
                {{ getEmployeeName(employeeNumber) }}
              </v-list-item-title>
              <v-list-item-subtitle class="text-caption">
                {{ employeeNumber }}
              </v-list-item-subtitle>
            </v-list-item>
          </v-list>
        </div>
      </v-card-text>
      <v-card-actions>
        <v-spacer></v-spacer>
        <v-btn
          color="grey"
          variant="text"
          @click="bulkRemoveDialog = false"
        >
          Cancel
        </v-btn>
        <v-btn
          color="error"
          @click="confirmBulkRemove"
          :loading="removingSelected"
        >
          Yes, Remove All ({{ selectedAssignedEmployees.length }})
        </v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>

  <!-- Debug Section - Remove this after testing -->
  <!-- <v-card class="mt-4" color="grey-lighten-4">
    <v-card-title>Debug Info</v-card-title>
    <v-card-text>
      <p><strong>Available Employees Count:</strong> {{ employees.length }}</p>
      <p><strong>Assigned Employees Count:</strong> {{ assignedEmployees.length }}</p>
      <div v-if="employees.length > 0">
        <p><strong>First Available Employee:</strong></p>
        <pre>{{ JSON.stringify(employees[0], null, 2) }}</pre>
      </div>
      <div v-if="assignedEmployees.length > 0">
        <p><strong>First Assigned Employee:</strong></p>
        <pre>{{ JSON.stringify(assignedEmployees[0], null, 2) }}</pre>
      </div>
    </v-card-text>
  </v-card> -->

  <!-- Bulk Operation Loading Overlay -->
  <v-dialog v-model="bulkOperationLoading" persistent max-width="400">
    <v-card>
      <v-card-title class="d-flex align-center">
        <v-icon class="mr-3" :color="bulkOperationType === 'assign' ? 'success' : 'error'">
          {{ bulkOperationType === 'assign' ? 'mdi-account-multiple-plus' : 'mdi-account-multiple-minus' }}
        </v-icon>
        {{ bulkOperationType === 'assign' ? 'Assigning Employees' : 'Removing Employees' }}
      </v-card-title>
      <v-card-text>
        <div class="text-center">
          <v-progress-circular
            indeterminate
            :color="bulkOperationType === 'assign' ? 'success' : 'error'"
            size="64"
            width="6"
            class="mb-4"
          ></v-progress-circular>
          
          <div class="text-h6 mb-2">
            {{ bulkOperationType === 'assign' ? 'Assigning' : 'Removing' }} {{ bulkOperationCount }} employee(s)
          </div>
          
          <div class="text-body-2 text-grey-darken-1 mb-4">
            {{ bulkOperationType === 'assign' ? 'Please wait while we assign employees to the project fund...' : 'Please wait while we remove employees from the project fund...' }}
          </div>
          
          <v-progress-linear
            indeterminate
            :color="bulkOperationType === 'assign' ? 'success' : 'error'"
            height="4"
            rounded
          ></v-progress-linear>
        </div>
      </v-card-text>
    </v-card>
  </v-dialog>
</template>


<script>
import PayrollMaintenanceTabs from '@/components/Payroll/PayrollMaintenanceTabs.vue'
import SidebarLayout from '@/layouts/SidebarLayout.vue'
import { Link } from '@inertiajs/vue3'

export default {
  layout: SidebarLayout,
  components: {
    PayrollMaintenanceTabs,
    Link,
  },
  props: {
    payrollProjectFund: Object,
    employees: Array,
    assignedEmployees: Array,
  },
  data() {
    return {
      activeTab: 'funds',
      searchAvailable: '',
      searchAssigned: '',
      assigningEmployee: null,
      removingEmployee: null,
      selectedAvailableEmployees: [],
      selectedAssignedEmployees: [],
      selectAllAvailable: false,
      selectAllAssigned: false,
      assigningSelected: false,
      removingSelected: false,
      assignDialog: false,
      removeDialog: false,
      bulkAssignDialog: false,
      bulkRemoveDialog: false,
      assignDialogEmployee: null,
      removeDialogEmployee: null,
      bulkOperationLoading: false,
      bulkOperationType: 'assign', // 'assign' or 'remove'
      bulkOperationCount: 0,
    }
  },
  computed: {
    filteredAvailableEmployees() {
      if (!this.searchAvailable) return this.employees
      const search = this.searchAvailable.toLowerCase()
      return this.employees.filter(employee => {
        const fullName = this.getFullName(employee.personalInformation).toLowerCase()
        const employeeNumber = employee.employee_number?.toLowerCase() || ''
        const position = employee.position?.name?.toLowerCase() || ''
        return fullName.includes(search) || employeeNumber.includes(search) || position.includes(search)
      })
    },
    filteredAssignedEmployees() {
      if (!this.searchAssigned) return this.assignedEmployees
      const search = this.searchAssigned.toLowerCase()
      return this.assignedEmployees.filter(employee => {
        const fullName = this.getFullName(employee.personalInformation).toLowerCase()
        const employeeNumber = employee.employee_number?.toLowerCase() || ''
        const position = employee.position?.name?.toLowerCase() || ''
        return fullName.includes(search) || employeeNumber.includes(search) || position.includes(search)
      })
    },
    isIndeterminateAvailable() {
      const filteredCount = this.filteredAvailableEmployees.length
      const selectedCount = this.selectedAvailableEmployees.length
      return selectedCount > 0 && selectedCount < filteredCount
    },
    isIndeterminateAssigned() {
      const filteredCount = this.filteredAssignedEmployees.length
      const selectedCount = this.selectedAssignedEmployees.length
      return selectedCount > 0 && selectedCount < filteredCount
    }
  },
  methods: {
    updateActiveTab(tab) {
      this.activeTab = tab
    },
    getFullName(personalInfo) {
      if (!personalInfo) return 'N/A'
      
      const lastName = personalInfo.lastname || ''
      const suffix = personalInfo.suffix || ''
      const firstName = personalInfo.firstname || ''
      const middleName = personalInfo.middlename || ''
      
      // Add first letter of middle name with period if middle name exists
      const middleInitial = middleName ? `${middleName.charAt(0)}.` : ''
      
      return `${lastName} ${suffix}, ${firstName} ${middleInitial}`.trim()
    },
    getInitials(firstName, lastName) {
      if (!firstName || !lastName) return 'N/A'
      return `${firstName.charAt(0)}${lastName.charAt(0)}`.toUpperCase()
    },
    showAssignDialog(employee) {
      this.assignDialogEmployee = employee
      this.assignDialog = true
    },
    confirmAssignEmployee() {
      const employee = this.assignDialogEmployee
      this.assigningEmployee = employee.employee_number
      this.$inertia.post(route('payroll.maintenance.projectfund.assignEmployee'), {
        employee_number: employee.employee_number,
        project_fund_id: this.payrollProjectFund.id
      }, {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
          this.showToast('Employee assigned successfully', 'success')
          this.assigningEmployee = null
          this.assignDialog = false
          this.assignDialogEmployee = null
          // Refresh the page to show updated data
          this.$inertia.reload()
        },
        onError: (errors) => {
          const errorMessages = Object.values(errors).flat().join(" ")
          this.showToast(`${errorMessages}`, 'error')
          this.assigningEmployee = null
          this.assignDialog = false
          this.assignDialogEmployee = null
        }
      })
    },
    showRemoveDialog(employee) {
      this.removeDialogEmployee = employee
      this.removeDialog = true
    },
    confirmRemoveEmployee() {
      const employee = this.removeDialogEmployee
      this.removingEmployee = employee.employee_number
      this.$inertia.delete(route('payroll.maintenance.projectfund.removeEmployee', employee.employee_number), {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
          this.showToast('Employee removed successfully', 'success')
          this.removingEmployee = null
          this.removeDialog = false
          this.removeDialogEmployee = null
          // Refresh the page to show updated data
          this.$inertia.reload()
        },
        onError: (errors) => {
          const errorMessages = Object.values(errors).flat().join(" ")
          this.showToast(`${errorMessages}`, 'error')
          this.removingEmployee = null
          this.removeDialog = false
          this.removeDialogEmployee = null
        }
      })
    },
    toggleSelectAllAvailable() {
      if (this.selectAllAvailable) {
        this.selectedAvailableEmployees = this.filteredAvailableEmployees.map(emp => emp.employee_number)
      } else {
        this.selectedAvailableEmployees = []
      }
    },
    toggleSelectAllAssigned() {
      if (this.selectAllAssigned) {
        this.selectedAssignedEmployees = this.filteredAssignedEmployees.map(emp => emp.employee_number)
      } else {
        this.selectedAssignedEmployees = []
      }
    },
    showBulkAssignDialog() {
      if (this.selectedAvailableEmployees.length === 0) return
      this.bulkAssignDialog = true
    },
    async confirmBulkAssign() {
      if (this.selectedAvailableEmployees.length === 0) return
      
      this.assigningSelected = true
      const selectedCount = this.selectedAvailableEmployees.length
      
      // Show loading overlay
      this.bulkOperationLoading = true
      this.bulkOperationType = 'assign'
      this.bulkOperationCount = selectedCount
      this.bulkAssignDialog = false
      
      try {
        await this.$inertia.post(route('payroll.maintenance.projectfund.assignEmployeesBulk'), {
          employee_numbers: this.selectedAvailableEmployees,
          project_fund_id: this.payrollProjectFund.id
        }, {
          preserveScroll: true,
          preserveState: true,
          onSuccess: () => {
            this.showToast(`${selectedCount} employee(s) assigned successfully`, 'success')
            this.$inertia.reload()
          },
          onError: (errors) => {
            const errorMessages = Object.values(errors).flat().join(" ")
            this.showToast(`${errorMessages}`, 'error')
          }
        })
      } catch (error) {
        this.showToast('Failed to assign employees', 'error')
      } finally {
        // Hide loading overlay
        this.bulkOperationLoading = false
        this.assigningSelected = false
        this.selectedAvailableEmployees = []
        this.selectAllAvailable = false
      }
    },
    showBulkRemoveDialog() {
      if (this.selectedAssignedEmployees.length === 0) return
      this.bulkRemoveDialog = true
    },

    async confirmBulkRemove() {
      if (this.selectedAssignedEmployees.length === 0) return
      
      this.removingSelected = true
      const selectedCount = this.selectedAssignedEmployees.length
      
      // Show loading overlay
      this.bulkOperationLoading = true
      this.bulkOperationType = 'remove'
      this.bulkOperationCount = selectedCount
      this.bulkRemoveDialog = false
      
      try {
        await this.$inertia.post(route('payroll.maintenance.projectfund.removeEmployeesBulk'), {
          employee_numbers: this.selectedAssignedEmployees
        }, {
          preserveScroll: true,
          preserveState: true,
          onSuccess: () => {
            this.showToast(`${selectedCount} employee(s) removed successfully`, 'success')
            this.$inertia.reload()
          },
          onError: (errors) => {
            const errorMessages = Object.values(errors).flat().join(" ")
            this.showToast(`${errorMessages}`, 'error')
          }
        })
      } catch (error) {
        this.showToast('Failed to remove employees', 'error')
      } finally {
        // Hide loading overlay
        this.bulkOperationLoading = false
        this.removingSelected = false
        this.selectedAssignedEmployees = []
        this.selectAllAssigned = false
      }
    },
    
    assignEmployeeByNumber(employeeNumber) {
      return new Promise((resolve, reject) => {
        this.$inertia.post(route('payroll.maintenance.projectfund.assignEmployee'), {
          employee_number: employeeNumber,
          project_fund_id: this.payrollProjectFund.id
        }, {
          preserveScroll: true,
          preserveState: true,
          onSuccess: () => resolve(),
          onError: () => reject()
        })
      })
    },
    removeEmployeeByNumber(employeeNumber) {
      return new Promise((resolve, reject) => {
        this.$inertia.delete(route('payroll.maintenance.projectfund.removeEmployee', employeeNumber), {
          preserveScroll: true,
          preserveState: true,
          onSuccess: () => resolve(),
          onError: () => reject()
        })
      })
    },
    getEmployeeName(employeeNumber) {
      const allEmployees = [...this.employees, ...this.assignedEmployees]
      const employee = allEmployees.find(emp => emp.employee_number === employeeNumber)
      return employee ? this.getFullName(employee.personal_information) : 'Unknown Employee'
    },
    getInitialsForEmployee(employeeNumber) {
      const allEmployees = [...this.employees, ...this.assignedEmployees]
      const employee = allEmployees.find(emp => emp.employee_number === employeeNumber)
      if (employee && employee.personal_information) {
        return this.getInitials(employee.personal_information.firstname, employee.personal_information.lastname)
      }
      return 'N/A'
    }
  }
}
</script>

<style scoped>
.v-list-item {
  transition: all 0.2s ease;
}

.v-list-item:hover {
  background-color: rgba(0, 0, 0, 0.04);
}

.border {
  border: 1px solid rgba(0, 0, 0, 0.12) !important;
}

/* Ensure table content is above floating button */
.v-list-item {
  position: relative;
  z-index: 20;
}

.v-btn {
  position: relative;
  z-index: 20;
}

.floating-back-btn {
  position: fixed !important;
  bottom: 24px !important;
  right: 24px !important;
  z-index: 10 !important;
  opacity: 0.8 !important;
  transition: all 0.3s ease !important;
}

.floating-back-btn:hover {
  opacity: 1 !important;
  transform: scale(1.1) !important;
  box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15) !important;
}

/* Responsive positioning */
@media (max-width: 768px) {
  .floating-back-btn {
    bottom: 16px !important;
    right: 16px !important;
  }
}
</style>