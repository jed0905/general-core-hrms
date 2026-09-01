<template>
  <SidebarLayout>
    <Head :title="`Edit Shift - ${shift.name}`" />

    <v-container fluid class="pa-6">
      <!-- Header -->
      <div
        class="d-flex align-center justify-space-between flex-wrap ga-3 mb-6"
      >
        <div class="d-flex align-center ga-3">
          <Link :href="route('time.shifts.index')">
            <v-btn
              icon="mdi-arrow-left"
              variant="text"
              density="comfortable"
              color="medium-emphasis"
            />
          </Link>
          <div>
            <h1 class="text-h5 font-weight-bold text-high-emphasis">
              Edit Work Shift
            </h1>
            <p class="text-body-2 text-medium-emphasis">
              Update working hours and shift settings for {{ shift.name }}.
            </p>
          </div>
        </div>

        <div class="d-flex align-center ga-2">
          <Link :href="route('time.shifts.index')">
            <v-btn variant="outlined" color="medium-emphasis" size="small">
              Cancel
            </v-btn>
          </Link>
          <v-btn
            color="primary"
            size="small"
            elevation="0"
            :loading="form.processing"
            @click="submit"
          >
            Update Shift
          </v-btn>
        </div>
      </div>

      <!-- Main Form -->
      <form @submit.prevent="submit">
        <v-row>
          <!-- Left Column: Shift Details -->
          <v-col cols="12" md="6">
            <v-card variant="outlined" class="rounded-lg bg-surface h-100">
              <v-card-title
                class="text-subtitle-1 font-weight-bold py-3 px-5 border-b"
              >
                Shift Details
              </v-card-title>

              <v-card-text class="pa-5">
                <v-row density="compact">
                  <v-col cols="12">
                    <v-text-field
                      v-model="form.name"
                      label="Shift Name *"
                      placeholder="e.g. Regular Day Shift"
                      variant="outlined"
                      density="compact"
                      :error-messages="form.errors.name"
                      required
                    />
                  </v-col>

                  <v-col cols="12">
                    <v-text-field
                      v-model="form.code"
                      label="Shift Code *"
                      placeholder="e.g. SHIFT-DS"
                      variant="outlined"
                      density="compact"
                      :error-messages="form.errors.code"
                      required
                    />
                  </v-col>

                  <v-col cols="12">
                    <v-textarea
                      v-model="form.description"
                      label="Description"
                      placeholder="Optional shift description or note..."
                      variant="outlined"
                      density="compact"
                      rows="4"
                      :error-messages="form.errors.description"
                    />
                  </v-col>

                  <v-col cols="12">
                    <v-select
                      v-model="form.is_active"
                      label="Status"
                      :items="statusOptions"
                      item-title="title"
                      item-value="value"
                      variant="outlined"
                      density="compact"
                      :error-messages="form.errors.is_active"
                    />
                  </v-col>
                </v-row>
              </v-card-text>
            </v-card>
          </v-col>

          <!-- Right Column: Working Hours -->
          <v-col cols="12" md="6">
            <v-card variant="outlined" class="rounded-lg bg-surface h-100">
              <v-card-title
                class="text-subtitle-1 font-weight-bold py-3 px-5 border-b"
              >
                Working Hours
              </v-card-title>

              <v-card-text class="pa-5">
                <v-row density="compact">
                  <v-col cols="12" sm="6">
                    <v-text-field
                      v-model="form.start_time"
                      label="Start Time *"
                      type="time"
                      variant="outlined"
                      density="compact"
                      :error-messages="form.errors.start_time"
                      required
                      @change="calculateHours"
                    />
                  </v-col>

                  <v-col cols="12" sm="6">
                    <v-text-field
                      v-model="form.end_time"
                      label="End Time *"
                      type="time"
                      variant="outlined"
                      density="compact"
                      :error-messages="form.errors.end_time"
                      required
                      @change="calculateHours"
                    />
                  </v-col>

                  <v-col cols="12" sm="6">
                    <v-text-field
                      v-model="form.break_start"
                      label="Break Start"
                      type="time"
                      variant="outlined"
                      density="compact"
                      :error-messages="form.errors.break_start"
                      @change="calculateHours"
                    />
                  </v-col>

                  <v-col cols="12" sm="6">
                    <v-text-field
                      v-model="form.break_end"
                      label="Break End"
                      type="time"
                      variant="outlined"
                      density="compact"
                      :error-messages="form.errors.break_end"
                      @change="calculateHours"
                    />
                  </v-col>

                  <v-col cols="12">
                    <v-text-field
                      v-model.number="form.required_hours"
                      label="Required Hours"
                      type="number"
                      step="0.25"
                      min="0"
                      max="24"
                      suffix="hours"
                      variant="outlined"
                      density="compact"
                      :error-messages="form.errors.required_hours"
                    />
                  </v-col>

                  <v-col cols="12" class="pt-2">
                    <v-switch
                      v-model="form.is_overnight"
                      label="Overnight Shift"
                      color="indigo"
                      density="compact"
                      hide-details
                      class="mb-1"
                      @change="calculateHours"
                    />
                    <v-switch
                      v-model="form.is_flexible"
                      label="Flexible Working Hours"
                      color="teal"
                      density="compact"
                      hide-details
                    />
                  </v-col>
                </v-row>
              </v-card-text>
            </v-card>
          </v-col>
        </v-row>
      </form>
    </v-container>
  </SidebarLayout>
</template>

<script>
import { Head, Link, useForm } from "@inertiajs/vue3";
import SidebarLayout from "@/Layouts/SidebarLayout.vue";

export default {
  name: "ShiftEdit",

  components: {
    SidebarLayout,
    Head,
    Link,
  },

  props: {
    shift: {
      type: Object,
      required: true,
    },
  },

  data() {
    return {
      statusOptions: [
        { title: "Active", value: true },
        { title: "Inactive", value: false },
      ],

      form: useForm({
        name: this.shift.name || "",
        code: this.shift.code || "",
        description: this.shift.description || "",
        start_time: this.formatTime(this.shift.start_time),
        end_time: this.formatTime(this.shift.end_time),
        break_start: this.formatTime(this.shift.break_start),
        break_end: this.formatTime(this.shift.break_end),
        required_hours:
          this.shift.required_hours !== null
            ? Number(this.shift.required_hours)
            : 8.0,
        is_overnight: Boolean(this.shift.is_overnight),
        is_flexible: Boolean(this.shift.is_flexible),
        is_active: Boolean(this.shift.is_active),
      }),
    };
  },

  methods: {
    formatTime(val) {
      return val ? val.substring(0, 5) : "";
    },

    calculateHours() {
      if (!this.form.start_time || !this.form.end_time) return;

      const [startH, startM] = this.form.start_time.split(":").map(Number);
      const [endH, endM] = this.form.end_time.split(":").map(Number);

      let totalMinutes = endH * 60 + endM - (startH * 60 + startM);

      if (totalMinutes <= 0 || this.form.is_overnight) {
        if (totalMinutes <= 0) {
          totalMinutes += 24 * 60;
        }
      }

      let breakMinutes = 0;
      if (this.form.break_start && this.form.break_end) {
        const [bStartH, bStartM] = this.form.break_start.split(":").map(Number);
        const [bEndH, bEndM] = this.form.break_end.split(":").map(Number);

        breakMinutes = bEndH * 60 + bEndM - (bStartH * 60 + bStartM);
        if (breakMinutes < 0) {
          breakMinutes += 24 * 60;
        }
      }

      const netMinutes = Math.max(0, totalMinutes - breakMinutes);
      this.form.required_hours = parseFloat((netMinutes / 60).toFixed(2));
    },

    submit() {
      this.form.put(route("time.shifts.update", this.shift.id), {
        onSuccess: () => {
          this.showToast("Work shift updated successfully!", "success");
        },
        onError: (errors) => {
          const errorCount = Object.keys(errors).length;
          this.showToast(
            `Failed to update shift. Please check ${errorCount} input field(s).`,
            "error"
          );
        },
      });
    },
  },
};
</script>
