<template>
  <v-app>
    <v-app-bar flat border="b" color="surface" density="comfortable">
      <v-container class="d-flex align-center py-0" style="max-width: 1200px">
        <a :href="route('careers.index')" class="d-flex align-center text-decoration-none text-high-emphasis" aria-label="Careers home" @click.prevent="go(route('careers.index'))">
          <img v-if="logo" :src="logo" :alt="`${company.name} logo`" style="height: 36px; max-width: 160px; object-fit: contain" class="mr-3" />
          <span class="text-subtitle-1 font-weight-bold">{{ company.name }}</span>
          <span class="text-subtitle-1 text-medium-emphasis ml-2 d-none d-sm-inline">Careers</span>
        </a>
        <v-spacer />
        <nav class="d-none d-md-flex align-center ga-1" aria-label="Careers">
          <v-btn variant="text" @click="go(route('careers.index'))">Jobs</v-btn>
          <template v-if="candidate">
            <v-btn variant="text" @click="go(route('careers.applications.index'))">My Applications</v-btn>
            <v-btn variant="text" @click="go(route('careers.profile'))">My Profile</v-btn>
            <v-btn variant="outlined" @click="logout">Sign out</v-btn>
          </template>
          <template v-else>
            <v-btn variant="text" @click="go(route('careers.login'))">Sign in</v-btn>
            <v-btn color="primary" variant="flat" @click="go(route('careers.register'))">Create account</v-btn>
          </template>
        </nav>
        <v-menu>
          <template #activator="{ props }">
            <v-btn class="d-md-none" icon="mdi-menu" variant="text" aria-label="Open menu" v-bind="props" />
          </template>
          <v-list density="compact">
            <v-list-item title="Jobs" @click="go(route('careers.index'))" />
            <template v-if="candidate">
              <v-list-item title="My Applications" @click="go(route('careers.applications.index'))" />
              <v-list-item title="My Profile" @click="go(route('careers.profile'))" />
              <v-list-item title="Sign out" @click="logout" />
            </template>
            <template v-else>
              <v-list-item title="Sign in" @click="go(route('careers.login'))" />
              <v-list-item title="Create account" @click="go(route('careers.register'))" />
            </template>
          </v-list>
        </v-menu>
      </v-container>
    </v-app-bar>

    <v-main class="bg-background">
      <v-container class="py-6 px-4" style="max-width: 1200px">
        <v-alert v-if="notice" type="success" variant="tonal" closable class="mb-4" role="status">{{ notice }}</v-alert>
        <slot />
      </v-container>
    </v-main>

    <v-footer border="t" class="text-body-2 text-medium-emphasis">
      <v-container class="d-flex flex-wrap ga-4 py-2" style="max-width: 1200px">
        <span>&copy; {{ year }} {{ company.name }}</span>
        <span v-if="company.email">Contact: <a :href="`mailto:${company.email}`">{{ company.email }}</a></span>
        <span v-if="company.phone">{{ company.phone }}</span>
      </v-container>
    </v-footer>
  </v-app>
</template>

<script>
import { router } from "@inertiajs/vue3";

export default {
  name: "CareersLayout",
  computed: {
    portal() {
      return this.$page.props.portal || {};
    },
    company() {
      return this.portal.company || { name: "Careers" };
    },
    candidate() {
      return this.portal.candidate;
    },
    notice() {
      return this.portal.notice;
    },
    logo() {
      return this.$page.props.branding?.client_logo || null;
    },
    year() {
      return new Date().getFullYear();
    },
  },
  methods: {
    go(url) {
      router.visit(url);
    },
    logout() {
      router.post(route("careers.logout"));
    },
  },
};
</script>
