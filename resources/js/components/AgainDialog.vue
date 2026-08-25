<template>
  <v-dialog
    :model-value="modelValue"
    @update:model-value="$emit('update:modelValue', $event)"
    max-width="500"
  >
    <v-card class="pa-4 rounded-lg">
      <v-card-title
        class="d-flex align-center justify-center text-h5 font-weight-bold mb-4"
      >
        <v-icon color="success" size="large" class="mr-2">
          mdi-plus-circle
        </v-icon>
        Create Again
      </v-card-title>
      <v-divider class="mb-4"></v-divider>
      <v-card-text class="text-body-1 text-center">
        <p class="mb-2">{{ message }}</p>
        <p class="text-caption text-medium-emphasis">
          You can create another item with the same form.
        </p>
      </v-card-text>
      <v-card-actions class="d-flex justify-end gap-2 pa-4">
        <v-btn
          color="grey-darken-1"
          variant="outlined"
          @click="handleCancel"
          :loading="loading"
          min-width="120"
        >
          No, Thanks
        </v-btn>
        <v-btn
          color="success"
          variant="elevated"
          @click="handleConfirm"
          :loading="loading"
          min-width="120"
        >
          Create Again
        </v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>
</template>

<script>
export default {
  props: {
    modelValue: {
      type: Boolean,
      required: true,
    },
    message: {
      type: String,
      default: "Do you want to create another special leave type?",
    },
    loading: {
      type: Boolean,
      default: false,
    },
  },
  emits: ["update:modelValue", "confirm", "cancel"],
  methods: {
    handleConfirm() {
      this.$emit("confirm");
    },
    handleCancel() {
      this.$emit("update:modelValue", false);
      this.$emit("cancel");
    },
  },
};
</script>
