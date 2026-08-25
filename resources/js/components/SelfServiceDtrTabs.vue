<template>
  <div class="mb-10 d-flex gap-5">
    <Link
      :href="route('user.management.index')"
      class="text-decoration-none"
    >
      <v-btn
        :color="
          activeTab === 'list' ? 'light-green-lighten-4' : 'grey-lighten-3'
        "
        @click="$emit('update:activeTab', 'list')"
        :text-color="
          activeTab === 'list' ? 'light-green-darken-4' : 'grey-darken-2'
        "
        class="text-none rounded-lg"
        min-width="120"
      >
        User List
      </v-btn>
    </Link>

    <Link
      :href="route('user.management.create')"
      class="text-decoration-none"
    >
      <v-btn
        :color="
          activeTab === 'add' ? 'light-green-lighten-4' : 'grey-lighten-3'
        "
        @click="$emit('update:activeTab', 'add')"
        :text-color="
          activeTab === 'add' ? 'light-green-darken-4' : 'grey-darken-2'
        "
        class="text-none rounded-lg"
        min-width="120"
      >
        Add User
      </v-btn>
    </Link>

   
  </div>
</template>

<script>
export default {
  name: "SelfServiceDtrTabs",
  props: {
    activeTab: {
      type: String,
      required: true,
    },
  },
};
</script>
