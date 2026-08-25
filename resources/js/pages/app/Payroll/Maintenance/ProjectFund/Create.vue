<template>
  <PayrollMaintenanceTabs :activeTab="activeTab" @update:activeTab="updateActiveTab" />
  <v-row>
    <v-col cols="12">
      <v-card>
        
        <v-card-text>
          <v-card-title>
            Add Project Fund
          </v-card-title>
          <v-divider
            class="my-4"
          ></v-divider>
          <v-form @submit.prevent="handleSubmit()">
            <v-row>
              <v-col cols="12" md="6">
                <v-text-field
                  label="Project Fund Name"
                  density="compact"
                  variant="outlined"
                  rounded="lg"
                  v-model="form.name"
                  :error-messages="v$.form.name.$errors.map(e => e.$message)"
                ></v-text-field>
              </v-col>
              <v-col cols="12" md="6">
                <v-text-field
                  label="Allocation"
                  density="compact"
                  variant="outlined"
                  rounded="lg"
                  v-model="form.allocation"
                  :error-messages="v$.form.allocation.$errors.map(e => e.$message)"
                ></v-text-field>
              </v-col>
              <v-col cols="12" md="6">
                <v-select
                  label="Employee Status"
                  density="compact"
                  variant="outlined"
                  rounded="lg"
                  v-model="form.employee_status"
                  :items="jobStatus"
                  item-title="name"
                  item-value="id"
                  :error-messages="v$.form.employee_status.$errors.map(e => e.$message)"
                ></v-select>
              </v-col>
              <v-col cols="12" md="6">
                <v-select
                  label="Employee Type"
                  density="compact"
                  variant="outlined"
                  rounded="lg"
                  v-model="form.employee_type"
                  :items="employeeType"
                  :error-messages="v$.form.employee_type.$errors.map(e => e.$message)"
                ></v-select>
              </v-col>
              <v-col cols="12" md="6">
                <v-select
                  label="Operating Unit"
                  density="compact"
                  variant="outlined"
                  rounded="lg"
                  v-model="form.operating_unit_id"
                  :items="operatingUnits"
                  item-title="name"
                  item-value="id"
                  :error-messages="v$.form.operating_unit_id.$errors.map(e => e.$message)"
                ></v-select>
              </v-col>

              <v-col cols="12">
                <p class="text-grey-darken-1">
                  Do not select an operating unit if you want to create a universal prject that will be available to all operating units.
                </p>
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
  import Layout from '@/layouts/SidebarLayout.vue';
  import PayrollMaintenanceTabs from '@/components/Payroll/PayrollMaintenanceTabs.vue';
  import ButtonSuccess from '@/components/ButtonSuccess.vue';
  import ButtonMuted from '@/components/ButtonMuted.vue';
  import { useForm } from '@inertiajs/vue3';
  import useVuelidate from '@vuelidate/core';
  import { required, helpers, minLength } from '@vuelidate/validators';
  import { employeeType } from '@/utils/EmployeeType';

  export default {
    layout: Layout,
    components: {
      PayrollMaintenanceTabs,
      ButtonSuccess,
      ButtonMuted,
    },
    props: {
      errors: Object,
      jobStatus: Object,
      operatingUnits: Object,
    },
    data() {
      return {
        activeTab: 'funds',
        form: useForm({
          name: '',
          allocation: '',
          employee_status: '',
          employee_type: '',
          operating_unit_id: '',
        }),
        v$: useVuelidate(),
      }
    },
    computed: {
      employeeType(){
        return employeeType;
      },
    },
    validations(){
      return {
        form: {
          name: {
            required: helpers.withMessage('Name is required', required),
          },
          allocation: { minLength: minLength(0)},
          employee_status: {
            required: helpers.withMessage('Employee status is required', required),
          },
          employee_type: {
            required: helpers.withMessage('Employee type is required', required),
          },
          operating_unit_id: {
            minLength: minLength(0),
          },
        },
      }
    },
    methods: {
      handleSubmit(){
        this.v$.$validate();
        if(this.v$.$invalid){
          return;
        }
        this.form.post(route('payroll.maintenance.projectfund.store'),{
          onSuccess: () => {
            this.showToast('Project fund created successfully', 'success');
          },
          onError: (errors) => {
            const errorMessages = Object.values(errors).flat().join(" ");
            this.showToast(`${errorMessages}`, "error");
          },
        });
      },

      goToIndex(){
        this.$inertia.visit(route('payroll.maintenance.projectfund.index'));
      },
    }
  }
</script>