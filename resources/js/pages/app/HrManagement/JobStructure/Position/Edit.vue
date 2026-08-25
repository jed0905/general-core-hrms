<template>
  <JobStructureTabs v-model:activeTab="activeTab" />

  <v-card class="pa-6 rounded-lg shadow-sm">
    <v-card-title class="mb-2">Edit Position</v-card-title>
    <v-divider class="mb-6" style="border: 1px solid black;"></v-divider>

    <v-form ref="form" @submit.prevent="submitForm" validate-on="submit lazy">
      <v-row align="center" class="mb-4" dense>
        <v-col cols="12">
          <v-row dense>
            <!-- Operating Unit -->
            <v-col cols="12" md="3">
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
                :readonly="
                  $page.props.auth.roles[0] === 'superadmin' ||
                  $page.props.auth.roles[0] === 'hr_director'
                    ? false
                    : true
                "
              />
            </v-col>
            <!-- Position -->
            <v-col cols="12" md="3">
              <v-autocomplete
                v-model="v$.formData.government_positions_id.$model"
                label="Position (Type to search)"
                density="compact"
                variant="outlined"
                rounded="lg"
                :items="filteredPositions"
                item-title="name"
                item-value="id"
                :search="search"
                :no-filter="true"
                @update:search="onSearch"
                :hide-no-data="search.length < 2"
                :error-messages="
                  v$.formData.government_positions_id?.$errors.map(
                    (e) => e.$message
                  )
                "
                return-object
              />
            </v-col>

            <!-- Plantilla Item Number -->
            <v-col cols="12" md="3">
              <v-text-field
                label="Plantilla Item Number"
                density="compact"
                variant="outlined"
                rounded="lg"
                v-model="formData.plantilla_item_number"
              />
            </v-col>

            <!-- Salary Grade -->
            <v-col cols="12" md="3">
              <v-select
                label="Salary Grade"
                rounded="lg"
                :items="salary_grades"
                item-title="salary_grade"
                item-value="id"
                density="compact"
                variant="outlined"
                v-model="formData.salary_grade_id"
              />
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

  <!-- <pre>{{ positions }}</pre> -->
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
    positions: Object,
    salary_grades: Object,
    operating_units: Object,
    government_positions: Object,
  },

  data() {
    return {
      activeTab: "position",
      // Initialize validation
      v$: useVuelidate(),

      search: "",
      formData: useForm({
        operating_unit_id:
          this.$page.props.auth.roles[0] === "superadmin" ||
          this.$page.props.auth.roles[0] === "hr_director"
            ? this.positions.operating_unit_id
            : this.operating_units[0],
        government_positions_id: this.government_positions?.find(
          (gp) => gp.id === this.positions.government_position.id
        ) || null,
        plantilla_item_number: this.positions.plantilla_item_number,
        salary_grade_id: this.positions.salary_grade_id,
      }),
    };
  },

  computed: {
    filteredPositions() {
      if (this.search.length < 2) return [];
      return this.government_positions.filter((position) =>
        position.name.toLowerCase().includes(this.search.toLowerCase())
      );
    },
  },

  validations() {
    return {
      formData: {
        government_positions_id: { required },
        operating_unit_id: { required },
      },
    };
  },



  methods: {
    goToIndex() {
      this.$inertia.visit(route("hrmanagement.jobstructure.position.index"));
    },

    onSearch(val) {
      this.search = val;
    },

   

    submitForm() {
      this.v$.$touch();

      if (this.v$.$invalid) return;

      const payload = {
        ...this.formData,
        government_positions_id:
          this.formData.government_positions_id?.id ?? null,
        operating_unit_id: this.$page.props.auth.roles[0] === "superadmin" ||
          this.$page.props.auth.roles[0] === "hr_director"
          ? this.formData.operating_unit_id
          : this.operating_units[0]?.id,
      };

      this.$inertia.put(
        route("hrmanagement.jobstructure.position.update", {
          id: this.positions.id,
        }),
        payload,
        {
          onSuccess: () => {
            this.showToast("Position updated successfully!", "success");
            this.formData.reset();
          },
          onError: (errors) => {
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
