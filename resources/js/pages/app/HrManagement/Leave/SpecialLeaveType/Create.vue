<template>
  <LeaveManagementTabs
    :activeMenuTitle="activeMenuTitle"
    v-model:activeTab="activeTab"
  />
  <FormWrapper>
    <div class="v-card-title">Add Special Leave Type</div>
    <v-divider class="my-4" style="border: 1px solid black"></v-divider>
    <v-form @submit.prevent="handleSubmit()">
      <v-col cols="12" md="6">
        <v-text-field
          v-model="form.name"
          label="Special Leave Name"
          variant="outlined"
          density="compact"
          rounded="lg"
          :error-messages="v$.form.name.$errors.map((e) => e.$message)"
        ></v-text-field>
      </v-col>
      <v-col cols="12" md="6">
        <v-text-field
          v-model="form.shortcut"
          label="Shortcut"
          variant="outlined"
          density="compact"
          rounded="lg"
          :error-messages="v$.form.shortcut.$errors.map((e) => e.$message)"
        ></v-text-field>
      </v-col>
      <v-divider class="my-4" style="border: 1px solid black"></v-divider>
      <div class="d-flex align-center justify-end">
        <ButtonMuted
          @click="goToIndex()"
          prepend-icon="mdi-cancel"
          name="Cancel"
        />
        <ButtonSuccess
          type="submit"
          prepend-icon="mdi-content-save-outline"
          name="Save"
          class="ml-4"
        />
      </div>
    </v-form>
  </FormWrapper>

  <!-- Again Dialog -->
  <AgainDialog
    v-model="showAgainDialog"
    :message="againDialogMessage"
    @confirm="handleCreateAgain"
    @cancel="handleDialogCancel"
  />
</template>
<script>
import SidebarLayout from "@/layouts/SidebarLayout.vue";
import LeaveManagementTabs from "@/components/LeaveManagementTabs.vue";
import ButtonSuccess from "@/components/ButtonSuccess.vue";
import ButtonMuted from "@/components/ButtonMuted.vue";
import AgainDialog from "@/components/AgainDialog.vue";
import { useForm } from "@inertiajs/vue3";
import useVuelidate from "@vuelidate/core";
import { required, minLength, maxLength, helpers } from "@vuelidate/validators";
import FormWrapper from "@/components/FormWrapper.vue";

export default {
  layout: SidebarLayout,
  components: {
    LeaveManagementTabs,
    ButtonSuccess,
    ButtonMuted,
    FormWrapper,
    AgainDialog,
  },
  props: {
    errors: Object,
  },
  data() {
    return {
      activeTab: "configure",
      showAgainDialog: false,
      v$: useVuelidate(),
      againDialogMessage: "Do you want to create another special leave type?",
      form: useForm({
        name: null,
        shortcut: null,
      }),
    };
  },
  validations() {
    return {
      form: {
        name: {
          required: helpers.withMessage("Name is required", required),
          minLength: helpers.withMessage(
            "Name must be at least 2 characters",
            minLength(2)
          ),
          maxLength: helpers.withMessage(
            "Name cannot exceed 255 characters",
            maxLength(255)
          ),
        },
        shortcut: {
          required: helpers.withMessage("Shortcut is required", required),
          minLength: helpers.withMessage(
            "Shortcut must be at least 2 characters",
            minLength(2)
          ),
          maxLength: helpers.withMessage(
            "Shortcut cannot exceed 10 characters",
            maxLength(10)
          ),
        },
      },
    };
  },

  methods: {
    handleSubmit() {
      this.form.post(route("hrmanagement.leave.specialLeave.store"), {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
          this.showToast("Special leave created successfully", "success");
          this.showAgainDialog = true;
        },
        onError: (errors) => {
          const errorMessages = Object.values(errors).flat().join(" ");
          this.showToast(`${errorMessages}`, "error");
        },
      });
    },
    handleCreateAgain() {
      this.showAgainDialog = false;
      this.form.reset();
    },
    handleDialogCancel() {
      this.showAgainDialog = false;
      return this.$inertia.visit(
        route("hrmanagement.leave.specialLeave.index")
      );
      // Optionally redirect to index page or stay on current page
    },
    goToIndex() {
      return this.$inertia.visit(
        route("hrmanagement.leave.specialLeave.index")
      );
    },
  },
};
</script>
