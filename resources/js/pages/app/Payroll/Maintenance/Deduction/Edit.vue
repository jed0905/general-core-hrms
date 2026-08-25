<template>
  <PayrollMaintenanceTabs :activeTab="activeTab" @update:activeTab="updateActiveTab" />
  <v-row>
    <v-col cols="12">
      <v-card>
        
        <v-card-text>
          <v-card-title>
            Edit Deduction
          </v-card-title>
          <v-divider
            class="my-4"
          ></v-divider>
          <v-form @submit.prevent="handleSubmit()">
            <v-row>
              <v-col cols="12" md="6">
                <v-text-field
                  label="Deduction Name"
                  density="compact"
                  variant="outlined"
                  rounded="lg"
                  v-model="form.name"
                  :error-messages="v$.form.name.$errors.map(e => e.$message)"
                ></v-text-field>
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
                <p style="color: gray; font-style: italic;" >Note: If no operating unit is selected, the deduction will be universal and available to all operating units. If an operating unit is selected, the deduction will only be available for that specific operating unit.</p>
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

  export default {
    layout: Layout,
    components: {
      PayrollMaintenanceTabs,
      ButtonSuccess,
      ButtonMuted,
    },
    props: {
      errors: Object,
      payrollDeduction: Object,
      operatingUnits: Object,
    },
    data() {
      return {
        activeTab: 'deductions',
        form: useForm({
          name: this.payrollDeduction.name,
          operating_unit_id: this.payrollDeduction.operating_unit_id,
        }),
        v$: useVuelidate(),
      }
    },
    validations(){
      return {
        form: {
          name: {
            required: helpers.withMessage('Name is required', required),
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
        this.form.put(route('payroll.maintenance.deduction.update', this.payrollDeduction.id),{
          onSuccess: () => {
            this.showToast('Deduction updated successfully', 'success');
          },
          onError: () => {
            const errorMessages = Object.values(errors).flat().join(" ");
            this.showToast(`${errorMessages}`, "error");
          },
        });
      },

      goToIndex(){
        this.$inertia.visit(route('payroll.maintenance.deduction.index'));
      },
    }
  }
</script>