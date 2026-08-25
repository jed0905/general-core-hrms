<template>
  <PayrollEmployeeMaintenanceTabs :activeTab="activeTab" @update:activeTab="updateActiveTab" />

  <div class="d-flex align-center justify-space-between mb-4">
    <!-- Previous Button -->
    <Link v-if="navigation.previous" :href="route('payroll.employee.maintenance.accounts.view', navigation.previous.id)">
      <v-btn
        variant="tonal"
        color="starbucks-green"
        prepend-icon="mdi-arrow-left"
        rounded="xl"
        min-width="120"
      >
        {{ navigation.previous.name }}
      </v-btn>
    </Link>
    <v-btn
      v-else
      variant="tonal"
      color="starbucks-green"
      prepend-icon="mdi-arrow-left"
      rounded="xl"
      min-width="120"
      disabled
    >
      Previous Employee
    </v-btn>

    <!-- Next Button -->
    <Link v-if="navigation.next" :href="route('payroll.employee.maintenance.accounts.view', navigation.next.id)">
      <v-btn
        variant="tonal"
        color="starbucks-green"
        append-icon="mdi-arrow-right"
        rounded="xl"
        min-width="120"
      >
        {{ navigation.next.name }}
      </v-btn>
    </Link>
    <v-btn
      v-else
      variant="tonal"
      color="starbucks-green"
      append-icon="mdi-arrow-right"
      rounded="xl"
      min-width="120"
      disabled
    >
      Next Employee
    </v-btn>
  </div>



  <v-card class="mb-4">
    <v-card-text>
      <v-row>
        <v-col cols="12" md="4">
          <div class="text-subtitle-2 text-grey">Employee ID</div>
          <div class="text-body-1">{{ employee.employee_number }}</div>
        </v-col>
        <v-col cols="12" md="4">
          <div class="text-subtitle-2 text-grey">Name</div>
          <div class="text-body-1">{{ employee.personal_information?.lastname }} {{ employee.personal_information?.suffix ?? '' }}, {{ employee.personal_information?.firstname ?? ''}} {{ employee.personal_information?.middlename ? employee.personal_information.middlename.charAt(0) + '.' : '' }}</div>
        </v-col>
        <v-col cols="12" md="4">
          <div class="text-subtitle-2 text-grey">Position</div>
          <div class="text-body-1">{{ employee.position?.government_position.name ?? '' }}</div>
        </v-col>
        <v-col cols="12" md="4">
          <div class="text-subtitle-2 text-grey">Department</div>
          <div class="text-body-1">{{ employee.department?.name ?? '' }}</div>
        </v-col>
        <v-col cols="12" md="4">
          <div class="text-subtitle-2 text-grey">Employment Status</div>
          <div class="text-body-1">{{ employee.job_status.name }}</div>
        </v-col>
        <v-col cols="12" md="4">
          <div class="text-subtitle-2 text-grey">Employee Type</div>
          <div class="text-body-1">{{ employee.employee_type }}</div>
        </v-col>
        <v-col cols="12" md="4">
          <div class="text-subtitle-2 text-grey">Date Hired</div>
          <div class="text-body-1">{{ employee.date_hired != null ? new Date(employee.date_hired).toLocaleDateString('en-US', {month: 'long', day: 'numeric', year: 'numeric'}) : 'No Date Hired Set' }}</div>
        </v-col>
      </v-row>
    </v-card-text>
  </v-card>

  <TableWrapper>
    <div class="d-flex align-center justify-end">
      <Link :href="route('payroll.employee.maintenance.accounts.create', {id:employee.id})">
        <v-btn
          color="starbucks-green"
          prepend-icon="mdi-plus"
          rounded="xl"
          min-width="120"
        >
          Add
        </v-btn>
      </Link>
    </div>
    <v-table>
      <thead>
        <tr>
          <th>Account Type</th>
          <th>Account Number</th>
          <th class="text-center">Action</th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="employeeAccounts in employeeAccounts.data" :key="employeeAccounts.id">
          <td>{{ employeeAccounts.account_type?.name }}</td>
          <td>{{ employeeAccounts.account_number }}</td>
          <td class="text-center">
            <v-btn
              variant="tonal"
              color="error"
              icon="mdi-delete"
              size="x-small"
              @click="deleteDialog = true; id = employeeAccounts.id"
            ></v-btn>
            <Link :href="route('payroll.employee.maintenance.accounts.edit', {id:employeeAccounts.id})">
              <v-btn
                variant="tonal"
                color="warning"
                icon="mdi-pencil"
                size="x-small"
                  class="ml-4"
                ></v-btn>
            </Link>
          </td>
        </tr>
      </tbody>
    </v-table>
    <Pagination 
      :meta="employeeAccounts.meta" 
    />
  </TableWrapper>

  <!-- <pre>{{ navigation }}</pre> -->  

   <div class="d-flex align-center justify-space-between mt-6">
    <!-- Previous Button -->
    <Link v-if="navigation.previous" :href="route('payroll.employee.maintenance.accounts.view', navigation.previous.id)">
      <v-btn
        variant="tonal"
        color="starbucks-green"
        prepend-icon="mdi-arrow-left"
        rounded="xl"
        min-width="120"
      >
        {{ navigation.previous.name }}
      </v-btn>
    </Link>
    <v-btn
      v-else
      variant="tonal"
      color="starbucks-green"
      prepend-icon="mdi-arrow-left"
      rounded="xl"
      min-width="120"
      disabled
    >
      Previous Employee
    </v-btn>

    <!-- Next Button -->
    <Link v-if="navigation.next" :href="route('payroll.employee.maintenance.accounts.view', navigation.next.id)">
      <v-btn
        variant="tonal"
        color="starbucks-green"
        append-icon="mdi-arrow-right"
        rounded="xl"
        min-width="120"
      >
        {{ navigation.next.name }}
      </v-btn>
    </Link>
    <v-btn
      v-else
      variant="tonal"
      color="starbucks-green"
      append-icon="mdi-arrow-right"
      rounded="xl"
      min-width="120"
      disabled
    >
      Next Employee
    </v-btn>
  </div>

  <DeleteDialog 
    :modelValue="deleteDialog"
    @update:modelValue="deleteDialog = $event"
    @confirm="deleteAccount()"
    @cancel="deleteDialog = false"
  />
</template>
<script>
  import Layout from '@/layouts/SidebarLayout.vue'
  import PayrollEmployeeMaintenanceTabs from '@/components/Payroll/PayrollEmployeeMaintenanceTabs.vue'
  import TableWrapper from '@/components/TableWrapper.vue'
  import { useForm } from '@inertiajs/vue3'
  import Pagination from '@/components/Pagination.vue'
  import DeleteDialog from '@/components/DeleteDialog.vue'

  export default {
    layout: Layout,
    components: {
      PayrollEmployeeMaintenanceTabs,
      TableWrapper,
      Pagination,
      DeleteDialog,
    },
    props: {
      employee: Object,
      employeeAccounts: Object,
      navigation: Object,
    },
    data() {
      return {
        activeTab: 'accounts',
        deleteDialog: false,
      }
    },
    methods: {
      deleteAccount(){
        this.$inertia.delete(route('payroll.employee.maintenance.accounts.delete', {id:this.id}),{
          preserveScroll: true,
          preserveState: true,
          onSuccess: () => {
            this.showToast('Account deleted successfully', 'success');
            this.deleteDialog = false;
          },
          onError: (errors) => {
            const errorMessages = Object.values(errors).flat().join(" ");
            this.showToast(`${errorMessages}`, 'error');
            this.deleteDialog = false;
          }
        });
      }
    }
  }
</script>