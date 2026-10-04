<template>
  <CareersLayout>
    <Head title="Reset your password" />
    <v-card variant="outlined" class="rounded-lg pa-6 mx-auto" max-width="440">
      <h1 class="text-h5 font-weight-bold mb-1">Reset your password</h1>
      <p class="text-body-2 text-medium-emphasis mb-4">Enter your email and we'll send you a reset link.</p>
      <v-alert v-if="status" type="info" variant="tonal" density="compact" class="mb-4" role="status">{{ status }}</v-alert>
      <v-text-field v-model="form.email" label="Email" type="email" autocomplete="email" variant="outlined" density="compact" :error-messages="form.errors.email" />
      <v-btn block color="primary" :loading="form.processing" :disabled="form.processing" @click="submit">Send reset link</v-btn>
      <v-btn block variant="text" class="mt-2" @click="go(route('careers.login'))">Back to sign in</v-btn>
    </v-card>
  </CareersLayout>
</template>

<script>
import { Head, router, useForm } from "@inertiajs/vue3";
import CareersLayout from "@/layouts/CareersLayout.vue";

export default {
  name: "CareersForgotPassword",
  components: { CareersLayout, Head },
  props: { status: { type: String, default: null } },
  data() {
    return { form: useForm({ email: "" }) };
  },
  methods: {
    submit() {
      this.form.post(route("careers.password.email"));
    },
    go(url) {
      router.visit(url);
    },
  },
};
</script>
