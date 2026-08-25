<template>
  <v-app>
    <v-navigation-drawer v-model="drawer">
      <div class="d-flex flex-column justify-center align-center mb-3 mt-3">
        <img :src="Logo" width="90" height="90" />
        <p class="text-subtitle-2">
          DMMMSU <!-- {{ $page.props.campus.abbreviation }} -->
        </p>
        <p class="text-subtitle-2">Employee Maintenance</p>
      </div>
      <v-divider></v-divider>

      <div class="mt-6">
        <p class="ml-2 text-caption" v-text="nav_title"></p>
        <v-list density="compact" nav>
          <template v-for="(navItem, i) in navItems" :key="i">
            <Link
              :href="route(navItem.route)"
              preserve-state
              class="text-decoration-none text-black"
            >
              <v-list-item
                link
                
                :prepend-icon="navItem.icon"
                :title="navItem.title"
                :exact="navItem.exact"
                :class="{
                  'v-list-item--active': route().current(navItem.route)
                }"
              >
              </v-list-item>
            </Link>
          </template>
        </v-list>
      </div>
    </v-navigation-drawer>

    <v-app-bar :elevation="1" class="pe-2">
      <v-app-bar-nav-icon @click="drawer = !drawer"></v-app-bar-nav-icon>
      <v-btn icon="mdi-home" :href="route('home.index')"></v-btn>

      <v-spacer></v-spacer>

      <!-- <p class="text-subtitle" v-text="$page.props.auth.initials"></p> -->

      <v-menu>
        <template v-slot:activator="{ props }">
          <v-btn icon="mdi-dots-vertical" v-bind="props"> </v-btn>
        </template>

        <v-list nav>
          <!-- <v-list-item
            title="Profile"
            value="profile"
            @click="
              triggerRouteLink('maintenance-and-utilities.user-profile.index')
            "
          ></v-list-item> -->
          <v-list-item
            title="Logout"
            value="logout"
            @click.prevent="handleLogout()"
          ></v-list-item>
        </v-list>
      </v-menu>
    </v-app-bar>

    <v-main class="bg-grey-lighten-5">
      <v-container fluid>
        <slot />
      </v-container>
    </v-main>
  </v-app>
</template>


<script>
import Logo from "../../images/dmmmsu-logo.png";

export default {
  data() {
    return {
      Logo,
      drawer: true,
      active: "dashboard",
    };
  },

  computed: {
    // nav_title() {
    //   return (
    //     this.$page.props.auth.role.charAt(0).toUpperCase() +
    //     this.$page.props.auth.role.slice(1)
    //   );
    // },
    navItems() {

      const Dashboard = {
        icon: 'mdi-view-dashboard',
        title: 'Dashboard',
        route: 'employee-maintenance.dashboard',
        active: route().current('employee-maintenance.dashboard'),
      }

      const Employee = {
        icon: "mdi-account",
        title: "Employee",
        route: "employee-maintenance.management.index",
        active: route().current('employee-maintenance.management.index'),
      };
      
      return [
        Dashboard,
        Employee,
      ];
      
    },
  },

  methods: {
    handleLogout() {
      this.$inertia.post(route("auth.logout"), "", {
        onSuccess: () => {
          this.showToast("Logout successful.");
        },
      });
    },
  },
};
</script>

<style>
</style>
