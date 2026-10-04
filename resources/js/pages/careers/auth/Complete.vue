<template>
  <CareersLayout>
    <Head title="Choose your password" />
    <v-card variant="outlined" class="rounded-lg pa-6 mx-auto" max-width="480">
      <h1 class="text-h5 font-weight-bold mb-1">Choose your password</h1>
      <p class="text-body-2 text-medium-emphasis mb-4">Your email address {{ email }} is confirmed. Choose a password (at least 10 characters, with letters and numbers).</p>
      <v-text-field v-model="form.password" label="Password *" type="password" autocomplete="new-password" variant="outlined" density="compact" :error-messages="form.errors.password" />
      <v-text-field v-model="form.password_confirmation" label="Confirm password *" type="password" autocomplete="new-password" variant="outlined" density="compact" />
      <v-btn block color="primary" :loading="form.processing" :disabled="form.processing" @click="submit">Finish</v-btn>
    </v-card>
  </CareersLayout>
</template>

<script>
import { Head, useForm } from "@inertiajs/vue3";
import CareersLayout from "@/layouts/CareersLayout.vue";

export default {
  name: "CareersCompleteRegistration",
  components: { CareersLayout, Head },
  props: {
    email: { type: String, required: true },
    action: { type: String, required: true }, // the signed URL (kept as-is so the signature stays valid)
  },
  data() {
    return { form: useForm({ password: "", password_confirmation: "" }) };
  },
  methods: {
    submit() {
      this.form.post(this.action);
    },
  },
};
</script>
