<template>
  <div class="mb-10 d-flex gap-5">
    <!-- <v-btn
      :color="true ? 'light-green-lighten-4' : 'grey-lighten-3'"
      @click="toggleState"
      :text-color="true ? 'light-green-darken-4' : 'grey-darken-2'"
      class="text-none text-green rounded-lg"
      min-width="120"
    >
      Apply
    </v-btn>

    <Link
      :href="route('employee.management.create')"
      class="text-decoration-none"
    >
      <v-btn
        :color="false ? 'success' : 'grey-lighten-3'"
        @click="toggleState"
        class="text-none text-grey rounded-lg"
        min-width="120"
      >
        My Leave
      </v-btn>
    </Link> -->

    <v-menu :offset="[10, 0]">
      <template #activator="{ props }">
        <v-btn
          :color="false ? 'light-green-lighten-4' : 'grey-lighten-3'"
          class="text-none text-grey rounded-lg"
          v-bind="props"
          min-width="120"
        >
          Entitlements
          <v-icon end>mdi-menu-down</v-icon>
        </v-btn>
      </template>

      <v-list>
        <v-list-item :href="route('leaves.entitlements.index')">
          <v-list-item-title>Add Entitlements</v-list-item-title>
        </v-list-item>

        <v-list-item :href="route('leaves.entitlements.employee-entitlements')">
          <v-list-item-title>Employee Entitlements</v-list-item-title>
        </v-list-item>
      </v-list>
    </v-menu>

    <v-menu :offset="[10, 0]">
      <template #activator="{ props }">
        <v-btn
          :color="false ? 'light-green-lighten-4' : 'grey-lighten-3'"
          class="text-none text-grey rounded-lg"
          v-bind="props"
          min-width="120"
        >
          Configure
          <v-icon end>mdi-menu-down</v-icon>
        </v-btn>
      </template>

      <v-list>

        <v-list-item :href="route('leaves.leave-types.index')">
          <v-list-item-title>Leave Types</v-list-item-title>
        </v-list-item>

        <v-list-item>
          <v-list-item-title>Holidays</v-list-item-title>
        </v-list-item>
      </v-list>
    </v-menu>

    <!-- <Link
      :href="route('employee.management.create')"
      class="text-decoration-none"
    >
      <v-btn
        :color="false ? 'success' : 'grey-lighten-3'"
        @click="toggleState"
        class="text-none text-grey rounded-lg"
        min-width="120"
      >
        Leave List
      </v-btn>
    </Link> -->

    <!-- <v-menu :offset="[10, 0]">
      <template #activator="{ props }">
        <v-btn
          :color="false ? 'light-green-lighten-4' : 'grey-lighten-3'"
          class="text-none text-grey rounded-lg"
          v-bind="props"
          min-width="120"
        >
          Configure
          <v-icon end>mdi-menu-down</v-icon>
        </v-btn>
      </template>

      <v-list>
        <v-list-item @click="selectOption('Profile')">
          <v-list-item-title>Add Entitlements</v-list-item-title>
        </v-list-item>

        <v-list-item @click="selectOption('Settings')">
          <v-list-item-title>Employee Entitlements</v-list-item-title>
        </v-list-item>

        <v-list-item @click="selectOption('Logout')">
          <v-list-item-title>My Entitlements</v-list-item-title>
        </v-list-item>
      </v-list>
    </v-menu> -->
  </div>
</template>
<script>
export default {
  
}
</script>
