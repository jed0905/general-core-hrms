<template>
  <div class="mb-3 d-flex justify-space-between align-center">
    <Breadcrumbs
      :items="[
        {
          title: 'Permissions',
          disabled: false,
          href: route('permission.management.index'),
        },
        {
          title: 'Create',
          disabled: true,
          href: '#',
        },
      ]"
    />
  </div>
  <FormWrapper
    name="Create Permission"
    description="This module allows user to create a new role."
    rounded="lg"
  >
    <v-form @submit.prevent="handleSubmit()">
      <v-row dense>
        <v-col cols="12">
          <v-text-field
            label="Permission Name"
            variant="outlined"
            density="compact"
            class="mb-2"
            rounded="lg"
            v-model="form.name"
            :error-messages="v$.form.name.$errors.map((e) => e.$message)"
          ></v-text-field>
        </v-col>
        <v-col cols="12">
          <v-select
            label="Guard"
            variant="outlined"
            density="compact"
            rounded="lg"
            :items="guardNames"
            item-value="value"
            item-title="title"
            v-model="v$.form.guard_name.$model"
            :error-messages="v$.form.guard_name.$errors.map((e) => e.$message)"
          />
        </v-col>
        <v-col cols="12">
          <div class="d-flex justify-end">
            <ButtonSuccess type="submit" name="Create" />
          </div>
        </v-col>
      </v-row>
    </v-form>
  </FormWrapper>

  <!-- Create Another Role Dialog -->
  <v-dialog v-model="isCreateAnotherPermissionDialog" max-width="500">
    <v-card class="pa-4 rounded-lg">
      <v-card-title
        class="d-flex align-center justify-center text-h5 font-weight-bold mb-4"
      >
        <v-icon color="success" size="large" class="mr-2">
          mdi-alert-circle
        </v-icon>
        Create Another Role
      </v-card-title>
      <v-divider class="mb-4"></v-divider>
      <v-card-text class="text-body-1 text-center">
        <p class="mb-2">Are you sure you want to create another role?</p>
      </v-card-text>
      <v-card-actions class="d-flex justify-end gap-2 pa-4">
        <v-btn
          color="grey-darken-1"
          variant="outlined"
          @click="returnToIndex()"
          min-width="120"
        >
          No
        </v-btn>
        <v-btn
          color="success"
          variant="elevated"
          min-width="120"
          @click="handleCreateAnotherPermission()"
        >
          Yes
        </v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>
</template>
<script>
import SidebarLayout from "@/layouts/SidebarLayout.vue";
import Breadcrumbs from "@/components/Breadcrumbs.vue";
import FormWrapper from "@/components/FormWrapper.vue";
import ButtonSuccess from "@/components/ButtonSuccess.vue";
import { useForm } from "@inertiajs/vue3";
import useVuelidate from "@vuelidate/core";
import { required } from "@vuelidate/validators";

export default {
  layout: SidebarLayout,
  components: {
    Breadcrumbs,
    FormWrapper,
    ButtonSuccess,
  },
  props: {
    errors: Object,
  },
  data() {
    return {
      isCreateAnotherPermissionDialog: false,
      v$: useVuelidate(),
      form: useForm({
        name: null,
        guard_name: "web",
      }),

      guardNames: [
        { value: "web", title: "Web" },
        { value: "api", title: "API" },
      ],
    };
  },
  validations() {
    return {
      form: {
        name: { required },
        guard_name: { required },
      },
    };
  },
  methods: {
    handleSubmit() {
      this.form.post(route("permission.management.store"), {
        onSuccess: () => {
          this.showToast("Permission created successfully", "success");
          this.isCreateAnotherPermissionDialog = true;
        },
        onError: (errors) => {
          if (errors) {
            this.showToast(errors.message, "error");
          }
        },
      });
    },

    handleCreateAnotherPermission() {
      this.form.name = null;
      this.form.guard_name = "web";
      this.v$.form.$reset();
      this.isCreateAnotherPermissionDialog = false;
    },

    returnToIndex() {
      this.isCreateAnotherPermissionDialog = false;
      this.$inertia.visit(route("permission.management.index"));
    },
  },
};
</script>

