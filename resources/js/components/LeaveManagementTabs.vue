<template>
  <div class="mb-10 d-flex gap-5">
    <!-- <Link
      :href="route('hrmanagement.leave.assignLeave')"
      class="text-decoration-none"
    >
      <v-btn
        :color="
          activeTab === 'Assign Leave'
            ? 'light-green-lighten-4'
            : 'grey-lighten-3'
        "
        :text-color="
          activeTab === 'Assign Leave' ? 'light-green-darken-4' : 'grey-darken-2'
        "
        @click="updateActiveTab('Assign Leave')"
        class="text-none rounded-lg"
        min-width="120"
      >
        Assign Leave
      </v-btn>
    </Link> -->
    <Link
      :href="route('hrmanagement.leave.leaveList')"
      class="text-decoration-none"
    >
      <v-btn
        :color="
          activeTab === 'leave-list'
            ? 'light-green-lighten-4'
            : 'grey-lighten-3'
        "
        :text-color="
          activeTab === 'leave-list' ? 'light-green-darken-4' : 'grey-darken-2'
        "
        @click="updateActiveTab('leave-list')"
        class="text-none rounded-lg"
        min-width="120"
      >
        Leave List
      </v-btn>
    </Link>

    <v-menu :offset="[10, 0]" v-if="$page.props.auth.roles[0] != 'employee'">
      <template #activator="{ props }">
        <v-btn
          :color="
            activeTab === 'entitlements'
              ? 'light-green-lighten-4'
              : 'grey-lighten-3'
          "
          class="text-none rounded-lg"
          v-bind="props"
          min-width="120"
        >
          Entitlements
          <v-icon end>mdi-menu-down</v-icon>
        </v-btn>
      </template>

      <v-list>
        <v-list-item>
          <Link :href="route('hrmanagement.leave.index')" class="w-full">
            <v-list-item-title>Employee Entitlements</v-list-item-title>
          </Link>
        </v-list-item>

        <v-list-item>
          <Link
            :href="route('hrmanagement.leave.addLeaveEntitlements')"
            class="w-full"
          >
            <v-list-item-title>Add Entitlements</v-list-item-title>
          </Link>
        </v-list-item>
      </v-list>
    </v-menu>

    <v-menu :offset="[10, 0]" v-if="$page.props.auth.roles[0] != 'employee'">
      <template #activator="{ props }">
        <v-btn
          :color="
            activeTab === 'configure'
              ? 'light-green-lighten-4'
              : 'grey-lighten-3'
          "
          class="text-none rounded-lg"
          v-bind="props"
          min-width="120"
        >
          Configure
          <v-icon end>mdi-menu-down</v-icon>
        </v-btn>
      </template>

      <v-list>
        <!-- Mobile-friendly nested dropdown -->
        <v-menu
          location="right"
          :offset="[0, 0]"
          :close-on-content-click="false"
          :close-delay="200"
          @update:model-value="handleSubmenuToggle"
        >
          <template #activator="{ props }">
            <v-list-item
              v-bind="props"
              class="hover-bg-grey-lighten-4"
              @click.stop="toggleSubmenu"
              append-icon="mdi-chevron-right"
            >
              <v-list-item-title>Leave Types</v-list-item-title>

            </v-list-item>
          </template>

          <v-list>
            <v-list-item>
              <Link :href="route('hrmanagement.leave.leaveType.index')" class="w-full">
                <v-list-item-title>Leave Types</v-list-item-title>
              </Link>
            </v-list-item>
            <v-list-item>
              <Link :href="route('hrmanagement.leave.specialLeave.index')" class="w-full">
                <v-list-item-title>Special Leave Types</v-list-item-title>
              </Link>
            </v-list-item>

          </v-list>
        </v-menu>

        <v-list-item>
          <Link :href="route('hrmanagement.leave.scheduler.index')" class="w-full">
            <v-list-item-title>Leave Scheduler</v-list-item-title>
          </Link>
        </v-list-item>

        <v-list-item>
          <Link
            :href="route('hrmanagement.universityActivities.index')"
            class="w-full"
          >
            <v-list-item-title>University Activities</v-list-item-title>
          </Link>
        </v-list-item>

        <v-list-item>
          <Link :href="route('hrmanagement.holiday.index')" class="w-full">
            <v-list-item-title>Holidays</v-list-item-title>
          </Link>
        </v-list-item>
      </v-list>
    </v-menu>
  </div>
</template>
<script>
export default {
  props: {
    activeTab: {
      type: String,
      required: true,
    },
  },
  data() {
    return {
      submenuOpen: false,
    };
  },
  methods: {
    toggleSubmenu() {
      // For mobile: toggle on click
      if (this.isMobile()) {
        this.submenuOpen = !this.submenuOpen;
      }
    },
    handleSubmenuToggle(isOpen) {
      this.submenuOpen = isOpen;
    },
    isMobile() {
      return window.innerWidth <= 768;
    },
  },
};
</script>

<style scoped>
.rotate-90 {
  transform: rotate(90deg);
  transition: transform 0.2s ease;
}
</style>
