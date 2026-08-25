<template>
  <LeaveManagementTabs v-model:activeTab="activeTab"  />
  <v-row>
    <v-col cols="12">
      <v-card>
        <v-card-text>
          <v-row>
            <v-col cols="12" md="5">
              <div class="text-subtitle-1 font-weight-bold mb-2">Employee Information</div>
              <div class="mb-1">
                <span class="font-weight-medium">Name:</span> 
                {{ employeeLeaveScheduler.employee.personal_information.firstname }} {{ employeeLeaveScheduler.employee.personal_information.middlename ?? '' }} {{ employeeLeaveScheduler.employee.personal_information.lastname }} {{ employeeLeaveScheduler.employee.personal_information.suffix ?? '' }}
              </div>
              <div class="mb-1">
                <span class="font-weight-medium">Employee ID:</span>
                {{ employeeLeaveScheduler.employee.employee_number }}
              </div>
              <div>
                <span class="font-weight-medium">Position:</span>
                {{ employeeLeaveScheduler.employee.position?.government_position.name }}
              </div>
            </v-col>
            <v-col cols="12" md="5">
              <div class="text-subtitle-1 font-weight-bold mb-2">&nbsp;</div>
              <div class="mb-1">
                <span class="font-weight-medium">Department:</span>
                {{ employeeLeaveScheduler.employee.department?.name }}
              </div>
              <div>
                <span class="font-weight-medium">Operating Unit:</span>
                {{ employeeLeaveScheduler.employee.operating_unit?.name }}
              </div>
            </v-col>
          </v-row>
        </v-card-text>
      </v-card>
    </v-col>
    <v-col cols="12">
      <v-card>
        <v-card-text>
          <div class="v-card-title text-h6">
            Edit Leave Scheduler
          </div>
          <v-divider 
            class="my-4"
            style="border: 1px solid black;"
          ></v-divider>
          <v-form @submit.prevent="handleSubmit()">
            <v-row>
              <v-col cols="12" md="6">
                <v-select 
                  :items="days"
                  v-model="form.run_day" 
                  label="Run Day" 
                  variant="outlined"
                  density="compact"
                  rounded="lg"
                  type="number" 
                  min="1" 
                  max="31" 
                  required
                ></v-select>
              </v-col>
              <v-col cols="12" md="6">
                <v-text-field
                  label="Credits to Add"
                  variant="outlined"
                  density="compact"
                  rounded="lg"
                  type="number"
                  v-model="form.credits_to_add"
                  required
                ></v-text-field>
              </v-col>
            </v-row>
            <div class="d-flex align-center justify-end">
              <ButtonMuted name="Cancel" @click="goToIndex" />
              <ButtonSuccess class="ml-2" type="submit" name="Save" />
            </div>
          </v-form>
        </v-card-text>
      </v-card>
    </v-col>
  </v-row>
  <!-- <pre>{{ employeeLeaveScheduler }}</pre> -->
</template>
<script>
  import SidebarLayout from '@/layouts/SidebarLayout.vue';
  import LeaveManagementTabs from '@/components/LeaveManagementTabs.vue';
  import { useForm } from '@inertiajs/vue3';
  import { useVuelidate } from '@vuelidate/core';
  import { required, minValue, maxValue } from '@vuelidate/validators';

  import ButtonMuted from '@/components/ButtonMuted.vue';
  import ButtonSuccess from '@/components/ButtonSuccess.vue';

  export default {
    layout: SidebarLayout,
    components: {
      LeaveManagementTabs,
      ButtonMuted,
      ButtonSuccess,
    },
    props: {
      errors: Object,
      employeeLeaveScheduler: Object,
    },
    data() {
      return {
        activeTab: 'configure',
        days: ['1','2','3','4','5','6','7','8','9','10','11','12','13','14','15','16','17','18','19','20','21','22','23','24','25','26','27','28','29','30','31'],
        form: useForm({
          run_day: this.employeeLeaveScheduler.run_day,
          credits_to_add: this.employeeLeaveScheduler.credits_to_add,
        }),
        v$: useVuelidate(),
      }
    },
    methods: {
      handleSubmit(){
        this.form.put(route('hrmanagement.employeeLeaveScheduler.update', this.employeeLeaveScheduler.id),{
          preserveScroll:true,
          preserveState:true,
          onSuccess: () => {
            this.showToast('Leave scheduler updated successfully', 'success');
          },
          onError: (errors) => {
            const errorMessages = Object.values(errors).flat().join(" ");
            this.showToast(`${errorMessages}`, "error");
          },
        });
      },
      goToIndex(){
        this.$inertia.visit(route('hrmanagement.employeeLeaveScheduler.index'));
      }
    }
  }
</script>