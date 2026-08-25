<template>
  <JobStructureTabs v-model:activeTab="activeTab" />

  <v-card class="pa-6 rounded-lg shadow-sm">
    <v-card-title class="mb-2">Edit Designation</v-card-title>
    <v-divider class="mb-6"></v-divider>

    <v-form ref="form" @submit.prevent="submitForm" validate-on="submit lazy">
      <v-row align="center" class="mb-4" dense>
        <v-col cols="12">
          <v-row dense>
            <!-- Designation Name -->
            <v-col cols="12" md="6">
              <v-text-field
                label="Designation"
                density="compact"
                variant="outlined"
                v-model="v$.formData.name.$model"
                :error-messages="
                  v$.formData.name.$errors.map((e) => e.$message)
                "
              />
            </v-col>

            <v-col cols="12" md="6">
              <v-text-field
                label="Operating Unit"
                density="compact"
                variant="outlined"
                v-model="formData.operating_unit"
                :readonly="
                  $page.props.auth.roles[0] != 'superadmin' &&
                  $page.props.auth.roles[0] != 'hr_director'
                "
              />
            </v-col>
          </v-row>
        </v-col>
      </v-row>

      <v-divider class="my-6" />

      <!-- Actions -->
      <div class="d-flex justify-end">
        <ButtonMuted name="Cancel" @click="goToIndex" />
        <ButtonSuccess class="ml-2" type="submit" name="Save" />
      </div>
    </v-form>
  </v-card>

  <!-- <pre>{{ user }}</pre> -->
</template>

<script>
import SidebarLayout from "@/layouts/SidebarLayout.vue";
import Breadcrumbs from "@/components/Breadcrumbs.vue";
import PrimaryButton from "@/components/PrimaryButton.vue";
import JobStructureTabs from "@/components/JobStructureTabs.vue";
import { useForm } from "@inertiajs/vue3";
import ButtonSuccess from "@/components/ButtonSuccess.vue";
import ButtonMuted from "@/components/ButtonMuted.vue";
import useVuelidate from "@vuelidate/core";
import { required } from "@vuelidate/validators";

export default {
  layout: SidebarLayout,

  components: {
    Breadcrumbs,
    PrimaryButton,
    JobStructureTabs,
    ButtonSuccess,
    ButtonMuted,
  },

  props: {
    designation: Object,
  },

  data() {
    return {
      activeTab: "designation",
      // Initialize validation
      v$: useVuelidate(),

      formData: useForm({
        name: this.designation.name,
        operating_unit: this.designation.operating_unit.name,
      }),
    };
  },

  validations() {
    return {
      formData: {
        name: { required },
      },
    };
  },

  methods: {
    goToIndex() {
      this.$inertia.visit(route("hrmanagement.jobstructure.designation.index"));
    },

    submitForm() {
      this.v$.$touch();

      if (this.v$.$invalid) return;

      this.formData.put(
        route("hrmanagement.jobstructure.designation.update", {
          id: this.designation.id,
        }),
        {
          onSuccess: () => {
            this.showToast("Designation updated successfully!", "success");
            this.formData.reset();
          },
          onError: (errors) => {
            // Combine all error messages into one string
            const errorMessages = Object.values(errors).flat().join(" ");

            this.showToast(`${errorMessages}`, "error");
          },
        }
      );
    },
  },
};
</script>

<style>
</style>
