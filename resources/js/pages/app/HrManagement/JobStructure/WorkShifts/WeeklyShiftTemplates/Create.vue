<template>
  <JobStructureTabs :activeTab="activeTab" />
  <v-card>
    <v-card-text>
      <div class="v-card-title text-h6 font-weight-medium">
        Add Weekly Shift Template
      </div>
      <v-divider
        class="my-4"
        style="border: 1px solid black;"
      ></v-divider>
      <v-form @submit.prevent="handleSubmit()">
        <v-row>
          <v-col cols="12" md="6">
            <v-text-field
              label="Name"
              variant="outlined"
              density="compact"
              rounded="lg"
              v-model="form.name"
              :error-messages="v$.form.name.$errors.map(e => e.$message)"
            ></v-text-field>
          </v-col>
          <v-col cols="12" md="6">
            <v-textarea
              label="Description"
              variant="outlined"
              density="compact"
              rounded="lg"
              v-model="form.description"
              :error-messages="v$.form.description.$errors.map(e => e.$message)"
            ></v-textarea>
          </v-col>
        </v-row>
        <v-divider
          class="my-4"
          style="border: 1px solid black;"
        ></v-divider>
        <div class="d-flex align-center justify-end">
          <ButtonMuted name="Cancel" @click="goToIndex" />
          <ButtonSuccess class="ml-2" type="submit" name="Save" />
        </div>
      </v-form>
    </v-card-text>
  </v-card>
</template>
<script>
  import SidebarLayout from '@/layouts/SidebarLayout.vue'
  import JobStructureTabs from '@/components/JobStructureTabs.vue'
  import ButtonSuccess from '@/components/ButtonSuccess.vue'
  import ButtonMuted from '@/components/ButtonMuted.vue'
  import { useForm } from '@inertiajs/vue3'
  import useVuelidate from '@vuelidate/core'
  import { required, minLength, maxLength } from '@vuelidate/validators'

  export default {
    layout: SidebarLayout,
    components: {
      JobStructureTabs,
      ButtonSuccess,
      ButtonMuted,
    },
    props: {
      errors: Object,
    },
    data() {
      return {
        activeTab: "workShift",
        v$: useVuelidate(),
        form: useForm({
          'name' : null,
          'description' : null,
        })
      }
    },
    validations() {
      return {
        form: {
          name: { required },
          description: { required },
        }
      }
    },
    methods: {
      goToIndex() {
        this.$inertia.visit(route('hrmanagement.jobstructure.weeklyShiftTemplates.index'));
      },
      handleSubmit() {
        this.v$.$touch();
        if(!this.v$.$invalid){
          this.form.post(route('hrmanagement.jobstructure.weeklyShiftTemplates.store'), {
            onSuccess: () => {
              this.showToast('Weekly shift template created successfully', 'success');
      
              this.form.reset();
              this.form.name = null;
              this.form.description = null;
            },
            onError: (errors) => {
              const errorMessages = Object.values(errors).flat().join(" ");
      
              this.showToast(`${errorMessages}`, "error");
            }
          });
        }
      }
    }
  }
</script>