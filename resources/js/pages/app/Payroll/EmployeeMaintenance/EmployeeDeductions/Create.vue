<template>
  <PayrollEmployeeMaintenanceTabs :activeTab="activeTab" @update:activeTab="updateActiveTab" />
  <v-row>
    <v-col cols="12">
      <v-card>
        
        <v-card-text>
          <div class="v-card-title">
            Add Employee Deduction
          </div>
          <v-divider
            class="my-4"
          ></v-divider>
          <v-form @submit.prevent="handleSubmit()">
            <v-row>
              <v-col cols="12" md="6">
                <v-autocomplete
                  label="Deduction"
                  density="compact"
                  variant="outlined"
                  rounded="lg"
                  :items="payrollDeductions"
                  item-title="name"
                  item-value="id"
                  v-model="form.deduction_id"
                  :error-messages="v$.form.deduction_id.$errors.map(e => e.$message)"
                ></v-autocomplete>
              </v-col>
              <v-col cols="12" md="6">
                <v-text-field
                  label="Amount"
                  density="compact"
                  variant="outlined"
                  rounded="lg"
                  placeholder="0.00"
                  v-model="form.amount"
                  :error-messages="v$.form.amount.$errors.map(e => e.$message)"
                ></v-text-field>
              </v-col>
              <v-col cols="12" md="6">
                <v-text-field
                  label="Date From"
                  density="compact"
                  variant="outlined"
                  rounded="lg"
                  type="date"
                  v-model="form.date_start"
                  :error-messages="v$.form.date_start.$errors.map(e => e.$message)"
                ></v-text-field>
              </v-col>
              <v-col cols="12" md="6">
                <v-text-field
                  label="Date To"
                  density="compact"
                  variant="outlined"
                  rounded="lg"
                  type="date"
                  v-model="form.date_end"
                  :error-messages="v$.form.date_end.$errors.map(e => e.$message)"
                ></v-text-field>
              </v-col>
              <v-col cols="12" md="6">
                <v-select
                  label="Deduction Period"
                  density="compact"
                  variant="outlined"
                  rounded="lg"
                  :items="deductionPeriod"
                  v-model="form.deduction_period"
                  :error-messages="v$.form.deduction_period.$errors.map(e => e.$message)"
                ></v-select>
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
  import ButtonSuccess from '@/components/ButtonSuccess.vue'
  import ButtonMuted from '@/components/ButtonMuted.vue'
  import { deductionPeriod } from '@/utils/Period'
  import { useForm } from '@inertiajs/vue3'
  import useVuelidate from '@vuelidate/core'
  import { required, helpers } from '@vuelidate/validators'

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
      payrollDeductions: Object,
    },
    data(){
      return {
        activeTab: 'deductions',
        deductionPeriod,
        v$: useVuelidate(),
        form: useForm({
          employee_number: this.employee.id,
          deduction_id: '',
          amount: '',
          date_start: '',
          date_end: '',
          deduction_period: '',
        }),
      }
    },
    validations(){
      return {
        form: {
          employee_number: { required },
          deduction_id: { required: helpers.withMessage('Deduction is required', required) },
          amount: { required: helpers.withMessage('Amount is required', required) },
          date_start: { required: helpers.withMessage('Date From is required', required) },
          date_end: { required: helpers.withMessage('Date To is required', required) },
          deduction_period: { required: helpers.withMessage('Deduction Period is required', required) },
        },
      }
    },
    methods: {
      goToIndex(){
        this.$inertia.visit(route('payroll.employee.maintenance.deductions.view',{id:this.employee.id}));
      },

      handleSubmit(){
        this.v$.$validate();
        if(!this.v$.$invalid){
          this.form.post(route('payroll.employee.maintenance.deductions.store'),{
            onSuccess: () => {
              this.showToast('Deduction created successfully', 'success');
            },
            onError: (errors) => {
              const errorMessages = Object.values(errors).flat().join(" ");
              this.showToast(`${errorMessages}`, "error");
            },
          });
        }
      }
    }
  }
</script>