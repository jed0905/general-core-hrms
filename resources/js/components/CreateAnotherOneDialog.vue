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
          mdi-check-circle
        </v-icon>
        Success!
      </v-card-title>
      <v-divider class="mb-4"></v-divider>
      <v-card-text class="text-body-1 text-center">
        <p class="mb-2">
          {{ message || "Item has been created successfully!" }}
        </p>
        <p class="text-caption text-medium-emphasis">
          Would you like to create another one?
        </p>
      </v-card-text>
      <v-card-actions class="d-flex justify-end gap-2 pa-4">
        <v-btn
          color="grey-darken-1"
          variant="outlined"
          @click="handleCancel()"
          min-width="120"
        >
          No
        </v-btn>
        <v-btn
          color="success"
          variant="elevated"
          @click="handleConfirm()"
          min-width="120"
        >
          Yes
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
      default: "",
    },
  },
  emits: ["update:modelValue", "confirm", "cancel"],
  methods: {
    handleConfirm() {
      this.$emit("confirm");
      this.$emit("update:modelValue", false);
    },
    handleCancel() {
      this.$emit("cancel");
      this.$emit("update:modelValue", false);
    },
  },
};
</script>