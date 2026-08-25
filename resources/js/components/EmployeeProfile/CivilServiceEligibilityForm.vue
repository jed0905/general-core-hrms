<template>
  <!-- Civil Service Eligibility Form -->
  <v-form @submit.prevent="submitForm()">
    <v-col cols="12">
      <v-card-title class="d-flex align-center justify-space-between">
        Career Service Eligibility
        <v-btn
          variant="tonal"
          class="starbucks-green"
          size="x-small"
          icon
          @click="addEligibility"
          :disabled="isFormEditable"
        >
          <v-icon>mdi-plus</v-icon>
        </v-btn>
      </v-card-title>
    </v-col>

    <template
      v-for="(service, index) in civilServiceEligibilityForm.eligibilities"
      :key="index"
    >
      <v-row
        :class="index % 2 === 0 ? 'bg-white' : 'bg-grey-lighten-4'"
        class="pa-3 rounded-lg"
      >
        <input type="hidden" v-model="service.id" />

        <v-col cols="12" md="6">
          <v-text-field
            density="compact"
            variant="outlined"
            label="Career Service/RA 1080 (Board/Bar) Under Special Laws/CES/CSEE"
            rounded="lg"
            v-model="
              civilServiceEligibilityForm.eligibilities[index].eligibility
            "
            :disabled="isFormEditable"
          />
        </v-col>

        <v-col cols="12" md="3">
          <v-text-field
            density="compact"
            variant="outlined"
            label="Rating"
            rounded="lg"
            v-model="civilServiceEligibilityForm.eligibilities[index].rating"
            :disabled="isFormEditable"
          />
        </v-col>

        <v-col cols="12" md="2">
          <v-text-field
            density="compact"
            variant="outlined"
            label="Date of Examination/Conferment"
            type="date"
            rounded="lg"
            v-model="
              civilServiceEligibilityForm.eligibilities[index]
                .date_of_examination
            "
            :disabled="isFormEditable"
          />
        </v-col>

        <v-col cols="12" md="6">
          <v-text-field
            density="compact"
            variant="outlined"
            label="Place of Examination/Conferment"
            rounded="lg"
            v-model="
              civilServiceEligibilityForm.eligibilities[index]
                .place_of_examination
            "
            :disabled="isFormEditable"
          />
        </v-col>

        <v-col cols="12" md="3">
          <v-text-field
            density="compact"
            variant="outlined"
            label="License No"
            rounded="lg"
            v-model="
              civilServiceEligibilityForm.eligibilities[index].license_no
            "
            :disabled="isFormEditable"
          />
        </v-col>

        <v-col cols="12" md="2">
          <v-text-field
            density="compact"
            variant="outlined"
            label="Date of Validity"
            type="date"
            rounded="lg"
            v-model="
              civilServiceEligibilityForm.eligibilities[index].date_of_validity
            "
            :disabled="isFormEditable"
          />
        </v-col>

        <v-col cols="12" md="1" class="d-flex align-center">
          <v-btn
            variant="tonal"
            color="red-darken-4"
            size="x-small"
            @click="deleteEligibility(index)"
            :disabled="isFormEditable"
            icon="mdi-delete"
          />
        </v-col>
      </v-row>
    </template>

    <v-row>
      <v-col cols="12">
        <div class="d-flex align-center justify-end">
          <v-btn
            type="submit"
            rounded="xl"
            class="starbucks-green"
            min-width="120"
            :disabled="isFormEditable"
          >
            Save
          </v-btn>
        </div>
      </v-col>
    </v-row>
  </v-form>

  <!-- Delete Dialog -->
  <DeleteDialog
    v-model="isDeleteDialog"
    message="Are you sure you want to delete this eligibility?"
    :loading="loading"
    @confirm="handleDeleteEligibility"
    @cancel="isDeleteDialog = false"
  />

  <!-- <pre>{{ employeeCivilServiceEligibilities }}</pre> -->
</template>
<script>
import { useForm } from "@inertiajs/vue3";
import { helpers, minLength } from "@vuelidate/validators";
import useVuelidate from "@vuelidate/core";
import DeleteDialog from "@/components/DeleteDialog.vue";

export default {
  props: {
    isFormEditable: {
      type: Boolean,
      default: null,
    },
    employee_id: String,
    employeeCivilServiceEligibilities: Object,
  },

  components: {
    DeleteDialog,
  },

  data() {
    const civilServiceEligibilities =
      this.employeeCivilServiceEligibilities?.data || [];

    return {
      v$: useVuelidate(),

      isDeleteDialog: false,
      selectedEligibility: null,

      civilServiceEligibilityForm: useForm({
        eligibilities: civilServiceEligibilities.length
          ? civilServiceEligibilities.map((csc) => ({
              id: csc.id || "",
              eligibility: csc.eligibility || "",
              rating: csc.rating || "",
              date_of_examination: csc.date_of_examination || "",
              place_of_examination: csc.place_of_examination || "",
              license_no: csc.license_no || "",
              date_of_validity: csc.date_of_validity || "",
            }))
          : [
              {
                id: "",
                eligibility: "",
                rating: "",
                date_of_examination: "",
                place_of_examination: "",
                license_no: "",
                date_of_validity: "",
              },
            ],
      }),
    };
  },

  watch: {
    employeeCivilServiceEligibilities: {
      handler(newVal) {
        const data = newVal?.data || [];
        this.civilServiceEligibilityForm.eligibilities = data.length
          ? data.map((e) => ({
              id: e.id || "",
              eligibility: e.eligibility || "",
              rating: e.rating || "",
              date_of_examination: e.date_of_examination || "",
              place_of_examination: e.place_of_examination || "",
              license_no: e.license_no || "",
              date_of_validity: e.date_of_validity || "",
            }))
          : [
              {
                id: "",
                eligibility: "",
                rating: "",
                date_of_examination: "",
                place_of_examination: "",
                license_no: "",
                date_of_validity: "",
              },
            ];
      },
      deep: true,
      immediate: true,
    },
  },

  validations: {
    // Civil Service Eligibility validation
    civilServiceEligibilityForm: {
      $each: helpers.forEach({
        eligibility: {
          minLength: minLength(0),
        },
        rating: {
          minLength: minLength(0),
        },
        date_of_examination: {
          minLength: minLength(0),
        },
        place_of_examination: {
          minLength: minLength(0),
        },
        license_no: {
          minLength: minLength(0),
        },
      }),
    },
  },

  methods: {
    submitForm() {
      // this.eligibilities.post(route('hrmanagement.'))
      // console.log(this.employee_id)

      if (route().current().startsWith("self-service.")) {
        this.civilServiceEligibilityForm.put(
          route("self-service.my-profile.updateCivilServiceEligibility", {
            id: this.employee_id,
          }),
          {
            onSuccess: () => {
              this.showToast("Eligibility successfully updated", "success");
              this.$emit("eligibilityRefresh");
            },

            onError: (errors) => {
              const errorMessages = Object.values(errors).flat().join(" ");

              this.showToast(`${errorMessages}`, "error");
            },
          }
        );
      } else {
        this.civilServiceEligibilityForm.put(
          route("hrmanagement.employee.updateCivilServiceEligibility", {
            id: this.employee_id,
          }),
          {
            onSuccess: () => {
              this.showToast("Eligibility successfully updated", "success");
              this.$emit("eligibilityRefresh");
            },

            onError: (errors) => {
              const errorMessages = Object.values(errors).flat().join(" ");

              this.showToast(`${errorMessages}`, "error");
            },
          }
        );
      }
    },

    addEligibility() {
      this.civilServiceEligibilityForm.eligibilities.push({
        id: null,
        eligibility: null,
        rating: null,
        date_of_examination: null,
        place_of_examination: null,
        license_no: null,
      });
    },

    deleteEligibility(index) {
      const eligibilityId =
        this.civilServiceEligibilityForm.eligibilities[index].id;

      if (eligibilityId) {
        this.isDeleteDialog = true;
        this.selectedEligibility = eligibilityId;
      } else {
        this.civilServiceEligibilityForm.eligibilities.splice(index, 1);
      }
    },

    handleDeleteEligibility() {
      this.$inertia.delete(
        route("hrmanagement.employee.deleteCivilServiceEligibility", {
          id: this.selectedEligibility,
        }),
        {
          onSuccess: () => {
            this.showToast("Eligibility deleted successfully", "success");
            this.isDeleteDialog = false;
            this.selectedEligibility = null;
            this.$emit("eligibilityRefresh");
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
