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
        <v-icon color="warning" size="large" class="mr-2">
          mdi-alert-circle
        </v-icon>
        Delete Confirmation
      </v-card-title>
      <v-divider class="mb-4"></v-divider>
      <v-card-text class="text-body-1 text-center">
        <p class="mb-2">{{ message }}</p>
        <p class="text-caption text-medium-emphasis">
          This action cannot be undone.
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
          Cancel
        </v-btn>
        <v-btn
          color="error"
          variant="elevated"
          @click="handleConfirm"
          :loading="loading"
          min-width="120"
        >
          Delete
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
      default: "Are you sure you want to delete this item?",
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