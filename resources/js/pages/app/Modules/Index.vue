<template>
  <v-col class="bg-white d-flex flex-column justify-start align-center">
    <v-container class="pa-5 flex-shrink-0">
      <!-- <pre>
        {{subsystems}}
      </pre> -->
      <div class="text-end">
        <v-menu location="bottom">
          <template v-slot:activator="{ props }">
            <v-btn v-bind="props" v-text="$page.props.auth.initials"></v-btn>
          </template>

          <v-list nav>
            <v-list-item
              title="Logout"
              value="dashboard"
              @click.prevent="handleLogout()"
            ></v-list-item>
          </v-list>
        </v-menu>
      </div>
    </v-container>
  
    <v-container
      class="h-100 d-flex flex-column justify-center flex-shrink-0 align-center gap-2"
    >
      
      <p class="text-h4 text-center my-2 text-uppercase">Modules</p>
      <div class="d-flex flex-wrap justify-center align-center">
        <v-btn
          class="ma-2"
          width="200"
          stacked
          :prepend-icon="module.icon"
          :disabled="module.disabled"
          v-for="(module, i) in modules"
          :key="i"
          :href="route(module.to)"
        >
          {{ module.label }}
        </v-btn>
      </div>
    </v-container>
    
  </v-col>
</template>

<script>
import ModulesLayout from '@/layouts/ModulesLayout.vue'

export default {

  layout: ModulesLayout,

  props: {
    modules: Object,
  },

  methods: {
    handleLogout() {
      this.$inertia.post(route('auth.logout'), '', {
        onSuccess: () => {
          this.showToast('Logout successful.')
        }
      })
    }
  },
}
</script>

<style>
</style>