<template>
  <JobStructureTabs v-model:activeTab="activeTab" />

  <v-card class="pa-6 rounded-lg shadow-sm">
    <v-card-title class="mb-2">Add Job Status</v-card-title>
    <v-divider class="mb-6"></v-divider>

    <v-form ref="form" @submit.prevent="submitForm" validate-on="submit lazy">
      <v-row align="center" class="mb-4" dense>
        <v-col cols="12">
          <v-row dense>
            <!-- Plantilla Item Number -->
            <v-col cols="12">
              <v-text-field
                label="Name"
                density="compact"
                variant="outlined"
                v-model="formData.name"
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
  },

  data() {
    return {
      activeTab: "jobStatus",
      // Initialize validation
      v$: useVuelidate(),

      search: "",
      formData: useForm({
        name: null,
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
      this.$inertia.visit(route("hrmanagement.jobstructure.jobstatus.index"));
    },

    submitForm() {
      this.v$.$touch();

      if (this.v$.$invalid) return;

      // console.log("Form Data:", this.formData);

      this.formData.post(route("hrmanagement.jobstructure.jobstatus.store"), {
        onSuccess: () => {
          this.showToast("Job Status created successfully!", "success");
          this.formData.reset();
        },
        onError: (errors) => {
          const errorMessages = Object.values(errors).flat().join(" ");

          this.showToast(`${errorMessages}`, "error");
        },
      });
    },
  },
};
</script>

<style>
</style>
