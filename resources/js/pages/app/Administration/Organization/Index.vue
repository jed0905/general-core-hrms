<template>
  <v-container fluid class="pa-6 max-width-xl">
    <!-- Page Header Hero Block -->
    <v-card
      variant="flat"
      class="mb-6 rounded-xl border bg-surface pa-6 position-relative overflow-hidden"
    >
      <div
        class="d-flex flex-column flex-sm-row align-sm-center justify-space-between ga-4"
      >
        <div>
          <div class="d-flex align-center ga-2 mb-1">
            <v-chip
              color="primary"
              variant="tonal"
              size="small"
              class="font-weight-medium"
            >
              Administration
            </v-chip>
          </div>
          <h1
            class="text-h4 font-weight-bold text-high-emphasis tracking-tight"
          >
            {{ general.name || "Organization Settings" }}
          </h1>
          <p class="text-body-1 text-medium-emphasis mt-1">
            Manage company details, corporate identity, branch locations, and
            organizational hierarchy.
          </p>
        </div>
      </div>
    </v-card>

    <!-- Main Navigation & Content Container -->
    <v-card variant="flat" class="rounded-xl border bg-surface overflow-hidden">
      <!-- Clean Segmented Tabs -->
      <v-tabs
        v-model="activeTab"
        color="primary"
        align-tabs="start"
        class="px-4 border-b"
        height="56"
      >
        <v-tab value="general" class="text-none font-weight-medium px-4">
          <v-icon icon="mdi-domain" size="20" class="me-2" />
          General Info
        </v-tab>
        <v-tab value="locations" class="text-none font-weight-medium px-4">
          <v-icon
            icon="mdi-map-marker-multiple-outline"
            size="20"
            class="me-2"
          />
          Locations / Branches
          <v-chip size="x-small" color="primary" variant="tonal" class="ms-2">
            {{ locations.length }}
          </v-chip>
        </v-tab>
        <v-tab value="branding" class="text-none font-weight-medium px-4">
          <v-icon icon="mdi-palette-outline" size="20" class="me-2" />
          Branding & Theme
        </v-tab>
        <v-tab value="departments" class="text-none font-weight-medium px-4">
          <v-icon icon="mdi-sitemap-outline" size="20" class="me-2" />
          Departments
          <v-chip size="x-small" color="primary" variant="tonal" class="ms-2">
            {{ departments.length }}
          </v-chip>
        </v-tab>
      </v-tabs>

      <v-card-text class="pa-6">
        <v-window v-model="activeTab" transition="fade-transition">
          <!-- TAB 1: General Information -->
          <v-window-item value="general">
            <v-form ref="generalForm" @submit.prevent="saveGeneralInfo">
              <div
                class="text-subtitle-2 font-weight-bold text-uppercase text-medium-emphasis mb-4"
              >
                Organization Overview & Contact Details
              </div>

              <v-row>
                <v-col cols="12" md="6">
                  <v-text-field
                    v-model="general.name"
                    label="Organization Name"
                    placeholder="e.g. Acme Corporation"
                    prepend-inner-icon="mdi-office-building-outline"
                    variant="outlined"
                    density="comfortable"
                    rounded="lg"
                    required
                  />
                </v-col>

                <v-col cols="12" md="6">
                  <v-text-field
                    v-model="general.shortcut"
                    label="Organization Shortcut / Abbreviation"
                    placeholder="e.g. Acme Corporation"
                    prepend-inner-icon="mdi-office-building-outline"
                    variant="outlined"
                    density="comfortable"
                    rounded="lg"
                    required
                  />
                </v-col>

                <v-col cols="12" md="6">
                  <v-text-field
                    v-model="general.email"
                    label="Email Address"
                    type="email"
                    prepend-inner-icon="mdi-email-outline"
                    variant="outlined"
                    density="comfortable"
                    rounded="lg"
                  />
                </v-col>

                <v-col cols="12" md="6">
                  <v-text-field
                    v-model="general.phone"
                    label="Phone Number"
                    prepend-inner-icon="mdi-phone-outline"
                    variant="outlined"
                    density="comfortable"
                    rounded="lg"
                  />
                </v-col>

                <v-col cols="12" md="6">
                  <v-text-field
                    v-model="general.street1"
                    label="Street Address Line 1"
                    prepend-inner-icon="mdi-map-marker-outline"
                    variant="outlined"
                    density="comfortable"
                    rounded="lg"
                  />
                </v-col>

                <v-col cols="12" md="6">
                  <v-text-field
                    v-model="general.street2"
                    label="Street Address Line 2 (Optional)"
                    variant="outlined"
                    density="comfortable"
                    rounded="lg"
                  />
                </v-col>

                <v-col cols="12" sm="6" md="3">
                  <v-text-field
                    v-model="general.city"
                    label="City"
                    variant="outlined"
                    density="comfortable"
                    rounded="lg"
                  />
                </v-col>

                <v-col cols="12" sm="6" md="3">
                  <v-text-field
                    v-model="general.province"
                    label="State / Province"
                    variant="outlined"
                    density="comfortable"
                    rounded="lg"
                  />
                </v-col>

                <v-col cols="12" sm="6" md="3">
                  <v-text-field
                    v-model="general.country"
                    label="Country"
                    variant="outlined"
                    density="comfortable"
                    rounded="lg"
                  />
                </v-col>

                <v-col cols="12" sm="6" md="3">
                  <v-text-field
                    v-model="general.zip_code"
                    label="Zip / Postal Code"
                    variant="outlined"
                    density="comfortable"
                    rounded="lg"
                  />
                </v-col>

                <v-col cols="12">
                  <v-textarea
                    v-model="general.note"
                    label="Notes & Overview"
                    rows="3"
                    variant="outlined"
                    density="comfortable"
                    rounded="lg"
                  />
                </v-col>
              </v-row>

              <div class="d-flex justify-end mt-4">
                <v-btn
                  color="primary"
                  type="submit"
                  prepend-icon="mdi-content-save-outline"
                  rounded="lg"
                  size="large"
                  class="text-none font-weight-medium"
                >
                  Save Changes
                </v-btn>
              </div>
            </v-form>
          </v-window-item>

          <!-- TAB 2: Locations / Branches -->
          <v-window-item value="locations">
            <div
              class="d-flex flex-column flex-sm-row justify-space-between align-sm-center ga-4 mb-6"
            >
              <div>
                <h2 class="text-h6 font-weight-bold">Branch Locations</h2>
                <p class="text-body-2 text-medium-emphasis">
                  Manage physical branch offices, facilities, and contact
                  details.
                </p>
              </div>

              <v-btn
                color="primary"
                prepend-icon="mdi-plus"
                rounded="lg"
                class="text-none font-weight-medium"
                @click="openLocationModal()"
              >
                Add Location
              </v-btn>
            </div>

            <v-card
              variant="outlined"
              class="rounded-lg overflow-hidden border"
            >
              <v-data-table
                :headers="locationHeaders"
                :items="locations"
                hover
                class="bg-transparent"
              >
                <template #item.address="{ item }">
                  <div class="font-weight-medium text-high-emphasis">
                    {{ item.address }}
                  </div>
                </template>

                <template #item.city_province="{ item }">
                  <span class="text-medium-emphasis">
                    {{
                      [item.city, item.province].filter(Boolean).join(", ") ||
                      "—"
                    }}
                  </span>
                </template>

                <template #item.phone="{ item }">
                  <span class="text-medium-emphasis">{{
                    item.phone || "—"
                  }}</span>
                </template>

                <template #item.zip_code="{ item }">
                  <v-chip
                    size="small"
                    variant="tonal"
                    class="font-weight-medium"
                  >
                    {{ item.zip_code || "—" }}
                  </v-chip>
                </template>

                <template #item.actions="{ item }">
                  <div class="d-flex justify-end ga-1">
                    <v-btn
                      icon="mdi-pencil-outline"
                      size="small"
                      variant="text"
                      color="medium-emphasis"
                      @click="openLocationModal(item)"
                    />
                    <v-btn
                      icon="mdi-trash-can-outline"
                      size="small"
                      variant="text"
                      color="error"
                      @click="deleteLocation(item)"
                    />
                  </div>
                </template>
              </v-data-table>
            </v-card>
          </v-window-item>

          <!-- TAB 3: Corporate Branding -->
          <v-window-item value="branding">
            <v-form @submit.prevent="saveBranding">
              <v-row class="py-2">
                <!-- Logo Section -->
                <v-col cols="12" md="4" class="text-center">
                  <v-card
                    variant="outlined"
                    class="pa-6 border-dashed rounded-xl d-flex flex-column align-center justify-center bg-grey-lighten-5"
                    style="min-height: 220px"
                  >
                    <!-- NEWLY SELECTED LOGO -->
                    <div
                      v-if="logoPreview"
                      class="d-flex align-center justify-center"
                      style="width: 100%; height: 180px"
                    >
                      <v-img
                        :key="logoPreview"
                        :src="logoPreview"
                        width="220"
                        height="160"
                        contain
                        class="rounded-lg"
                      />
                    </div>

                    <!-- SAVED LOGO -->
                    <div
                      v-else-if="branding.client_logo"
                      class="d-flex align-center justify-center"
                      style="width: 100%; height: 180px"
                    >
                      <v-img
                        :key="branding.client_logo"
                        :src="`/storage/${branding.client_logo}`"
                        width="220"
                        height="160"
                        contain
                        class="rounded-lg"
                      />
                    </div>

                    <!-- NO LOGO -->
                    <div v-else class="text-grey-darken-1 py-4 text-center">
                      <v-icon
                        icon="mdi-cloud-upload-outline"
                        size="48"
                        class="mb-2 text-medium-emphasis"
                      />

                      <p class="text-subtitle-2 font-weight-medium">
                        No Logo Uploaded
                      </p>
                    </div>
                  </v-card>
                </v-col>

                <v-col cols="12" md="8" class="ps-md-6">
                  <div class="mb-4">
                    <h3 class="text-h6 font-weight-bold mb-1">
                      Company Identity Logo
                    </h3>
                    <p class="text-body-2 text-medium-emphasis">
                      Upload your company logo. PNG or SVG files with
                      transparent backgrounds work best.
                    </p>
                  </div>

                  <v-file-input
                    label="Select new logo file"
                    accept="image/png,image/jpeg,image/svg+xml"
                    prepend-inner-icon="mdi-file-image-outline"
                    prepend-icon=""
                    variant="outlined"
                    density="comfortable"
                    rounded="lg"
                    show-size
                    :multiple="false"
                    @change="onLogoChange"
                  />
                </v-col>
              </v-row>

              <v-divider class="my-6" />

              <!-- Theme Colors Section -->
              <div class="mb-4">
                <h3 class="text-h6 font-weight-bold mb-1">
                  Brand Theme Colors
                </h3>
                <p class="text-body-2 text-medium-emphasis">
                  Select primary and secondary colors used across the user
                  interface.
                </p>
              </div>

              <v-row>
                <!-- Primary Color Picker -->
                <v-col cols="12" sm="6">
                  <v-text-field
                    v-model="branding.primary_color"
                    label="Primary Color"
                    variant="outlined"
                    density="comfortable"
                    rounded="lg"
                    readonly
                  >
                    <template #prepend-inner>
                      <v-menu :close-on-content-click="false">
                        <template #activator="{ props }">
                          <div
                            v-bind="props"
                            class="color-swatch rounded-circle cursor-pointer me-2 border"
                            :style="{ backgroundColor: branding.primary_color }"
                          />
                        </template>
                        <v-card class="pa-2">
                          <v-color-picker
                            v-model="branding.primary_color"
                            mode="hex"
                            hide-inputs
                          />
                        </v-card>
                      </v-menu>
                    </template>
                  </v-text-field>
                </v-col>

                <!-- Secondary Color Picker -->
                <v-col cols="12" sm="6">
                  <v-text-field
                    v-model="branding.secondary_color"
                    label="Secondary Color"
                    variant="outlined"
                    density="comfortable"
                    rounded="lg"
                    readonly
                  >
                    <template #prepend-inner>
                      <v-menu :close-on-content-click="false">
                        <template #activator="{ props }">
                          <div
                            v-bind="props"
                            class="color-swatch rounded-circle cursor-pointer me-2 border"
                            :style="{
                              backgroundColor: branding.secondary_color,
                            }"
                          />
                        </template>
                        <v-card class="pa-2">
                          <v-color-picker
                            v-model="branding.secondary_color"
                            mode="hex"
                            hide-inputs
                          />
                        </v-card>
                      </v-menu>
                    </template>
                  </v-text-field>
                </v-col>
              </v-row>

              <!-- Live Color Palette Preview -->
              <v-card variant="tonal" class="pa-4 rounded-lg my-2 border">
                <div
                  class="text-caption font-weight-bold text-uppercase text-medium-emphasis mb-2"
                >
                  Live Palette Preview
                </div>
                <div class="d-flex ga-3 align-center">
                  <v-chip
                    :style="{
                      backgroundColor: branding.primary_color,
                      color: '#fff',
                    }"
                    class="font-weight-medium"
                  >
                    Primary Action
                  </v-chip>
                  <v-chip
                    :style="{
                      backgroundColor: branding.secondary_color,
                      color: '#fff',
                    }"
                    class="font-weight-medium"
                  >
                    Secondary Accent
                  </v-chip>
                </div>
              </v-card>

              <div class="d-flex justify-end mt-6">
                <v-btn
                  color="primary"
                  type="submit"
                  prepend-icon="mdi-content-save-outline"
                  rounded="lg"
                  size="large"
                  class="text-none font-weight-medium"
                >
                  Save Branding & Theme
                </v-btn>
              </div>
            </v-form>
          </v-window-item>

          <!-- TAB 4: Departments Management -->
          <v-window-item value="departments">
            <div
              class="d-flex flex-column flex-sm-row justify-space-between align-sm-center ga-4 mb-6"
            >
              <div>
                <h2 class="text-h6 font-weight-bold">Department Structure</h2>
                <p class="text-body-2 text-medium-emphasis">
                  Manage sub-units and hierarchical relationships.
                </p>
              </div>

              <v-btn
                color="primary"
                prepend-icon="mdi-plus"
                rounded="lg"
                class="text-none font-weight-medium"
                @click="openDepartmentModal()"
              >
                Add Department
              </v-btn>
            </div>

            <!-- Enhanced Data Table -->
            <v-card
              variant="outlined"
              class="rounded-lg overflow-hidden border"
            >
              <v-data-table
                :headers="deptHeaders"
                :items="departments"
                hover
                class="bg-transparent"
              >
                <template #item.name="{ item }">
                  <div class="font-weight-medium text-high-emphasis">
                    {{ item.name }}
                  </div>
                </template>

                <template #item.shortcut="{ item }">
                  <v-chip
                    size="small"
                    variant="tonal"
                    class="font-weight-medium"
                  >
                    {{ item.shortcut || "—" }}
                  </v-chip>
                </template>

                <template #item.parent_id="{ item }">
                  <span class="text-medium-emphasis">
                    {{ getDepartmentName(item.parent_id) }}
                  </span>
                </template>

                <template #item.actions="{ item }">
                  <div class="d-flex justify-end ga-1">
                    <v-btn
                      icon="mdi-pencil-outline"
                      size="small"
                      variant="text"
                      color="medium-emphasis"
                      @click="openDepartmentModal(item)"
                    />
                    <v-btn
                      icon="mdi-trash-can-outline"
                      size="small"
                      variant="text"
                      color="error"
                      @click="deleteDepartment(item)"
                    />
                  </div>
                </template>
              </v-data-table>
            </v-card>
          </v-window-item>
        </v-window>
      </v-card-text>
    </v-card>

    <!-- Location Modal Dialog -->
    <v-dialog v-model="locationDialog" max-width="560px">
      <v-card class="rounded-xl pa-2">
        <v-card-title
          class="d-flex align-center justify-space-between font-weight-bold text-h6 px-4 pt-4"
        >
          <span>{{
            isEditingLocation ? "Edit Location" : "Create Location"
          }}</span>
          <v-btn
            icon="mdi-close"
            variant="text"
            size="small"
            @click="locationDialog = false"
          />
        </v-card-title>

        <v-card-text class="px-4 pt-2">
          <v-form ref="locationForm" @submit.prevent="saveLocation">
            <v-text-field
              v-model="locationData.address"
              label="Street Address"
              placeholder="e.g. 123 Business Rd"
              variant="outlined"
              density="comfortable"
              rounded="lg"
              class="mb-3"
            />
            <v-row density="compact" class="mb-1">
              <v-col cols="12" sm="6">
                <v-text-field
                  v-model="locationData.city"
                  label="City"
                  variant="outlined"
                  density="comfortable"
                  rounded="lg"
                />
              </v-col>
              <v-col cols="12" sm="6">
                <v-text-field
                  v-model="locationData.province"
                  label="State / Province"
                  variant="outlined"
                  density="comfortable"
                  rounded="lg"
                />
              </v-col>
            </v-row>
            <v-row density="compact" class="mb-1">
              <v-col cols="12" sm="6">
                <v-text-field
                  v-model="locationData.zip_code"
                  label="Zip / Postal Code"
                  variant="outlined"
                  density="comfortable"
                  rounded="lg"
                />
              </v-col>
              <v-col cols="12" sm="6">
                <v-text-field
                  v-model="locationData.phone"
                  label="Phone Number"
                  variant="outlined"
                  density="comfortable"
                  rounded="lg"
                />
              </v-col>
            </v-row>

            <v-switch
              v-model="locationData.is_main"
              label="Set as Main / Headquarter Location"
              color="primary"
              inset
              density="comfortable"
              hide-details
              class="mb-4 ms-1 font-weight-medium"
            />

            <v-textarea
              v-model="locationData.notes"
              label="Notes"
              rows="3"
              variant="outlined"
              density="comfortable"
              rounded="lg"
            />
          </v-form>
        </v-card-text>

        <v-card-actions class="px-4 pb-4 pt-0 justify-end ga-2">
          <v-btn
            variant="plain"
            rounded="lg"
            class="text-none"
            @click="locationDialog = false"
          >
            Cancel
          </v-btn>
          <v-btn
            color="primary"
            variant="flat"
            rounded="lg"
            class="text-none font-weight-medium px-6"
            @click="saveLocation"
          >
            Save
          </v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>

    <!-- Delete Location Confirmation Dialog -->
    <v-dialog v-model="deleteLocationDialog" max-width="400px">
      <v-card class="rounded-xl pa-2">
        <v-card-title
          class="d-flex align-center ga-2 font-weight-bold text-h6 px-4 pt-4"
        >
          <v-icon icon="mdi-alert-circle-outline" color="error" size="24" />
          <span>Delete Location?</span>
        </v-card-title>

        <v-card-text class="px-4 pt-2 text-body-1 text-medium-emphasis">
          Are you sure you want to delete
          <strong class="text-high-emphasis">{{
            locationToDelete?.address || "this location"
          }}</strong
          >? This action cannot be undone.
        </v-card-text>

        <v-card-actions class="px-4 pb-4 pt-2 justify-end ga-2">
          <v-btn
            variant="plain"
            rounded="lg"
            class="text-none"
            :disabled="isDeletingLocation"
            @click="deleteLocationDialog = false"
          >
            Cancel
          </v-btn>
          <v-btn
            color="error"
            variant="flat"
            rounded="lg"
            class="text-none font-weight-medium px-5"
            :loading="isDeletingLocation"
            @click="confirmDeleteLocation"
          >
            Delete
          </v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>

    <!-- Modernized Department Dialog -->
    <v-dialog v-model="deptDialog" max-width="480px">
      <v-card class="rounded-xl pa-2">
        <v-card-title
          class="d-flex align-center justify-space-between font-weight-bold text-h6 px-4 pt-4"
        >
          <span>{{
            isEditingDept ? "Edit Department" : "Create Department"
          }}</span>
          <v-btn
            icon="mdi-close"
            variant="text"
            size="small"
            @click="deptDialog = false"
          />
        </v-card-title>

        <v-card-text class="px-4 pt-2">
          <v-form ref="deptForm" @submit.prevent="saveDepartment">
            <v-text-field
              v-model="deptData.name"
              label="Department Name"
              required
              variant="outlined"
              density="comfortable"
              rounded="lg"
              class="mb-3"
            />
            <v-text-field
              v-model="deptData.shortcut"
              label="Shortcut / Abbreviation"
              placeholder="e.g. HR, IT, FIN"
              variant="outlined"
              density="comfortable"
              rounded="lg"
              class="mb-3"
            />
            <v-select
              v-model="deptData.parent_id"
              :items="parentDeptOptions"
              item-title="name"
              item-value="id"
              label="Parent Unit / Department"
              variant="outlined"
              density="comfortable"
              rounded="lg"
            />
          </v-form>
        </v-card-text>

        <v-card-actions class="px-4 pb-4 pt-0 justify-end ga-2">
          <v-btn
            variant="plain"
            rounded="lg"
            class="text-none"
            @click="deptDialog = false"
          >
            Cancel
          </v-btn>
          <v-btn
            color="primary"
            variant="flat"
            rounded="lg"
            class="text-none font-weight-medium px-6"
            @click="saveDepartment"
          >
            Save
          </v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>

    <!-- Delete Department Confirmation Dialog -->
    <v-dialog v-model="deleteDialog" max-width="400px">
      <v-card class="rounded-xl pa-2">
        <v-card-title
          class="d-flex align-center ga-2 font-weight-bold text-h6 px-4 pt-4"
        >
          <v-icon icon="mdi-alert-circle-outline" color="error" size="24" />
          <span>Delete Department?</span>
        </v-card-title>

        <v-card-text class="px-4 pt-2 text-body-1 text-medium-emphasis">
          Are you sure you want to delete
          <strong class="text-high-emphasis">{{ deptToDelete?.name }}</strong
          >? This action cannot be undone.
        </v-card-text>

        <v-card-actions class="px-4 pb-4 pt-2 justify-end ga-2">
          <v-btn
            variant="plain"
            rounded="lg"
            class="text-none"
            :disabled="isDeleting"
            @click="deleteDialog = false"
          >
            Cancel
          </v-btn>
          <v-btn
            color="error"
            variant="flat"
            rounded="lg"
            class="text-none font-weight-medium px-5"
            :loading="isDeleting"
            @click="confirmDeleteDepartment"
          >
            Delete
          </v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>
  </v-container>
</template>

<script>
import SidebarLayout from "@/layouts/SidebarLayout.vue";

export default {
  name: "OrganizationManagement",
  layout: SidebarLayout,

  props: {
    initialGeneral: { type: Object, default: () => ({}) },
    initialBranding: { type: Object, default: () => ({}) },
    initialDepartments: { type: Array, default: () => [] },
    initialLocations: { type: Array, default: () => [] },
  },

  data() {
    return {
      activeTab: "general",

      general: {
        name: "",
        shortcut: "",
        phone: "",
        email: "",
        country: "",
        province: "",
        city: "",
        zip_code: "",
        street1: "",
        street2: "",
        note: "",
        ...this.initialGeneral,
      },

      branding: {
        client_logo: null,
        primary_color: "#1867C0",
        secondary_color: "#5C6BC0",
        ...this.initialBranding,
      },

      logoFile: null,
      logoPreview: null,

      departments: [...this.initialDepartments],

      // Location / Branch State
      locations: [...this.initialLocations],
      locationDialog: false,
      isEditingLocation: false,
      locationData: {
        id: null,
        address: "",
        city: "",
        province: "",
        zip_code: "",
        phone: "",
        notes: "",
        is_main: false,
      },
      locationHeaders: [
        { title: "Address", key: "address" },
        { title: "City / Province", key: "city_province" },
        { title: "Phone", key: "phone" },
        { title: "Zip Code", key: "zip_code" },
        { title: "Actions", key: "actions", sortable: false, align: "end" },
      ],
      deleteLocationDialog: false,
      locationToDelete: null,
      isDeletingLocation: false,

      deptDialog: false,
      isEditingDept: false,
      deptData: { id: null, name: "", shortcut: "", parent_id: null },
      deptHeaders: [
        { title: "Department Name", key: "name" },
        { title: "Shortcut", key: "shortcut" },
        { title: "Parent Unit", key: "parent_id" },
        { title: "Actions", key: "actions", sortable: false, align: "end" },
      ],

      // Delete Modal State
      deleteDialog: false,
      deptToDelete: null,
      isDeleting: false,
    };
  },

  watch: {
    initialDepartments: {
      handler(newVal) {
        this.departments = [...newVal];
      },
      deep: true,
    },
    initialLocations: {
      handler(newVal) {
        this.locations = [...newVal];
      },
      deep: true,
    },
  },

  computed: {
    orgDisplayName() {
      return this.general.name ? this.general.name.trim() : "Main Organization";
    },

    parentDeptOptions() {
      const availableDepartments = this.departments
        .filter((d) => d.id !== this.deptData.id)
        .map((d) => ({
          id: d.id,
          name: d.shortcut ? `${d.name} (${d.shortcut})` : d.name,
        }));

      return [
        {
          id: null,
          name: `Top Level (${this.orgDisplayName})`,
        },
        ...availableDepartments,
      ];
    },

    logoUrl() {
      if (!this.branding.client_logo) {
        return null;
      }

      // If your database stores:
      // organizations/logos/company.png
      return `/storage/${this.branding.client_logo}`;
    },
  },

  methods: {
    saveGeneralInfo() {
      this.$inertia.post(
        route("administration.organization.general.update"),
        this.general,
        {
          preserveState: true,
          onSuccess: () => {
            this.showToast(
              "General organization settings updated successfully.",
              "success"
            );
          },
          onError: () => {
            this.showToast("Failed to update organization settings.", "error");
          },
        }
      );
    },

    // Location / Branch Methods
    openLocationModal(loc = null) {
      if (loc) {
        this.isEditingLocation = true;
        this.locationData = { ...loc };
      } else {
        this.isEditingLocation = false;
        this.locationData = {
          id: null,
          address: "",
          city: "",
          province: "",
          zip_code: "",
          phone: "",
          notes: "",
        };
      }
      this.locationDialog = true;
    },

    saveLocation() {
      if (this.isEditingLocation) {
        this.$inertia.put(
          route(
            "administration.organization.location.update",
            this.locationData.id
          ),
          this.locationData,
          {
            preserveState: true,
            onSuccess: (page) => {
              if (page.props.initialLocations) {
                this.locations = [...page.props.initialLocations];
              }
              this.locationDialog = false;
              this.showToast("Location updated successfully.", "success");
            },
            onError: () => {
              this.showToast("Failed to update location.", "error");
            },
          }
        );
      } else {
        const payload = {
          address: this.locationData.address,
          city: this.locationData.city,
          province: this.locationData.province,
          zip_code: this.locationData.zip_code,
          phone: this.locationData.phone,
          notes: this.locationData.notes,
        };

        this.$inertia.post(
          route("administration.organization.location.store"),
          payload,
          {
            preserveState: true,
            onSuccess: (page) => {
              if (page.props.initialLocations) {
                this.locations = [...page.props.initialLocations];
              }
              this.locationDialog = false;
              this.showToast("Location created successfully.", "success");
            },
            onError: () => {
              this.showToast("Failed to create location.", "error");
            },
          }
        );
      }
    },

    deleteLocation(loc) {
      this.locationToDelete =
        typeof loc === "object"
          ? loc
          : this.locations.find((l) => l.id === loc);
      this.deleteLocationDialog = true;
    },

    confirmDeleteLocation() {
      if (!this.locationToDelete?.id) return;

      this.isDeletingLocation = true;

      this.$inertia.delete(
        route(
          "administration.organization.location.destroy",
          this.locationToDelete.id
        ),
        {
          preserveState: true,
          onSuccess: (page) => {
            if (page.props.initialLocations) {
              this.locations = [...page.props.initialLocations];
            }
            this.deleteLocationDialog = false;
            this.locationToDelete = null;
            this.isDeletingLocation = false;
            this.showToast("Location deleted successfully.", "success");
          },
          onError: () => {
            this.isDeletingLocation = false;
            this.showToast("Failed to delete location.", "error");
          },
        }
      );
    },

    onLogoChange(event) {
      console.log("CHANGE EVENT:", event);

      const file = event?.target?.files?.[0];

      if (!file) {
        console.log("No file selected.");

        this.logoFile = null;
        this.logoPreview = null;

        return;
      }

      console.log("FILE:", file);
      console.log("NAME:", file.name);
      console.log("TYPE:", file.type);

      // Keep the actual file for FormData
      this.logoFile = file;

      // Read the file directly
      const reader = new FileReader();

      reader.onload = (e) => {
        console.log("FILE READER RESULT:", e.target.result);

        this.logoPreview = e.target.result;
      };

      reader.onerror = (e) => {
        console.error("FILE READER ERROR:", e);

        this.logoPreview = null;
      };

      reader.readAsDataURL(file);
    },

    saveBranding() {
      const formData = new FormData();

      let file = this.logoFile;

      if (Array.isArray(file)) {
        file = file[0] || null;
      }

      if (file instanceof File) {
        formData.append("client_logo", file);
      }

      formData.append("primary_color", this.branding.primary_color || "");

      formData.append("secondary_color", this.branding.secondary_color || "");

      this.$inertia.post(
        route("administration.organization.branding.update"),
        formData,
        {
          forceFormData: true,
          preserveState: true,
          preserveScroll: true,

          onSuccess: () => {
            this.showToast(
              "Branding and theme settings updated successfully.",
              "success"
            );

            // Clear the selected file
            this.logoFile = null;

            // The server should now provide the
            // new branding.client_logo value.
            this.logoPreview = null;
          },

          onError: (errors) => {
            console.error("Branding validation errors:", errors);

            this.showToast("Failed to save branding settings.", "error");
          },
        }
      );
    },

    getDepartmentName(parentId) {
      if (!parentId) {
        return this.orgDisplayName;
      }
      const parent = this.departments.find((d) => d.id === parentId);
      return parent ? `${parent.name} (${parent.shortcut})` : "—";
    },

    openDepartmentModal(dept = null) {
      if (dept) {
        this.isEditingDept = true;
        this.deptData = { ...dept };
      } else {
        this.isEditingDept = false;
        this.deptData = { id: null, name: "", shortcut: "", parent_id: null };
      }
      this.deptDialog = true;
    },

    saveDepartment() {
      if (this.isEditingDept) {
        this.$inertia.put(
          route(
            "administration.organization.department.update",
            this.deptData.id
          ),
          this.deptData,
          {
            preserveState: true,
            onSuccess: (page) => {
              if (page.props.initialDepartments) {
                this.departments = [...page.props.initialDepartments];
              }
              this.deptDialog = false;
              this.showToast("Department updated successfully.", "success");
            },
            onError: () => {
              this.showToast("Failed to update department.", "error");
            },
          }
        );
      } else {
        const payload = {
          name: this.deptData.name,
          shortcut: this.deptData.shortcut,
          parent_id: this.deptData.parent_id,
        };

        this.$inertia.post(
          route("administration.organization.department.store"),
          payload,
          {
            preserveState: true,
            onSuccess: (page) => {
              if (page.props.initialDepartments) {
                this.departments = [...page.props.initialDepartments];
              }
              this.deptDialog = false;
              this.showToast("Department created successfully.", "success");
            },
            onError: () => {
              this.showToast("Failed to create department.", "error");
            },
          }
        );
      }
    },

    deleteDepartment(dept) {
      this.deptToDelete =
        typeof dept === "object"
          ? dept
          : this.departments.find((d) => d.id === dept);
      this.deleteDialog = true;
    },

    confirmDeleteDepartment() {
      if (!this.deptToDelete?.id) return;

      this.isDeleting = true;

      this.$inertia.delete(
        route(
          "administration.organization.department.destroy",
          this.deptToDelete.id
        ),
        {
          preserveState: true,
          onSuccess: () => {
            this.deleteDialog = false;
            this.deptToDelete = null;
            this.isDeleting = false;
            this.showToast("Department deleted successfully.", "success");
          },
          onError: () => {
            this.isDeleting = false;
            this.showToast("Failed to delete department.", "error");
          },
        }
      );
    },
  },
};
</script>

<style scoped>
.min-h-220 {
  min-height: 220px;
}
.max-width-xl {
  max-width: 1400px;
}
.tracking-tight {
  letter-spacing: -0.02em;
}
.border-dashed {
  border-style: dashed !important;
}
.color-swatch {
  width: 24px;
  height: 24px;
}
</style>
