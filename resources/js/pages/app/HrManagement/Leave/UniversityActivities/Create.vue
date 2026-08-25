<template>
  <LeaveManagementTabs v-model:activeTab="activeTab" />
  <v-row>
    <v-col cols="12">
      <v-card>

        <v-card-text>
          <div class="v-card-title">
            Add University Activity
          </div>
          <v-divider class="my-4" style="border: 1px solid black;"></v-divider>
          <v-form @submit.prevent="handleSubmit()">
            <v-row>
              <v-col cols="12" md="6">
                <v-text-field
                  label="Title"
                  variant="outlined"
                  rounded="lg"
                  density="compact"
                  v-model="form.title"
                  :error-messages="v$.form.title.$errors.map(e => e.$message)"
                ></v-text-field>
              </v-col>


              <v-col cols="12" md="6">
                <v-select
                  label="Type"
                  variant="outlined"
                  rounded="lg"
                  density="compact"
                  :items="type"
                  item-title="title"
                  item-value="value"
                  v-model="form.type"
                  :error-messages="v$.form.type.$errors.map(e => e.$message)"
                ></v-select>
              </v-col>



              <v-col cols="12"  md="6">
                <v-text-field
                  label="Start Date"
                  variant="outlined"
                  rounded="lg"
                  density="compact"
                  type="date"
                  v-model="form.start_at"
                  :error-messages="v$.form.start_at.$errors.map(e => e.$message)"
                ></v-text-field>
              </v-col>
              <v-col cols="12"  md="6">
                <v-text-field
                  label="End Date"
                  variant="outlined"
                  rounded="lg"
                  density="compact"
                  type="date"
                  :min="form.start_at"
                  v-model="form.end_at"
                  :error-messages="v$.form.end_at.$errors.map(e => e.$message)"
                ></v-text-field>
              </v-col>
              <v-col cols="12" md="6">
                <v-autocomplete
                  label="Operating Unit"
                  variant="outlined"
                  rounded="lg"
                  density="compact"
                  :items="operatingUnits"
                  item-title="name"
                  item-value="id"
                  v-model="form.operating_unit_ids"
                  :error-messages="v$.form.operating_unit_ids.$errors.map(e => e.$message)"
                  multiple
                  chips
                  closable-chips
                ></v-autocomplete>
              </v-col>
              <v-col cols="12" md="6">
                <v-text-field
                  label="Document Control Number"
                  variant="outlined"
                  rounded="lg"
                  density="compact"
                  v-model="form.document_control_number"
                  :error-messages="v$.form.document_control_number.$errors.map(e => e.$message)"
                ></v-text-field>
              </v-col>
              <v-col cols="12">
                <v-textarea
                  label="Description"
                  variant="outlined"
                  rounded="lg"
                  density="compact"
                  v-model="form.description"
                  :error-messages="v$.form.description.$errors.map(e => e.$message)"
                ></v-textarea>
              </v-col>

              <v-col cols="12">
                <div class="d-flex align-center justify-end">
                  <ButtonMuted name="Cancel" @click="goToIndex()" />
                  <ButtonSuccess name="Save" type="submit" class="ml-2" />
                </div>
              </v-col>
            </v-row>
          </v-form>
        </v-card-text>
      </v-card>
    </v-col>
  </v-row>

  <AgainDialog
    v-model="showAgainDialog"
    :message="againDialogMessage"
    @confirm="this.showAgainDialog = false"
    @cancel="goToIndex()"
  />

</template>
<script>
  import SidebarLayout from '@/layouts/SidebarLayout.vue'
  import LeaveManagementTabs from '@/components/LeaveManagementTabs.vue'
  import ButtonSuccess from '@/components/ButtonSuccess.vue'
  import ButtonMuted from '@/components/ButtonMuted.vue'
  import { useForm } from '@inertiajs/vue3'
  import useVuelidate from '@vuelidate/core'
  import { required,minLength, helpers } from '@vuelidate/validators'
  import AgainDialog from '@/components/DialogAgain.vue';

  export default {
    layout:SidebarLayout,
    components:{
      LeaveManagementTabs,
      ButtonSuccess,
      ButtonMuted,
      AgainDialog,
    },
    props: {
      errors: Object,
      operatingUnits: Object,
    },
    data(){
      return {
        activeTab: 'configure',
        showAgainDialog: false,
        againDialogMessage: 'Do you want to create another university activity?',
        type: [
          {
            'title': 'Event',
            'value': 'event',
          },
          {
            'title': 'Suspension',
            'value': 'suspension',
          },
          {
            'title': 'Work from Home',
            'value': 'work_from_home',
          },
          {
            'title': 'Advisory',
            'value': 'advisory',
          }
        ],
        v$: useVuelidate(),
        form: useForm({
          title: null,
          type: null,
          description: null,
          start_at: null,
          end_at: null,
          operating_unit_ids: [],
          document_control_number: null,
        })
      }
    },

    validations(){
      return {
        form: {
          title: { required },
          type: { required },
          description: { minLength: minLength(0) },
          start_at: { required },
          end_at: {
            required,
            validDate: helpers.withMessage('Please select a valid date', (value) => {
              if (!value) return true;
              const date = new Date(value);
              return date instanceof Date && !isNaN(date);
            }),
            afterStart: helpers.withMessage('End date cannot be before start date', function (value) {
              if (!value || !this.form.start_at) return true;
              return new Date(value) >= new Date(this.form.start_at);
            })
          },
          operating_unit_ids: {
            // Optional field, no validation needed
          },
          document_control_number: {
            required,
            minLength: minLength(5),
          },
        }
      }
    },

    methods:{
      goToIndex(){
        this.$inertia.visit(route('hrmanagement.universityActivities.index'));
      },
      handleSubmit(){
        this.v$.form.$validate();
        if(!this.v$.form.$invalid){
          this.form.post(route('hrmanagement.universityActivities.store'),{
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => {
              this.showToast('University activity created successfully', 'success');
              this.form.operating_unit_ids = [];
              this.form.document_control_number = null;
              this.form.description = null;
              this.form.start_at = null;
              this.form.end_at = null;
              this.form.title = null;
              this.showAgainDialog = true;
            },
            onError: (errors) => {
              const errorMessages = Object.values(errors).flat().join(" ");
              this.showToast(`${errorMessages}`, "error");
            }
          });
        }
      },

    }

  }
</script>
