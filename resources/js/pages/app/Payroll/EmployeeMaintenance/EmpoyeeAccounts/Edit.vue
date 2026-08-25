<template>
  <PayrollEmployeeMaintenanceTabs :activeTab="activeTab" @update:activeTab="updateActiveTab" />

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

  <v-row>
    <v-col cols="12">
      <v-card>
        <v-card-text>
          <v-card-title>
            Edit Account
          </v-card-title>
          <v-divider
            class="my-4"
          ></v-divider>
          <v-form @submit.prevent="handleSubmit()">
            <v-row>
              <v-col cols="12" md="6">
                <v-select
                  label="Account Type"
                  density="compact"
                  variant="outlined"
                  rounded="lg"
                  v-model="form.account_type_id"
                  :items="accountTypes"
                  item-title="name"
                  item-value="id"
                   :error-messages="v$.form.account_type_id.$errors.map(e => e.$message)"
                ></v-select>
              </v-col>
              <v-col cols="12" md="6">
                <v-text-field
                  label="Account Number"
                  density="compact"
                  variant="outlined"
                  rounded="lg"
                  v-model="form.account_number"
                  :error-messages="v$.form.account_number.$errors.map(e => e.$message)"
                ></v-text-field>
              </v-col>
              <v-col cols="12">
                <div class="d-flex align-center justify-end">
                  <ButtonMuted name="Cancel" @click="goToIndex()" ></ButtonMuted>
                  <ButtonSuccess name="Save" type="submit" class="ml-4" ></ButtonSuccess>
                </div>
              </v-col>
            </v-row>
          </v-form>
        </v-card-text>
      </v-card>
    </v-col>
  </v-row>

</template>
<script>
  import Layout from '@/layouts/SidebarLayout.vue'
  import PayrollEmployeeMaintenanceTabs from '@/components/Payroll/PayrollEmployeeMaintenanceTabs.vue'
  import { useForm } from '@inertiajs/vue3'
  import useVuelidate from '@vuelidate/core'
  import { required, helpers } from '@vuelidate/validators'
  import ButtonSuccess from '@/components/ButtonSuccess.vue'
  import ButtonMuted from '@/components/ButtonMuted.vue'
  export default {
    layout: Layout,
    components: {
      PayrollEmployeeMaintenanceTabs,
      ButtonSuccess,
      ButtonMuted,
    },
    props: {
      errors: Object,
      employee: Object,
      accountTypes: Object,
      employeeAccount: Object,
    },
    data(){
      return {
       activeTab: 'accounts',
       v$: useVuelidate(),
       createAgainDialog: false,
       loading: false,
       form: useForm({
          employee_number: this.employee.id,
          account_type_id: this.employeeAccount.account_type_id,
          account_number: this.employeeAccount.account_number,
        })
      }
    },
    validations(){
      return{ 
        form: {
          account_type_id: {
            required: helpers.withMessage('Account type is required', required),
          },
          account_number: {
            required: helpers.withMessage('Account number is required', required),
          },
        },
      }
    },
    methods: {
      
      goToIndex(){
        this.$inertia.visit(route('payroll.employee.maintenance.accounts.view',{id:this.employee.id}));
      },

      handleSubmit(){
        this.form.put(route('payroll.employee.maintenance.accounts.update',{id:this.employeeAccount.id}),{
          preserveScroll: true,
          preserveState: true,
          onSuccess: () => {
            this.showToast('Account update successfully', 'success');
            this.showCreateAgainDialog();
          },
          onError: () => {
            const errorMessages = Object.values(errors).flat().join(" ");
            this.showToast(`${errorMessages}`, "error");
          },
        });
      }
    }
  }
</script>