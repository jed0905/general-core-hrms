<template>
  <!-- Voluntary Work Form -->
  <v-form @submit.prevent="submitForm()">
    <v-col cols="12">
      <v-card-title class="d-flex align-center justify-space-between">
        Voluntary Work
        <v-btn
          variant="tonal"
          class="starbucks-green"
          size="x-small"
          icon
          @click="addVoluntaryWork"
          :disabled="isFormEditable"
        >
          <v-icon>mdi-plus</v-icon>
        </v-btn>
      </v-card-title>
    </v-col>

    <template
      v-for="(work, index) in voluntaryWorkForm.voluntaryWorks"
      :key="index"
    >
      <v-row
        :class="index % 2 === 0 ? 'bg-white' : 'bg-grey-lighten-4'"
        class="pa-3 rounded-lg"
      >
        <input type="hidden" v-model="work.id" />

        <v-col cols="12" md="6">
          <v-text-field
            density="compact"
            variant="outlined"
            label="Name & Address of Organization"
            v-model="work.name_of_organization"
            :disabled="isFormEditable"
            rounded="lg"
          />
        </v-col>

        <v-col cols="12" md="6">
          <v-text-field
            density="compact"
            variant="outlined"
            label="Position/Nature of Work"
            v-model="work.position"
            :disabled="isFormEditable"
            rounded="lg"
          />
        </v-col>

        <v-col cols="12" md="4">
          <v-text-field
            density="compact"
            variant="outlined"
            label="From"
            type="date"
            v-model="work.from"
            :disabled="isFormEditable"
            rounded="lg"
          />
        </v-col>

        <v-col cols="12" md="4">
          <v-text-field
            density="compact"
            variant="outlined"
            label="To"
            type="date"
            v-model="work.to"
            :disabled="isFormEditable"
            rounded="lg"
          />
        </v-col>

        <v-col cols="12" md="3">
          <v-text-field
            density="compact"
            variant="outlined"
            label="Number of Hours"
            type="number"
            v-model="work.hours"
            :disabled="isFormEditable"
            rounded="lg"
          />
        </v-col>

        <v-col cols="12" md="1" class="d-flex align-center">
          <v-btn
            color="error"
            size="x-small"
            icon
            @click="removeVoluntaryWork(index)"
            :disabled="isFormEditable"
            variant="tonal"
          >
            <v-icon>mdi-delete</v-icon>
          </v-btn>
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
    v-model="isDeleteDialogVisible"
    message="Are you sure you want to delete this voluntary work?"
    :loading="loading"
    @confirm="handleDeleteVoluntaryWork"
    @cancel="isDeleteDialogVisible = false"
  />

  <!-- <pre>{{ employeeVoluntaryWorks }}</pre> -->
</template>
<script>
import { useForm } from "@inertiajs/vue3";
import { helpers, minLength, required } from "@vuelidate/validators";
import useVuelidate from "@vuelidate/core";
import DeleteDialog from "@/components/DeleteDialog.vue";

export default {
  props: {
    isFormEditable: {
      type: Boolean,
      default: null,
    },

    employee_id: String,
    employeeVoluntaryWorks: Object,
  },

  components: {
    DeleteDialog,
  },

  data() {
    const voluntary = this.employeeVoluntaryWorks?.data || [];

    return {
      isDeleteDialogVisible: false,

      selectedVoluntaryWork: null,

      v$: useVuelidate(),

      voluntaryWorkForm: useForm({
        voluntaryWorks: voluntary.length
          ? voluntary.map((v) => ({
              id: v.id || "",
              name_of_organization: v.name_of_organization || "",
              from: v.from || "",
              to: v.to || "",
              hours: v.hours || "",
              position: v.position || "",
            }))
          : [
              {
                id: "",
                name_of_organization: "",
                from: "",
                to: "",
                hours: "",
                position: "",
              },
            ],
      }),
    };
  },

  watch: {
    employeeVoluntaryWorks: {
      handler(newVal) {
        const data = newVal?.data || [];
        this.voluntaryWorkForm.voluntaryWorks = data.length
          ? data.map((v) => ({
              id: v.id || "",
              name_of_organization: v.name_of_organization || "",
              from: v.from || "",
              to: v.to || "",
              hours: v.hours || "",
              position: v.position || "",
            }))
          : [
              {
                id: "",
                name_of_organization: "",
                from: "",
                to: "",
                hours: "",
                position: "",
              },
            ];
      },
      deep: true,
      immediate: true,
    },
  },

  validations: {
    voluntaryWorks: {
      $each: helpers.forEach({
        nameAddress: { required, minLength: minLength(1) },
        position: { required, minLength: minLength(1) },
        from: { required, minLength: minLength(1) },
        to: { required, minLength: minLength(1) },
        hours: { required, minLength: minLength(1) },
      }),
    },
  },

  methods: {
    addVoluntaryWork() {
      this.voluntaryWorkForm.voluntaryWorks.push({
        id: "",
        name_of_organization: "",
        from: "",
        to: "",
        hours: "",
        position: "",
      });
    },

    removeVoluntaryWork(index) {
      const voluntaryWorkId = this.voluntaryWorkForm.voluntaryWorks[index].id;

      if (voluntaryWorkId) {
        this.selectedVoluntaryWork = voluntaryWorkId;
        this.isDeleteDialogVisible = true;
      } else {
        this.voluntaryWorkForm.voluntaryWorks.splice(index, 1);
      }
    },

    handleDeleteVoluntaryWork() {
      this.$inertia.delete(
        route("hrmanagement.employee.deleteEmployeeVoluntaryWork", {
          id: this.selectedVoluntaryWork,
        }),
        {
          onSuccess: () => {
            this.showToast("Voluntary work successfully deleted", "success");
            this.isDeleteDialogVisible = false;
            this.$emit("voluntaryWorkRefresh");
          },
          onError: (errors) => {
            this.isDeleteDialogVisible = false;
            const errorMessages = Object.values(errors).flat().join(" ");
            this.showToast(errorMessages, "error");
          },
        }
      );
    },

    submitForm() {
      if (
        this.voluntaryWorkForm.voluntaryWorks[0].name_of_organization === "" &&
        this.voluntaryWorkForm.voluntaryWorks.length === 1
      ) {
        this.showToast("Please add at least one voluntary work.", "error");
        return;
      }

      if (
        this.voluntaryWorkForm.voluntaryWorks.some(
          (work) =>
            work.name_of_organization === "" ||
            work.from === "" ||
            work.to === "" ||
            work.hours === "" ||
            work.position === ""
        )
      ) {
        this.v$.$touch();
        this.showToast("Please fill in all required fields.", "error");
        return;
      }
      if (route().current().startsWith("self-service.")) {
        this.voluntaryWorkForm.put(
          route("self-service.my-profile.updateEmployeeVoluntaryWork", {
            id: this.employee_id,
          }),
          {
            onSuccess: () => {
              this.showToast("Voluntary work successfully updated", "success");
              this.$emit("voluntaryWorkRefresh");
            },
            onError: (errors) => {
              const errorMessages = Object.values(errors).flat().join(" ");
              this.showToast(errorMessages, "error");
            },
          }
        );
      } else {
        this.voluntaryWorkForm.put(
          route("hrmanagement.employee.updateEmployeeVoluntaryWork", {
            id: this.employee_id,
          }),
          {
            onSuccess: () => {
              this.showToast("Voluntary work successfully updated", "success");
              this.$emit("voluntaryWorkRefresh");
            },
            onError: (errors) => {
              const errorMessages = Object.values(errors).flat().join(" ");
              this.showToast(errorMessages, "error");
            },
          }
        );
      }
    },

    handleSubmitVoluntaryWork() {
      console.log(this.voluntaryWorks);
      this.v$.$touch();

      if (!this.v$.$invalid) {
        alert("Voluntary Work form submitted successfully!");
      }
    },
  },
};
</script>
