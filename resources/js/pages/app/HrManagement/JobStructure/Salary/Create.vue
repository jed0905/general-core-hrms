<template>
  <JobStructureTabs v-model:activeTab="activeTab" />

  <v-card class="pa-6 rounded-lg shadow-sm">
    <v-card-title class="mb-2">Add Salary Schedule</v-card-title>
    <v-divider class="mb-6" style="border: 1px solid black"></v-divider>

    <v-form ref="form" @submit.prevent="submitForm()" validate-on="submit lazy">
      <v-row align="center" class="mb-4" dense>
        <v-col cols="12">
          <v-row dense>
            <!-- Name -->
            <v-col cols="12" md="3">
              <v-text-field
                label="Name"
                density="compact"
                variant="outlined"
                rounded="lg"
                v-model="v$.formData.name.$model"
                :error-messages="
                  v$.formData.name.$errors.map((e) => e.$message)
                "
              />
            </v-col>

            <!-- Law Reference -->
            <v-col cols="12" md="3">
              <v-text-field
                label="Law Reference"
                density="compact"
                variant="outlined"
                rounded="lg"
                v-model="formData.law_reference"
              />
            </v-col>

            <!-- Effective From -->
            <v-col cols="12" md="3">
              <v-text-field
                label="Effective From"
                density="compact"
                variant="outlined"
                rounded="lg"
                type="date"
                v-model="v$.formData.effective_from.$model"
                :error-messages="
                  v$.formData.effective_from.$errors.map((e) => e.$message)
                "
              />
            </v-col>

            <!-- Effective To (can select present)-->
            <v-col cols="12" md="3">
              <v-text-field
                label="Effective To (leave blank if present)"
                density="compact"
                variant="outlined"
                rounded="lg"
                type="date"
                v-model="formData.effective_to"
              />
            </v-col>
          </v-row>
        </v-col>
      </v-row>

      <v-divider class="my-6" style="border: 1px solid black" />

      <!-- Actions -->
      <div class="d-flex justify-end">
        <ButtonMuted name="Cancel" @click="goToIndex" />
        <ButtonSuccess class="ml-2" type="submit" name="Save" />
      </div>
    </v-form>
  </v-card>

  <!-- <pre>{{ employees }}</pre> -->
</template>

<script>
import SidebarLayout from "@/layouts/SidebarLayout.vue";
import Breadcrumbs from "@/components/Breadcrumbs.vue";
import TableWrapper from "@/components/TableWrapper.vue";
import PrimaryButton from "@/components/PrimaryButton.vue";
import FilterWrapper from "@/components/FilterWrapper.vue";
import JobStructureTabs from "@/components/JobStructureTabs.vue";
import { useForm } from "@inertiajs/vue3";
import ButtonSuccess from "@/components/ButtonSuccess.vue";
import ButtonMuted from "@/components/ButtonMuted.vue";
import { useVuelidate } from "@vuelidate/core";
import {
  required,
  minLength,
  maxLength,
  sameAs,
  helpers,
} from "@vuelidate/validators";

export default {
  layout: SidebarLayout,

  components: {
    Breadcrumbs,
    PrimaryButton,
    TableWrapper,
    FilterWrapper,
    JobStructureTabs,
    ButtonSuccess,
    ButtonMuted,
  },

  props: {
    errors: Object,
    operating_units: Object,
  },

  data() {
    return {
      activeTab: "salary",
      // Initialize validation
      v$: useVuelidate(),

      search: "",
      formData: useForm({
        name: null,
        law_reference: null,
        effective_from: null,
        effective_to: null,
      }),
    };
  },

  validations() {
    return {
      formData: {
        name: { required },
        effective_from: { required },

      },
    };
  },

  methods: {
    goToIndex() {
      this.$inertia.visit(
        route("hrmanagement.jobstructure.salary.index")
      );
    },

    submitForm() {
      this.v$.$touch();

      if (this.v$.$invalid) return;

      this.formData.post(
        route("hrmanagement.jobstructure.salary.store"),
        {
          onSuccess: () => {
            this.showToast("Salary schedule created successfully.", "success");
            this.formData.reset();
            this.v$.$reset();
          },

          onError: () => {
            // Handle errors if needed
          },
        }
      );
    },
  },
};
</script>

