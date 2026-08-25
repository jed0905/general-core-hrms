<template>
  <v-app>
    <v-navigation-drawer class="noscroll rounded-right" v-model="drawer">
      <!-- Fixed Logo Section -->
      <div
        class="sidebar-header d-flex flex-column justify-center align-center"
      >
        <img :src="Logo" width="90" height="90" />
        <p class="text-subtitle-2 mt-1">{{ $page.props.company_shortcut }}</p>
        <p class="text-subtitle-2 text-center text-wrap">
          {{ $page.props.app_name }}
        </p>
      </div>

      <v-divider></v-divider>

      <div class="sidebar-content overflow-y-auto" v-if="navItems.length">
        <v-list nav dense class="sidebar-list pa-0">
          <template v-for="(group, groupIndex) in navItems" :key="groupIndex">
            <v-list-subheader class="text-uppercase text-grey-darken-1">
              {{ group.title }}
            </v-list-subheader>

            <template v-for="(item, itemIndex) in group.items" :key="itemIndex">
              <Link
                v-if="
                  !item.permission ||
                  $page.props.auth.permissions.includes(item.permission)
                "
                :href="route(item.route)"
                preserve-state
                class="text-decoration-none text-black"
              >
                <v-list-item
                  link
                  class="py-2 px-4"
                  :prepend-icon="item.icon"
                  :title="item.title"
                  :class="{
                    'v-list-item--active': route().current(
                      `${item.routePrefix}*`
                    ),
                  }"
                />
              </Link>
            </template>
          </template>
        </v-list>
      </div>
    </v-navigation-drawer>

    <v-app-bar :elevation="1" class="pe-2 gradient-bg">
      <v-app-bar-nav-icon
        @click="drawer = !drawer"
        style="color: white !important"
      ></v-app-bar-nav-icon>
      <v-banner-text style="color: white !important">
        {{ activeMenuTitle }}
      </v-banner-text>
      <v-spacer></v-spacer>

      <!-- 🔔 Notification Bell -->
      <v-menu
        v-model="notificationsMenu"
        offset-y
        :close-on-content-click="false"
        @update:modelValue="handleMenuToggle"
        @open="calculateDropdownHeight"
      >
        <template #activator="{ props }">
          <div class="me-6 d-flex align-center" v-bind="props">
            <!-- 🔔 Show badge only if there are unread notifications -->
            <v-badge
              v-if="unreadNotifications.length > 0"
              :content="unreadNotifications.length"
              color="error"
              overlap
              offset-x="1"
              offset-y="25"
            >
              <v-icon
                icon="mdi-bell-outline"
                size="26"
                class="text-white cursor-pointer"
              />
            </v-badge>

            <!-- 🔕 Plain bell when no unread notifications -->
            <v-icon
              v-else
              icon="mdi-bell-outline"
              size="26"
              class="text-white cursor-pointer"
            />
          </div>
        </template>

        <v-card class="pa-0" style="width: 350px; overflow: hidden">
          <v-card-title class="text-subtitle-2 font-weight-bold">
            Notifications
          </v-card-title>
          <v-divider></v-divider>

          <!-- Notification list -->
          <v-list
            class="pa-0"
            :style="{
              maxHeight: showAll ? `${dropdownMaxHeight}px` : '300px',
              overflowY: 'auto',
            }"
          >
            <template v-if="visibleNotifications.length">
              <v-list-item
                v-for="(notif, i) in visibleNotifications"
                :key="i"
                class="py-3 px-4"
                :class="{ 'bg-blue-grey-lighten-4': !notif.read_at }"
                style="white-space: normal; word-wrap: break-word"
              >
                <div class="flex justify-between w-100 items-start">
                  <!-- Notification text -->
                  <div class="flex-1 pr-2">
                    <v-list-item-title
                      class="notification-title text-sm font-weight-medium mb-1"
                      :class="{ 'font-weight-bold': !notif.read_at }"
                    >
                      {{ notif.data.title }}
                    </v-list-item-title>

                    <v-list-item-subtitle
                      class="notification-message text-grey-darken-1"
                      :class="{ 'font-weight-bold': !notif.read_at }"
                    >
                      {{ notif.data.message }}
                    </v-list-item-subtitle>
                  </div>

                  <!-- Mark as Read button -->
                  <div>
                    <v-btn
                      v-if="!notif.read_at"
                      variant="text"
                      size="x-small"
                      color="primary"
                      class="ml-2"
                      @click="markAsRead(notif.id)"
                    >
                      Mark as Read
                    </v-btn>
                  </div>
                </div>
              </v-list-item>
            </template>

            <v-list-item v-else>
              <v-list-item-title>No notifications</v-list-item-title>
            </v-list-item>
          </v-list>

          <!-- Show more / less -->
          <v-divider></v-divider>
          <v-card-actions class="justify-center">
            <v-btn
              v-if="notifications.length > 5"
              variant="text"
              @click="toggleShowMore"
              size="small"
            >
              {{ showAll ? "Show less" : "Show more" }}
            </v-btn>
          </v-card-actions>
        </v-card>
      </v-menu>
      <!-- 🔔 End Notification Bell -->

      <v-menu>
        <template v-slot:activator="{ props }">
          <div class="d-flex align-center" v-bind="props">
            <!-- Notification Bell -->

            <!-- Employee Name -->
            <div
              class="text-white text-subtitle-2 cursor-pointer font-weight-bold me-3"
            >
              {{ $page.props.auth.name }}
            </div>
            <!-- Clickable Profile Image -->
            <div
              class="profile-section d-flex align-center me-3 cursor-pointer"
            >
              <img
                :src="profileImage"
                width="32"
                height="32"
                class="rounded-circle"
              />
            </div>

            <!-- Menu Button -->
            <!-- <v-btn icon="mdi-dots-vertical" style="color: white !important" /> -->
          </div>
        </template>
        <v-list nav class="text-center">
          <Link :href="route('my-account.index')">
            <v-list-item title="My Account"></v-list-item>
          </Link>
          <v-list-item title="Logout" @click.prevent="handleLogout()" />
        </v-list>
      </v-menu>
    </v-app-bar>

    <v-main class="page-background">
      <v-container fluid>
        <slot />
      </v-container>
    </v-main>
  </v-app>
</template>

<script>
import Logo from "../../images/dmmmsu-logo.png";
import ProfileImage from "../../images/sample-profile-images/female_prof_pic.jpg";

export default {
  data() {
    return {
      Logo,
      drawer: true,
      mini: false,
      ProfileImage,

      // Notification Menu
      notificationsMenu: false,
      unreadNotifications: [],
      notifications: [],
      showAll: false,
      echo: null,

      dropdownMaxHeight: 500, // default fallback
    };
  },

  async mounted() {
    await this.fetchNotifications();
    this.listenForNotifications();
  },

  computed: {
    visibleNotifications() {
      return this.showAll ? this.notifications : this.notifications.slice(0, 5);
    },

    profileImage() {
      const photo = this.$page.props.auth.user.employee?.photo;

      // Return full path if photo exists, otherwise default image
      return photo
        ? `/storage/${photo}` // assuming photo = 'profile_pictures/filename.jpg'
        : this.ProfileImage;
    },

    userRoles() {
      return this.$page.props.auth.roles || []; // e.g. ['superadmin', 'campus_hr']
    },

    navItems() {
      const hasRole = (role) => this.userRoles.includes(role);

      const groupedItems = [];

      if (
        hasRole("superadmin") ||
        hasRole("hr_director") ||
        hasRole("campus_hr") ||
        hasRole("employee")
      ) {
        // Superadmin and HR Director have access to all sections
        // Dashboard
        groupedItems.push({
          title: "Dashboard",
          items: [
            {
              icon: "mdi-view-dashboard",
              title: "Dashboard",
              route: "dashboard.index",
            },
          ],
        });
      }

      // employee dashboard
      // if (hasRole("employee")) {
      //   groupedItems.push({
      //     title: "Dashboard",
      //     items: [
      //       {
      //         icon: "mdi-view-dashboard",
      //         title: "Dashboard",
      //         route: "self-service.dashboard.index",
      //         routePrefix: "self-service.dashboard",
      //       },
      //     ],
      //   });
      // }

      // Administration
      if (
        hasRole("superadmin") ||
        hasRole("hr_director") ||
        hasRole("ict") ||
        hasRole("campus_hr") ||
        hasRole("campus_hr_staff")
      ) {
        groupedItems.push({
          title: "Administration",
          items: [
            {
              title: "User Management",
              icon: "mdi-account-multiple",
              route: "administration.user.index",
              permission: "user.view",
              routePrefix: "administration.user",
            },
            {
              title: "Organization",
              icon: "mdi-domain",
              route: "administration.organization.index",
              permission: "organization.view",
              routePrefix: "administration.organization",
            },
            {
              title: "Roles",
              icon: "mdi-shield-account",
              route: "role.management.index",
              permission: "role.view",
              routePrefix: "administration.role",
            },
            // {
            //   title: "Permissions",
            //   icon: "mdi-lock",
            //   route: "permission.management.index",
            //   permission: "permission.view",
            //   routePrefix: "administration.permission",
            // },
            {
              title: "Role Permissions",
              icon: "mdi-lock-question",
              route: "role-permission.management.index",
              permission: "permission.assign",
              routePrefix: "administration.role-permission",
            },
          ],
        });
      }

      // HR Management
      if (
        hasRole("superadmin") ||
        hasRole("hr_director") ||
        hasRole("campus_hr") ||
        hasRole("ict") ||
        this.hasPermission("dtr.view") ||
        this.hasPermission("dtr.print") ||
        this.hasPermission("leave.view") ||
        this.hasPermission("leave.recommend") ||
        this.hasPermission("leave.approve")
      ) {
        groupedItems.push({
          title: "HR Management",
          items: [
            {
              title: "Job Structure",
              icon: "mdi-office-building",
              route: "hrmanagement.jobstructure.jobstatus.index",
              permission: "job_structure.view",
              routePrefix: "hrmanagement.jobstructure",
            },
            {
              title: "Employees",
              icon: "mdi-account-multiple",
              route: "hrmanagement.employee.index",
              permission: "employee.view",
              routePrefix: "hrmanagement.employee",
            },
            {
              title: "Daily Time Record",
              icon: "mdi-clock",
              route: "hrmanagement.dailytimerecord.index",
              permission: "dtr.view",
              routePrefix: "hrmanagement.dailytimerecord.",
            },
            {
              title: "Leave Management",
              icon: "mdi-calendar",
              route: "hrmanagement.leave.leaveList",
              permission: "leave.view",
              routePrefix: "hrmanagement.leave",
            },
          ],
        });
      }

      // Faculty Evaluation Reports

      // if(
      //   hasRole("superadmin") ||
      //   hasRole("hr_director") ||
      //   hasRole("campus_hr") ||
      //   hasRole("campus_hr_staff")
      // ) {
      //   groupedItems.push({
      //     title: "Faculty Evaluation Reports",
      //     items: [
      //       {
      //         title: "Faculty Evaluation Reports",
      //         icon: "mdi-file-chart",
      //       },
      //     ],
      //   });
      // }

      // Payroll Officer
      /*if(hasRole('payroll') || hasRole('superadmin')){
        groupedItems.push({
          title: "Payroll",
          items: [
            {
              title: "Payroll Generation",
              icon: "mdi-calendar-blank",
              route: "payroll.generation.calendar.index",
              routePrefix: "payroll.generation.calendar",
            },
            {
              title: "Employee Maintenance",
              icon: "mdi-account-multiple",
              route: "payroll.employee.maintenance.accounts.index",
              routePrefix: "payroll.employee.maintenance.accounts",
            },
            {
              title: "Maintenance",
              icon: "mdi-cog-outline",
              route: "payroll.maintenance.accounttype.index",
              routePrefix: "payroll.maintenance.accounttype",
            },
          ],
        });
      }*/

      //Employee
      //   Self-Service
      if (!this.$page.props.auth.name == "") {
        groupedItems.push({
          title: "Self-Service",
          items: [
            {
              title: "My DTR",
              icon: "mdi-clock-time-four-outline",
              route: "self-service.my-dtr.index",
              permission: "dtr.self.view",
              routePrefix: "self-service.my-dtr",
            },
            {
              title: "My Leaves",
              icon: "mdi-calendar-account",
              route: "self-service.my-leaves.index",
              permission: "leave.self.view",
              routePrefix: "self-service.my-leaves",
            },

            {
              title: "My Profile",
              icon: "mdi-account",
              route: "self-service.my-profile.index",
              permission: "profile.view",
              routePrefix: "self-service.my-profile",
            },
          ],
        });
      }

      // // Payroll
      // if (
      //   hasRole("payroll_officer") ||
      //   hasRole("hr_director") ||
      //   hasRole("superadmin")
      // ) {
      //   groupedItems.push({
      //     title: "Payroll",
      //     items: [
      //       {
      //         title: "Payroll",
      //         icon: "mdi-cash-multiple",
      //         route: "dashboard.index",
      //       },
      //       {
      //         title: "Reports",
      //         icon: "mdi-file-chart",
      //         route: "dashboard.index",
      //       },
      //     ],
      //   });
      // }

      return groupedItems;
    },

    // activeMenuTitle() {
    //   const currentRoute = this.route().current(); // e.g., "users.edit"
    //   console.log(currentRoute);
    //   for (const group of this.navItems) {
    //     for (const item of group.items) {
    //       if (
    //         (!item.permission ||
    //           this.$page.props.auth.permissions.includes(item.permission)) &&
    //         this.route().current(item.route.routePrefix)
    //       ) {
    //         return item.title;
    //       }
    //     }
    //   }

    //   return "";
    // },

    activeMenuTitle() {
      const currentRoute = this.route().current();

      for (const group of this.navItems) {
        for (const item of group.items) {
          if (
            (!item.permission ||
              this.$page.props.auth.permissions.includes(item.permission)) &&
            this.route().current(`${item.routePrefix}*`) // wildcard here
          ) {
            return item.title;
          }
        }
      }

      return "";
    },

    // Dynamic top tabs/menu based on current main section
    currentTabs() {
      const tabsConfig = this.tabsConfigBySection;

      // Find active section by routePrefix wildcard match
      let activeSection = null;
      for (const sectionKey of Object.keys(tabsConfig)) {
        const section = tabsConfig[sectionKey];
        if (section.routePrefixes?.some((p) => this.route().current(`${p}*`))) {
          activeSection = sectionKey;
          break;
        }
      }

      if (!activeSection) return [];

      // Filter items by permission if specified
      const items = tabsConfig[activeSection].items || [];
      return items.filter((it) => {
        if (!it.permission) return true;
        return this.$page.props.auth.permissions.includes(it.permission);
      });
    },

    tabsConfigBySection() {
      return {
        // Leaves module top tabs
        hrmanagement_leave: {
          routePrefixes: [
            "hrmanagement.leave",
            "leaves.management",
            "leaves.entitlements",
            "leaves.leave-types",
          ],
          items: [
            {
              type: "menu",
              label: "Entitlements",
              items: [
                {
                  label: "Add Entitlements",
                  to: "leaves.entitlements.index",
                  permission: "manage leaves",
                },
                {
                  label: "Employee Entitlements",
                  to: "leaves.entitlements.employee-entitlements",
                  permission: "manage leaves",
                },
              ],
            },
            {
              type: "menu",
              label: "Configure",
              items: [
                {
                  label: "Leave Types",
                  to: "leaves.leave-types.index",
                  permission: "manage leaves",
                },
                { label: "Holidays" },
              ],
            },
          ],
        },

        // Employees module tabs
        hrmanagement_employee: {
          routePrefixes: ["hrmanagement.employee"],
          items: [
            {
              type: "link",
              label: "Employees List",
              to: "hrmanagement.employee.index",
              permission: "manage employees",
            },
            {
              type: "link",
              label: "Add Employee",
              to: "hrmanagement.employee.create",
              permission: "manage employees",
            },
          ],
        },

        // Job structure
        hrmanagement_jobstructure: {
          routePrefixes: ["hrmanagement.jobstructure"],
          items: [
            {
              type: "link",
              label: "Job Status",
              to: "hrmanagement.jobstructure.jobstatus.index",
              permission: "manage job status",
            },
            {
              type: "link",
              label: "Positions",
              to: "hrmanagement.jobstructure.position.index",
              permission: "manage positions",
            },
            {
              type: "link",
              label: "Designations",
              to: "hrmanagement.jobstructure.designation.index",
              permission: "manage designations",
            },
            {
              type: "link",
              label: "Salaries",
              to: "hrmanagement.jobstructure.salary.index",
              permission: "manage salary",
            },
          ],
        },

        // Administration
        administration: {
          routePrefixes: ["administration.user", "administration.organization"],
          items: [
            {
              type: "link",
              label: "Users",
              to: "administration.user.index",
              permission: "manage accounts",
            },
            {
              type: "link",
              label: "Organization",
              to: "administration.organization.index",
              permission: "manage operating units",
            },
          ],
        },

        // Self-Service
        self_service: {
          routePrefixes: [
            "self-service.dashboard",
            "self-service.dtr",
            "self-service.my-leaves",
            "self-service.my-profile",
          ],
          items: [
            { type: "link", label: "My DTR", to: "self-service.dtr.index" },
            {
              type: "link",
              label: "My Leaves",
              to: "self-service.my-leaves.index",
            },
            {
              type: "link",
              label: "My Profile",
              to: "self-service.my-profile.index",
            },
          ],
        },
      };
    },
  },

  methods: {
    toggleShowMore() {
      this.showAll = !this.showAll;
    },

    calculateDropdownHeight() {
      this.dropdownMaxHeight = window.innerHeight;
      console.log(this.dropdownMaxHeight);
      // this.$nextTick(() => {
      //   const bell = this.$el.querySelector(".v-badge"); // bell icon wrapper
      //   if (!bell) return;
      //   const bellRect = bell.getBoundingClientRect();
      //   const viewportHeight = window.innerHeight;
      //   const padding = 20; // optional padding from bottom
      //   this.dropdownMaxHeight = viewportHeight - bellRect.bottom - padding;
      // });
    },

    async fetchNotifications() {
      const res = await fetch("/notifications");
      const data = await res.json();
      this.notifications = data;
      this.unreadNotifications = data.filter((n) => !n.read_at);
    },

    listenForNotifications() {
      const employeeId = this.$page.props.auth.user.employee.id;

      window.Echo.private(`leave.status.${employeeId}`).notification(
        (notification) => {
          // console.log("New notification received:", notification);
          // this.showToast("You have a new notification");

          // Add to unread list
          this.unreadNotifications.unshift({
            id: notification.id,
            data: notification,
            read_at: null,
          });

          // Optionally also add to full list
          this.notifications.unshift({
            id: notification.id,
            data: notification,
            read_at: null,
          });
        }
      );
    },

    markAsRead(notifId = null) {
      this.$inertia.post(
        route("notifications.markAsRead"),
        { id: notifId },
        {
          preserveState: true,
          onSuccess: () => {
            // Update UI instantly without waiting for full reload
            if (notifId) {
              // Mark single notification as read
              const notif = this.notifications.find((n) => n.id === notifId);
              if (notif) notif.read_at = new Date().toISOString();
            } else {
              // Mark all as read
              this.notifications.forEach(
                (n) => (n.read_at = new Date().toISOString())
              );
            }

            // Recalculate unread notifications
            this.unreadNotifications = this.notifications.filter(
              (n) => !n.read_at
            );
          },
        }
      );
    },

    handleLogout() {
      this.$inertia.post(route("auth.logout"), "", {
        onSuccess: () => this.showToast("Logout successful."),
      });
    },

    hasPermission(permission) {
      // console.log("Checking permission:", permission);
      return this.$page.props.auth.permissions.includes(permission);
    },

    hasRoute(name) {
      return Object.keys(this.$page.props.ziggy.routes).includes(name);
    },
  },
};
</script>

<style scoped>
.gradient-bg {
  background: #006241;
  /* background: linear-gradient(90deg, rgba(0, 98, 65, 1) 0%, rgba(87, 199, 133, 1) 64%, rgba(242, 199, 27, 1) 97%); */
}

.page-background {
  /* Option 3: Subtle gradient with reduced opacity */
  background: linear-gradient(
    226deg,
    rgba(0, 98, 65, 0.1) 5%,
    rgba(87, 199, 133, 0.1) 55%,
    rgba(242, 199, 27, 0.1) 97%
  );
}

.tabs {
  position: sticky;
  top: 64px;
  z-index: 10;
  background-color: white;
  height: 50px;
}

.sidebar-header {
  position: sticky; /* Or use fixed if needed */
  top: 0;
  background-color: white; /* Match drawer background */
  z-index: 1;
  padding: 16px 0;
  border-bottom: 1px solid #eee;
}

.sidebar-content {
  flex: 1;
  overflow: hidden;
  padding-top: 8px;
}

.sidebar-list .v-list-item {
  min-height: 36px !important;
  font-size: 0.875rem;
}

/* Profile section styles */
.cursor-pointer {
  cursor: pointer;
}

.profile-section:hover {
  opacity: 0.8;
  transition: opacity 0.2s ease;
}

/* Navigation drawer rounded right edge */
.rounded-right {
  border-top-right-radius: 16px !important;
  border-bottom-right-radius: 20px !important;
}
.rounded-circle {
  border-radius: 50%;
  object-fit: cover;
  width: 35px;
  height: 35px;
}

.notification-title,
.notification-message {
  white-space: normal !important;
  word-break: break-word !important;
  overflow: visible !important;
  text-overflow: unset !important;
  display: block !important;
  line-height: 1.4;
}
</style>
