<template>
  <CareersLayout>
    <Head title="Sign in" />
    <v-card variant="outlined" class="rounded-lg pa-6 mx-auto" max-width="440">
      <h1 class="text-h5 font-weight-bold mb-4">Sign in</h1>
      <v-alert v-if="status" type="info" variant="tonal" density="compact" class="mb-4" role="status">{{ status }}</v-alert>
      <v-text-field v-model="form.email" label="Email" type="email" autocomplete="email" variant="outlined" density="compact" :error-messages="form.errors.email" />
      <v-text-field v-model="form.password" label="Password" type="password" autocomplete="current-password" variant="outlined" density="compact" :error-messages="form.errors.password" @keyup.enter="submit" />
      <v-checkbox v-model="form.remember" label="Keep me signed in" density="compact" hide-details class="mb-2" />
      <v-btn block color="primary" :loading="form.processing" :disabled="form.processing" @click="submit">Sign in</v-btn>
      <div class="d-flex flex-wrap justify-space-between mt-4">
        <v-btn variant="text" size="small" @click="go(route('careers.password.request'))">Forgot password?</v-btn>
        <v-btn variant="text" size="small" @click="go(route('careers.register'))">Create an account</v-btn>
      </div>
      <v-divider class="my-3" />
      <p class="text-caption text-medium-emphasis mb-1">Didn't get the confirmation email?</p>
      <div class="d-flex ga-2">
        <v-text-field v-model="resend.email" label="Email" type="email" variant="outlined" density="compact" hide-details />
        <v-btn variant="tonal" :loading="resend.processing" @click="sendAgain">Resend</v-btn>
      </div>
    </v-card>
  </CareersLayout>
</template>

<script>
import { Head, router, useForm } from "@inertiajs/vue3";
import CareersLayout from "@/layouts/CareersLayout.vue";

export default {
  name: "CareersLogin",
  components: { CareersLayout, Head },
  props: { status: { type: String, default: null } },
  data() {
    return { form: useForm({ email: "", password: "", remember: false }), resend: useForm({ email: "" }) };
  },
  methods: {
    submit() {
      this.form.post(route("careers.login.store"), { onFinish: () => this.form.reset("password") });
    },
    sendAgain() {
      this.resend.post(route("careers.register.resend"), { preserveScroll: true });
    },
    go(url) {
      router.visit(url);
    },
  },
};
</script>
