<template>
  <v-app>
    <!-- Top Navigation App Bar with Dynamic Brand Color -->
    <v-app-bar :elevation="1" color="primary" class="pe-2" height="64">
      <!-- App Brand / Dynamic Logo Section -->
      <div class="d-flex align-center ms-4 me-6 cursor-pointer">
        <img
          v-if="clientLogo"
          :src="clientLogo"
          width="40"
          height="40"
          class="me-2"
        />
        <div class="d-flex flex-column text-white">
          <span class="text-subtitle-2 font-weight-bold leading-tight">
            {{ $page.props.company?.shortcut || $page.props.company?.name }}
          </span>
          <span class="text-caption text-truncate leading-tight max-w-200">
            {{ $page.props.app_name }}
          </span>
        </div>
      </div>

      <v-divider
        vertical
        inset
        class="me-2 border-opacity-25 text-white d-none d-md-flex"
      ></v-divider>

      <!-- Desktop Dynamic Top Navigation Menu -->
      <div class="d-none d-md-flex align-center">
        <template
          v-for="(group, groupIndex) in visibleNavItems"
          :key="groupIndex"
        >
          <!-- Single Direct Navigation Item -->
          <Link
            v-if="!group.items"
            :href="route(group.route)"
            preserve-state
            class="text-decoration-none"
          >
            <v-btn
              variant="text"
              class="text-white text-capitalize me-1"
              :class="{
                'active-nav-btn': route().current(`${group.routePrefix}*`),
              }"
            >
              {{ group.title }}
            </v-btn>
          </Link>

          <!-- Dropdown Navigation Items -->
          <v-menu v-else open-on-hover offset-y transition="slide-y-transition">
            <template #activator="{ props }">
              <v-btn
                v-bind="props"
                variant="text"
                class="text-white text-capitalize me-1"
                :class="{ 'active-nav-btn': isGroupActive(group) }"
              >
                {{ group.title }}
                <v-icon icon="mdi-chevron-down" end size="small"></v-icon>
              </v-btn>
            </template>

            <v-list density="compact" elevation="4" class="py-1">
              <template
                v-for="(item, itemIndex) in group.items"
                :key="itemIndex"
              >
                <Link
                  :href="route(item.route)"
                  preserve-state
                  class="text-decoration-none text-high-emphasis"
                >
                  <v-list-item
                    link
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
            </v-list>
          </v-menu>
        </template>
      </div>

      <v-spacer></v-spacer>

      <!-- Right Action Items -->
      <div class="d-flex align-center">
        <!-- 🌓 Theme Mode Toggle Button -->
        <v-btn
          icon
          variant="text"
          class="me-3 text-white"
          size="small"
          @click="toggleThemeMode"
        >
          <v-icon
            :icon="isDarkMode ? 'mdi-weather-sunny' : 'mdi-weather-night'"
            size="24"
          />
        </v-btn>

        <!-- 🔔 Notification Bell -->
        <v-menu
          v-model="notificationsMenu"
          offset-y
          :close-on-content-click="false"
          @update:modelValue="handleMenuToggle"
          @open="calculateDropdownHeight"
        >
          <template #activator="{ props }">
            <div class="me-4 d-flex align-center" v-bind="props">
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
                  :class="{ 'bg-surface-variant': !notif.read_at }"
                  style="white-space: normal; word-wrap: break-word"
                >
                  <div class="flex justify-between w-100 items-start">
                    <div class="flex-1 pr-2">
                      <v-list-item-title
                        class="notification-title text-sm font-weight-medium mb-1"
                        :class="{ 'font-weight-bold': !notif.read_at }"
                      >
                        {{ notif.data.title }}
                      </v-list-item-title>

                      <v-list-item-subtitle
                        class="notification-message text-medium-emphasis"
                        :class="{ 'font-weight-bold': !notif.read_at }"
                      >
                        {{ notif.data.message }}
                      </v-list-item-subtitle>
                    </div>

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

        <!-- User Profile Dropdown -->
        <v-menu>
          <template v-slot:activator="{ props }">
            <div class="d-flex align-center cursor-pointer me-2" v-bind="props">
              <div
                class="text-white text-subtitle-2 font-weight-bold me-2 d-none d-sm-block"
              >
                {{ $page.props.auth.name }}
              </div>
              <div class="profile-section d-flex align-center me-1">
                <img
                  :src="profileImage"
                  width="32"
                  height="32"
                  class="rounded-circle"
                />
              </div>
              <v-icon icon="mdi-menu-down" color="white"></v-icon>
            </div>
          </template>
          <v-list nav class="text-center">
            <Link
              :href="route('my-account.index')"
              class="text-decoration-none text-high-emphasis"
            >
              <v-list-item title="My Account"></v-list-item>
            </Link>
            <v-list-item title="Logout" @click.prevent="handleLogout()" />
          </v-list>
        </v-menu>

        <!-- Mobile Menu Toggle Button -->
        <v-app-bar-nav-icon
          class="d-md-none text-white"
          @click="mobileDrawer = !mobileDrawer"
        ></v-app-bar-nav-icon>
      </div>
    </v-app-bar>

    <!-- Mobile Drawer -->
    <v-navigation-drawer v-model="mobileDrawer" temporary location="right">
      <v-list nav dense class="pa-2">
        <template
          v-for="(group, groupIndex) in visibleNavItems"
          :key="groupIndex"
        >
          <!-- Single Direct Link -->
          <Link
            v-if="!group.items"
            :href="route(group.route)"
            preserve-state
            class="text-decoration-none text-high-emphasis"
          >
            <v-list-item
              link
              class="py-2 px-4"
              :title="group.title"
              :class="{
                'v-list-item--active': route().current(`${group.routePrefix}*`),
              }"
            />
          </Link>

          <!-- Group Sub-items -->
          <template v-else>
            <v-list-subheader
              class="text-uppercase text-medium-emphasis font-weight-bold"
            >
              {{ group.title }}
            </v-list-subheader>

            <template v-for="(item, itemIndex) in group.items" :key="itemIndex">
              <Link
                :href="route(item.route)"
                preserve-state
                class="text-decoration-none text-high-emphasis"
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
        </template>
      </v-list>
    </v-navigation-drawer>

    <!-- Main Content Area -->
    <v-main class="page-background">
      <v-container fluid>
        <slot />
      </v-container>
    </v-main>
  </v-app>
</template>

<script>
import ProfileImage from "../../images/sample-profile-images/female_prof_pic.jpg";

export default {
  data() {
    return {
      ProfileImage,
      mobileDrawer: false,

      // Notification Menu
      notificationsMenu: false,
      unreadNotifications: [],
      notifications: [],
      showAll: false,
      echo: null,

      dropdownMaxHeight: 500,
    };
  },

  async mounted() {
    await this.fetchNotifications();
    this.listenForNotifications();
  },

  computed: {
    clientLogo() {
      let logo = this.$page.props.branding?.client_logo;
      if (!logo) return null;

      // Sanitization fallback in case DB stored raw physical paths
      return logo
        .replace("/storage/app/public/", "/storage/")
        .replace("storage/app/public/", "/storage/")
        .replace("/app/public/", "/storage/")
        .replace("app/public/", "/storage/");
    },

    isDarkMode() {
      return this.$vuetify.theme.global.current.dark;
    },

    visibleNotifications() {
      return this.showAll ? this.notifications : this.notifications.slice(0, 5);
    },

    profileImage() {
      const photo = this.$page.props.auth.user.employee?.photo;
      return photo ? `/storage/${photo}` : this.ProfileImage;
    },

    userRoles() {
      return this.$page.props.auth.roles || [];
    },

    userPermissions() {
      return this.$page.props.auth.permissions || [];
    },

    isAdmin() {
      return this.userRoles.some((r) =>
        ["superadmin", "administrator", "admin"].includes(r.toLowerCase())
      );
    },

    isHR() {
      return this.userRoles.some((r) =>
        ["hr_director", "campus_hr", "hr_officer", "hr"].includes(
          r.toLowerCase()
        )
      );
    },

    modules() {
      return {
        dashboard: {
          title: "Dashboard",
          route: "dashboard.index",
          routePrefix: "dashboard",
          permission: "dashboard.view",
        },

        myProfile: {
          title: "My Profile",
          route: "self-service.my-profile.index",
          routePrefix: "self-service.my-profile",
          permission: "profile.view_own",
        },

        myTime: {
          title: "My Time",
          items: [
            {
              title: "My Attendance",
              icon: "mdi-clock-outline",
              route: "self-service.my-dtr.index",
              permission: "attendance.view_own",
              routePrefix: "self-service.my-dtr",
            },
            {
              title: "My Schedule",
              icon: "mdi-calendar-clock",
              route: "self-service.my-schedule.index",
              permission: "schedule.view_own",
              routePrefix: "self-service.my-schedule",
            },
          ],
        },

        myLeave: {
          title: "My Leave",
          items: [
            {
              title: "My Leave Applications",
              icon: "mdi-calendar-text",
              route: "self-service.my-leaves.index",
              permission: "leave.view_own",
              routePrefix: "self-service.my-leaves",
            },
            {
              title: "My Leave Balances",
              icon: "mdi-scale-balance",
              route: "self-service.my-leave-balances.index",
              permission: "leave.view_balance_own",
              routePrefix: "self-service.my-leave-balances",
            },
          ],
        },

        people: {
          title: "People",
          items: [
            {
              title: "Employees",
              icon: "mdi-account-multiple",
              route: "people.employee.index",
              permission: "employee.view",
              routePrefix: "people.employee",
            },
            {
              title: "Job Titles",
              icon: "mdi-briefcase-outline",
              route: "people.job-title.index",
              permission: "job_title.view",
              routePrefix: "people.job-title",
            },
            {
              title: "Employment Status",
              icon: "mdi-account-details",
              route: "people.employment-status.index",
              permission: "employee.employment.view",
              routePrefix: "hrmanagement.jobstructure.jobstatus",
            },
            {
              title: "Employee Movements",
              icon: "mdi-account-switch",
              route: "hrmanagement.employee.movement.index",
              permission: "employee_movement.view",
              routePrefix: "hrmanagement.employee.movement",
            },
          ],
        },

        time: {
          title: "Time",
          items: [
            {
              title: "Attendance",
              icon: "mdi-clock-outline",
              route: "hrmanagement.time.index",
              permission: "attendance.view",
              routePrefix: "hrmanagement.dailytimerecord",
            },
            {
              title: "Work Shifts",
              icon: "mdi-timetable",
              route: "time.shifts.index",
              permission: "shift.view",
              routePrefix: "time.shifts",
            },
            {
              title: "Work Schedules",
              icon: "mdi-calendar-clock",
              route: "time.work-schedules.index",
              permission: "work_schedule.view",
              routePrefix: "time.schedules",
            },
            {
              title: "Employee Schedules",
              icon: "mdi-calendar-clock",
              route: "time.employee-schedules.index",
              permission: "work_schedule.view",
              routePrefix: "time.schedules",
            },
            {
              title: "Holidays",
              icon: "mdi-calendar-star",
              route: "hrmanagement.holidays.index",
              permission: "holiday.view",
              routePrefix: "leaves.holidays",
            },
          ],
        },

        leave: {
          title: "Leave",
          items: [
            {
              title: "Leave Applications",
              icon: "mdi-calendar-text",
              route: "hrmanagement.leaves.leaveList",
              permission: "leave.view",
              routePrefix: "hrmanagement.leave",
            },
            {
              title: "Leave Types",
              icon: "mdi-shape-outline",
              route: "hrmanagement.leaves.leave-types.index",
              permission: "leave_policy.view",
              routePrefix: "leaves.leave-types",
            },
            {
              title: "Leave Policies",
              icon: "mdi-file-document-outline",
              route: "hrmanagement.leaves.policies.index",
              permission: "leave_policy.view",
              routePrefix: "leaves.policies",
            },
            {
              title: "Leave Balances",
              icon: "mdi-scale-balance",
              route: "hrmanagement.leaves.entitlements.index",
              permission: "leave.balance.view",
              routePrefix: "leaves.entitlements",
            },
          ],
        },

        payroll: {
          title: "Payroll",
          items: [],
        },

        reports: {
          title: "Reports",
          items: [],
        },

        administration: {
          title: "Administration",
          items: [
            {
              title: "Organization",
              icon: "mdi-domain",
              route: "administration.organization.index",
              permission: "organization.view",
              routePrefix: "administration.organization",
            },
            {
              title: "User Management",
              icon: "mdi-account-cog", // Valid @mdi/font icon name
              route: "administration.user.index",
              permission: "user.view",
              routePrefix: "administration.user",
            },
            {
              title: "Roles & Permissions",
              icon: "mdi-shield-account-outline",
              route: "administration.role.index",
              permission: "role.view",
              routePrefix: "administration.role",
            },
          ],
        },
      };
    },

    navItems() {
      if (this.isAdmin) {
        return [
          this.modules.dashboard,
          this.modules.people,
          this.modules.time,
          this.modules.leave,
          this.modules.payroll,
          this.modules.reports,
          this.modules.administration,
        ];
      }

      if (this.isHR) {
        return [
          this.modules.dashboard,
          this.modules.people,
          this.modules.time,
          this.modules.leave,
          this.modules.reports,
        ];
      }

      return [
        this.modules.dashboard,
        this.modules.myProfile,
        this.modules.myTime,
        this.modules.myLeave,
      ];
    },

    visibleNavItems() {
      return this.navItems
        .map((group) => {
          if (!group.items) {
            return this.hasPermission(group.permission) ? group : null;
          }

          const filteredItems = group.items.filter((item) =>
            this.hasPermission(item.permission)
          );

          if (filteredItems.length === 0) return null;

          return {
            ...group,
            items: filteredItems,
          };
        })
        .filter(Boolean);
    },
  },

  methods: {
    toggleThemeMode() {
      const nextTheme = this.isDarkMode ? "light" : "dark";
      this.$vuetify.theme.global.name = nextTheme;
      localStorage.setItem("user_theme_mode", nextTheme);
    },

    hasPermission(permission) {
      if (!permission) return true;
      return this.userPermissions.includes(permission);
    },

    isGroupActive(group) {
      if (!group.items) return false;
      return group.items.some((item) =>
        this.route().current(`${item.routePrefix}*`)
      );
    },

    toggleShowMore() {
      this.showAll = !this.showAll;
    },

    calculateDropdownHeight() {
      this.dropdownMaxHeight = window.innerHeight;
    },

    async fetchNotifications() {
      const res = await fetch("/notifications");
      const data = await res.json();
      this.notifications = data;
      this.unreadNotifications = data.filter((n) => !n.read_at);
    },

    listenForNotifications() {
      const employeeId = this.$page.props.auth.user.employee?.id;
      if (!employeeId) return;

      window.Echo.private(`leave.status.${employeeId}`).notification(
        (notification) => {
          this.unreadNotifications.unshift({
            id: notification.id,
            data: notification,
            read_at: null,
          });

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
            if (notifId) {
              const notif = this.notifications.find((n) => n.id === notifId);
              if (notif) notif.read_at = new Date().toISOString();
            } else {
              this.notifications.forEach(
                (n) => (n.read_at = new Date().toISOString())
              );
            }

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

    hasRoute(name) {
      return Object.keys(this.$page.props.ziggy.routes).includes(name);
    },
  },
};
</script>

<style scoped>
.cursor-pointer {
  cursor: pointer;
}

.profile-section:hover {
  opacity: 0.8;
  transition: opacity 0.2s ease;
}

.rounded-circle {
  border-radius: 50%;
  object-fit: cover;
  width: 32px;
  height: 32px;
}

.max-w-200 {
  max-width: 200px;
}

.active-nav-btn {
  background-color: rgba(255, 255, 255, 0.22);
  font-weight: bold;
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

.page-background {
  transition: background-color 0.3s ease;
}

:deep(.v-theme--light .page-background) {
  background-color: rgba(var(--v-theme-primary), 0.04);
}

:deep(.v-theme--dark .page-background) {
  background-color: #121212;
}
</style>
