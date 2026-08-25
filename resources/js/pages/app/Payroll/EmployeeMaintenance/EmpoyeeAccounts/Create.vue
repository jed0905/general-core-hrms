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
            Add Account
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
  <!-- Create Again Confirmation Dialog -->
  <v-dialog v-model="createAgainDialog" max-width="500">
    <v-card class="pa-4 rounded-lg">
      <v-card-title
        class="d-flex align-center justify-center text-h5 font-weight-bold mb-4"
      >
        <v-icon color="primary" size="large" class="mr-2">
          mdi-plus-circle
        </v-icon>
        Create Another Account?
      </v-card-title>
      <v-divider class="mb-4"></v-divider>
      <v-card-text class="text-body-1 text-center">
        <p class="mb-2">Account has been created successfully!</p>
        <p class="text-caption text-medium-emphasis">
          Would you like to create another account for this employee?
        </p>
      </v-card-text>
      <v-card-actions class="d-flex justify-end gap-2 pa-4">
        <v-btn
          color="grey-darken-1"
          variant="outlined"
          @click="handleNoCreateAgain"
          :loading="loading"
          min-width="120"
        >
          No, Go Back
        </v-btn>
        <v-btn
          color="primary"
          variant="elevated"
          @click="handleYesCreateAgain"
          :loading="loading"
          min-width="120"
        >
          Yes, Create Another
        </v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>
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
    },
    data(){
      return {
       activeTab: 'accounts',
       v$: useVuelidate(),
       createAgainDialog: false,
       loading: false,
       form: useForm({
          employee_number: this.employee.id,
          account_type_id: '',
          account_number: '',
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

      handleYesCreateAgain() {
        // Reset form and close dialog to allow creating another account
        this.form.reset();
        this.createAgainDialog = false;
        this.loading = false;
        // Optional: Show success message
        // this.showToast('Form reset for new account creation', 'success');
      },

      handleNoCreateAgain() {
        // Navigate back to the view page
        this.createAgainDialog = false;
        this.goToIndex();
      },

      showCreateAgainDialog() {
        // Method to show the dialog after successful account creation
        this.createAgainDialog = true;
      },

      handleSubmit(){
        this.form.post(route('payroll.employee.maintenance.accounts.store'),{
          preserveScroll: true,
          preserveState: true,
          onSuccess: () => {
            this.showToast('Account created successfully', 'success');
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