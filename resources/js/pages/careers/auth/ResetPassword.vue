<template>
  <CareersLayout>
    <Head title="Choose a new password" />
    <v-card variant="outlined" class="rounded-lg pa-6 mx-auto" max-width="440">
      <h1 class="text-h5 font-weight-bold mb-4">Choose a new password</h1>
      <v-text-field v-model="form.email" label="Email" type="email" autocomplete="email" variant="outlined" density="compact" :error-messages="form.errors.email" />
      <v-text-field v-model="form.password" label="New password" type="password" autocomplete="new-password" hint="At least 10 characters, with letters and numbers" persistent-hint variant="outlined" density="compact" :error-messages="form.errors.password" />
      <v-text-field v-model="form.password_confirmation" label="Confirm new password" type="password" autocomplete="new-password" variant="outlined" density="compact" class="mt-2" />
      <v-btn block color="primary" :loading="form.processing" :disabled="form.processing" @click="submit">Change password</v-btn>
    </v-card>
  </CareersLayout>
</template>

<script>
import { Head, useForm } from "@inertiajs/vue3";
import CareersLayout from "@/layouts/CareersLayout.vue";

export default {
  name: "CareersResetPassword",
  components: { CareersLayout, Head },
  props: { token: { type: String, required: true }, email: { type: String, default: "" } },
  data() {
    return { form: useForm({ token: this.token, email: this.email, password: "", password_confirmation: "" }) };
  },
  methods: {
    submit() {
      this.form.post(route("careers.password.update"));
    },
  },
};
</script>
