<template>
  <JobStructureTabs v-model:activeTab="activeTab" />

  <v-card class="pa-6 rounded-lg shadow-sm">
    <v-card-title class="mb-2">Add Designation</v-card-title>
    <v-divider class="mb-6" style="border: 1px solid black;"></v-divider>

    <v-form ref="form" @submit.prevent="submitForm()" validate-on="submit lazy">
      <v-row align="center" class="mb-4" dense>
        <v-col cols="12">
          <v-row dense>
            <!-- Designation -->
            <v-col cols="12" md="6">
              <v-text-field
                label="Designation"
                density="compact"
                variant="outlined"
                rounded="lg"
                v-model="v$.formData.name.$model"
                :error-messages="
                  v$.formData.name.$errors.map((e) => e.$message)
                "
              />
            </v-col>

            <!-- Operating Unit -->
            <v-col
              cols="12"
              md="5"
              v-if="
                $page.props.auth.roles[0] == 'superadmin' ||
                $page.props.auth.roles[0] == 'hr_director'
              "
            >
              <v-select
                label="Operating Unit"
                :items="operating_units"
                item-title="name"
                item-value="id"
                density="compact"
                variant="outlined"
                rounded="lg"
                v-model="v$.formData.operating_unit_id.$model"
                :error-messages="
                  v$.formData.operating_unit_id.$errors.map((e) => e.$message)
                "
              />
            </v-col>
            <v-col cols="12" md="1" >
              <v-switch
                v-model="formData.is_vsl"
                label="VSL"
                color="starbucks-green"
                inset
                hide-details
              ></v-switch>
            </v-col>
          </v-row>
        </v-col>
      </v-row>

      <v-divider class="my-6" style="border: 1px solid black;" />

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
      activeTab: "designation",
      // Initialize validation
      v$: useVuelidate(),

      search: "",
      formData: useForm({
        name: null,
        operating_unit_id: null,
        is_vsl: false,
      }),
    };
  },

  mounted() {
    if (
      this.$page.props.auth.roles[0] !== "superadmin" &&
      this.$page.props.auth.roles[0] !== "hr_director"
    ) {
      this.formData.operating_unit_id = this.$page.props.auth.user.employee.operating_unit_id;
      // console.log(this.$page.props.auth.user.employee.operating_unit_id);
    }
  },

  validations() {
    return {
      formData: {
        name: { required },
        operating_unit_id: { required },
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

      this.formData.post(route("hrmanagement.jobstructure.designation.store"), {
        onSuccess: () => {
          this.showToast("Designation created successfully!", "success");
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
