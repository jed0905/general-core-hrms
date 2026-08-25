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
          title: 'Edit',
          disabled: true,
          href: '#',
        },
      ]"
    />
  </div>
  <FormWrapper
    name="Edit Permission"
    description="This module allows user to edit and update a permission."
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
          <div class="d-flex justify-end">
            <ButtonSuccess type="submit" name="Update" />
          </div>
        </v-col>
      </v-row>
    </v-form>
  </FormWrapper>
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
    permission: Object,
  },
  data() {
    return {
      v$: useVuelidate(),
      form: useForm({
        name: this.permission.name,
      }),
    };
  },
  validations() {
    return {
      form: {
        name: { required },
      },
    };
  },
  methods: {
    handleSubmit() {
      this.form.put(route("permission.management.update", { id: this.permission.id }), {
        onSuccess: () => {
          this.showToast("Permission updated successfully", "success");
        },
        onError: (errors) => {
          for (const error in errors) {
            this.showToast(error[key][0], "error");
          }
        },
      });
    },
  },
};
</script>

