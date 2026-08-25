<template>
  <div class="mb-10 d-flex gap-5">
    <Link
      :href="route('administration.user.index')"
      class="text-decoration-none"
    >
      <v-btn
        :color="
          activeTab === 'userIndex' ? 'light-green-lighten-4' : 'grey-lighten-3'
        "
        @click="$emit('update:activeTab', 'userIndex')"
        :text-color="
          activeTab === 'userIndex' ? 'light-green-darken-4' : 'grey-darken-2'
        "
        class="text-none rounded-lg"
        min-width="120"
      >
        User List
      </v-btn>
    </Link>

    <Link
      :href="route('administration.user.create')"
      class="text-decoration-none"
    >
      <v-btn
        :color="
          activeTab === 'userAdd' ? 'light-green-lighten-4' : 'grey-lighten-3'
        "
        @click="$emit('update:activeTab', 'userAdd')"
        :text-color="
          activeTab === 'userAdd' ? 'light-green-darken-4' : 'grey-darken-2'
        "
        class="text-none rounded-lg"
        min-width="120"
      >
        Add User
      </v-btn>
    </Link>

    <!-- <v-btn
      :color="
        activeTab === 'reports' ? 'light-green-lighten-4' : 'grey-lighten-3'
      "
      @click="$emit('update:activeTab', 'reports')"
      :text-color="
        activeTab === 'reports' ? 'light-green-darken-4' : 'grey-darken-2'
      "
      class="text-none rounded-lg"
      min-width="120"
    >
      Reports
    </v-btn> -->
  </div>
</template>

<script>
export default {
  name: "EmployeeTabs",
  props: {
    activeTab: {
      type: String,
      required: true,
    },
  },
};
</script>
