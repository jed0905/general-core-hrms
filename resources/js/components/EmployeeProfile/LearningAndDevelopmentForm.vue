<template>
  <!-- Learning and Development Form -->
  <v-form @submit.prevent="submitForm()">
    <v-row>
      <v-col cols="12">
        <v-card-title class="d-flex align-center justify-space-between">
          Learning and Development
          <v-btn
            class="starbucks-green"
            size="x-small"
            icon
            @click="addLearningAndDevelopment"
            :disabled="isFormEditable"
          >
            <v-icon>mdi-plus</v-icon>
          </v-btn>
        </v-card-title>
      </v-col>

      <template
        v-for="(
          learning, index
        ) in learningAndDevelopmentForm.learningDevelopment"
        :key="index"
      >
        <v-row
          :class="index % 2 === 0 ? 'bg-white' : 'bg-grey-lighten-4'"
          class="pa-3 rounded-lg"
        >
          <input type="hidden" v-model="learning.id" />

          <v-col cols="12" md="6">
            <v-text-field
              density="compact"
              variant="outlined"
              label="Title of Learning and Development Interventions/Training Programs"
              v-model="learning.title"
              :disabled="isFormEditable"
              rounded="lg"
            />
          </v-col>

          <v-col cols="12" md="2">
            <v-text-field
              density="compact"
              variant="outlined"
              label="From"
              type="date"
              v-model="learning.from"
              :disabled="isFormEditable"
              rounded="lg"
            />
          </v-col>

          <v-col cols="12" md="2">
            <v-text-field
              density="compact"
              variant="outlined"
              label="To"
              type="date"
              v-model="learning.to"
              :disabled="isFormEditable"
              rounded="lg"
            />
          </v-col>

          <v-col cols="12" md="2">
            <v-text-field
              density="compact"
              variant="outlined"
              label="Number of Hours"
              v-model="learning.training_hours"
              :disabled="isFormEditable"
              rounded="lg"
            />
          </v-col>

          <v-col cols="12" md="6">
            <v-text-field
              density="compact"
              variant="outlined"
              label="Type of LD (Managerial/Supervisory/Technical/etc.)"
              v-model="learning.type_of_ld"
              :disabled="isFormEditable"
              rounded="lg"
            />
          </v-col>

          <v-col cols="12" md="5">
            <v-text-field
              density="compact"
              variant="outlined"
              label="Conducted/Sponsored by"
              v-model="learning.conducted_by"
              :disabled="isFormEditable"
              rounded="lg"
            />
          </v-col>

          <v-col cols="12" md="1" class="d-flex align-center">
            <v-btn
              color="error"
              size="x-small"
              icon
              @click="removeLearningAndDevelopment(index)"
              :disabled="isFormEditable"
              variant="tonal"
            >
              <v-icon>mdi-delete</v-icon>
            </v-btn>
          </v-col>
        </v-row>
      </template>

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
    message="Are you sure you want to delete this learning and development?"
    :loading="loading"
    @confirm="handleDeleteLearningAndDevelopment"
    @cancel="isDeleteDialog = false"
  />
  <!-- <pre>{{ employeeLearningAndDevelopment }}</pre> -->
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

    employeeLearningAndDevelopment: Object,
  },

  components: {
    DeleteDialog,
  },

  data() {
    const ld = this.employeeLearningAndDevelopment?.data || [];

    return {
      v$: useVuelidate(),

      learningAndDevelopmentForm: useForm({
        learningDevelopment: ld.length
          ? ld.map((e) => ({
              id: e.id || "",
              title: e.title || "",
              from: e.from || "",
              to: e.to || "",
              training_hours: e.training_hours || "",
              type_of_ld: e.type_of_ld || "",
              conducted_by: e.conducted_by || "",
            }))
          : [
              {
                id: "",
                title: "",
                from: "",
                to: "",
                training_hours: "",
                type_of_ld: "",
                conducted_by: "",
              },
            ],
      }),

      selectedLearningAndDevelopment: null,
      isDeleteDialog: false,
    };
  },

  watch: {
    employeeLearningAndDevelopment: {
      handler(newVal) {
        const data = newVal?.data || [];
        this.learningAndDevelopmentForm.learningDevelopment = data.length
          ? data.map((e) => ({
              id: e.id || "",
              title: e.title || "",
              from: e.from || "",
              to: e.to || "",
              training_hours: e.training_hours || "",
              type_of_ld: e.type_of_ld || "",
              conducted_by: e.conducted_by || "",
            }))
          : [
              {
                id: "",
                title: "",
                from: "",
                to: "",
                training_hours: "",
                type_of_ld: "",
                conducted_by: "",
              },
            ];
      },
      deep: true,
      immediate: true,
    },
  },

  validations: {
    learningAndDevelopment: {
      $each: helpers.forEach({
        title: {
          minLength: minLength(0),
        },
        from: {
          minLength: minLength(0),
        },
        to: {
          minLength: minLength(0),
        },
        numberOfHours: {
          minLength: minLength(0),
        },
        type: {
          minLength: minLength(0),
        },
        conductedBy: {
          minLength: minLength(0),
        },
      }),
    },
  },

  methods: {
    addLearningAndDevelopment() {
      this.learningAndDevelopmentForm.learningDevelopment.push({
        id: "",
        title: "",
        from: "",
        to: "",
        training_hours: "",
        type_of_ld: "",
        conducted_by: "",
      });
    },

    removeLearningAndDevelopment(index) {
      const learningAndDevelopmentId =
        this.learningAndDevelopmentForm.learningDevelopment[index].id;

      if (learningAndDevelopmentId) {
        this.selectedLearningAndDevelopment = learningAndDevelopmentId;
        this.isDeleteDialog = true;
      } else {
        this.learningAndDevelopmentForm.learningDevelopment.splice(index, 1);
      }
    },

    handleDeleteLearningAndDevelopment() {
      this.$inertia.delete(
        route("hrmanagement.employee.deleteEmployeeLearningAndDevelopment", {
          id: this.selectedLearningAndDevelopment,
        }),
        {
          onSuccess: () => {
            this.showToast(
              "Learning and Development successfully deleted",
              "success"
            );
            this.isDeleteDialog = false;
            this.$emit("learningAndDevelopmentRefresh");
          },
          onError: (errors) => {
            this.isDeleteDialog = false;
            const errorMessages = Object.values(errors).flat().join(" ");
            this.showToast(errorMessages, "error");
          },
        }
      );
    },

    submitForm() {
      if (
        this.learningAndDevelopmentForm.learningDevelopment[0].title === "" &&
        this.learningAndDevelopmentForm.learningDevelopment.length === 1
      ) {
        this.showToast(
          "Please add at least one learning and development.",
          "error"
        );
        return;
      }

      if (
        this.learningAndDevelopmentForm.learningDevelopment.some(
          (ld) =>
            ld.title === "" ||
            ld.from === "" ||
            ld.to === "" ||
            ld.training_hours === "" ||
            ld.type_of_ld === "" ||
            ld.conducted_by === ""
        )
      ) {
        this.v$.$touch();
        this.showToast("Please fill in all required fields.", "error");
        return;
      }

      if (route().current().startsWith("self-service.")) {
        this.learningAndDevelopmentForm.put(
          route(
            "self-service.my-profile.updateEmployeeLearningAndDevelopment",
            {
              id: this.employee_id,
            }
          ),
          {
            onSuccess: () => {
              this.showToast(
                "Learning and Development updated successfully",
                "success"
              );
              this.$emit("learningAndDevelopmentRefresh");
            },
            onError: (errors) => {
              const errorMessages = Object.values(errors).flat().join(" ");
              this.showToast(`${errorMessages}`, "error");
            },
          }
        );
      } else {
        this.learningAndDevelopmentForm.put(
          route("hrmanagement.employee.updateEmployeeLearningAndDevelopment", {
            id: this.employee_id,
          }),
          {
            onSuccess: () => {
              this.showToast(
                "Learning and Development updated successfully",
                "success"
              );
              this.$emit("learningAndDevelopmentRefresh");
            },
            onError: (errors) => {
              const errorMessages = Object.values(errors).flat().join(" ");
              this.showToast(`${errorMessages}`, "error");
            },
          }
        );
      }
    },
  },
};
</script>
