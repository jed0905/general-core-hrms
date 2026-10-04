<template>
  <v-card variant="outlined" class="rounded-lg">
    <v-card-title class="d-flex align-center justify-space-between text-subtitle-1 font-weight-bold py-3 px-5 border-b">
      <span>Documents</span>
      <v-btn
        v-if="can.uploadDocuments"
        color="primary"
        size="small"
        prepend-icon="mdi-upload"
        @click="openUpload"
      >
        Upload document
      </v-btn>
    </v-card-title>

    <v-card-text class="pa-0">
      <v-table v-if="documents.length" density="comfortable">
        <thead>
          <tr>
            <th>Type</th>
            <th>File</th>
            <th>Size</th>
            <th>Expires</th>
            <th>Uploaded</th>
            <th class="text-end">Actions</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="doc in documents" :key="doc.id">
            <td class="text-no-wrap">{{ doc.type?.name || "—" }}</td>
            <td>
              <div class="font-weight-medium">{{ doc.original_name }}</div>
              <div v-if="doc.description" class="text-caption text-medium-emphasis">{{ doc.description }}</div>
            </td>
            <td class="text-no-wrap">{{ formatSize(doc.file_size) }}</td>
            <td class="text-no-wrap">
              <template v-if="doc.expires_on">
                {{ formatDate(doc.expires_on) }}
                <v-chip v-if="expiryState(doc) === 'expired'" size="x-small" color="error" variant="tonal" class="ml-1">Expired</v-chip>
                <v-chip v-else-if="expiryState(doc) === 'soon'" size="x-small" color="warning" variant="tonal" class="ml-1">Expiring soon</v-chip>
              </template>
              <span v-else class="text-medium-emphasis">—</span>
            </td>
            <td class="text-no-wrap">
              <div>{{ formatDateTime(doc.uploaded_at) }}</div>
              <div v-if="doc.uploader" class="text-caption text-medium-emphasis">{{ doc.uploader.username }}</div>
            </td>
            <td class="text-end text-no-wrap">
              <v-btn
                v-if="canPreview(doc)"
                :href="route('people.employee.documents.view', [employee.id, doc.id])"
                target="_blank"
                rel="noopener"
                icon="mdi-eye-outline"
                size="small"
                variant="text"
                title="View"
              />
              <v-btn
                :href="route('people.employee.documents.download', [employee.id, doc.id])"
                icon="mdi-download"
                size="small"
                variant="text"
                title="Download"
              />
              <v-btn
                v-if="can.updateDocuments"
                icon="mdi-pencil-outline"
                size="small"
                variant="text"
                title="Edit details"
                @click="openEdit(doc)"
              />
              <v-btn
                v-if="can.deleteDocuments"
                icon="mdi-delete-outline"
                size="small"
                variant="text"
                color="error"
                title="Delete"
                @click="confirmDelete(doc)"
              />
            </td>
          </tr>
        </tbody>
      </v-table>
      <div v-else class="pa-6 text-center text-medium-emphasis">No documents uploaded.</div>
    </v-card-text>

    <!-- Upload / edit -->
    <v-dialog v-model="dialog" max-width="560">
      <v-card>
        <v-card-title class="text-h6 pa-4">{{ editing ? "Edit document details" : "Upload document" }}</v-card-title>
        <v-card-text>
          <v-file-input
            v-if="!editing"
            v-model="form.file"
            label="File *"
            :accept="accept"
            :hint="`PDF, images, Office or text files up to ${maxMb} MB.`"
            persistent-hint
            prepend-icon=""
            prepend-inner-icon="mdi-paperclip"
            variant="outlined"
            density="compact"
            class="mb-3"
            :error-messages="form.errors.file"
          />
          <v-select
            v-model="form.employee_document_type_id"
            :items="documentTypes"
            item-title="name"
            item-value="id"
            label="Document type *"
            variant="outlined"
            density="compact"
            class="mb-3"
            :error-messages="form.errors.employee_document_type_id"
          />
          <v-text-field
            v-model="form.expires_on"
            type="date"
            label="Expiry date"
            variant="outlined"
            density="compact"
            class="mb-3"
            clearable
            :error-messages="form.errors.expires_on"
          />
          <v-textarea
            v-model="form.description"
            label="Description"
            rows="2"
            counter="2000"
            variant="outlined"
            density="compact"
            :error-messages="form.errors.description"
          />
        </v-card-text>
        <v-card-actions class="pa-4">
          <v-spacer />
          <v-btn variant="text" @click="dialog = false">Cancel</v-btn>
          <v-btn color="primary" :loading="form.processing" @click="submit">
            {{ editing ? "Save" : "Upload" }}
          </v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>

    <DeleteDialog
      v-model="deleteDialog"
      :message="`Delete ${toDelete?.original_name || 'this document'}? The file is removed permanently.`"
      :loading="deleting"
      @confirm="destroy"
    />
  </v-card>
</template>

<script>
import { router, useForm } from "@inertiajs/vue3";
import DeleteDialog from "@/components/DeleteDialog.vue";

const INLINE_TYPES = ["application/pdf", "image/jpeg", "image/png"];
const ACCEPT = ".pdf,.jpg,.jpeg,.png,.doc,.docx,.xls,.xlsx,.odt,.ods,.txt";

export default {
  name: "EmployeeDocuments",

  components: { DeleteDialog },

  props: {
    employee: { type: Object, required: true },
    documents: { type: Array, default: () => [] },
    documentTypes: { type: Array, default: () => [] },
    can: { type: Object, default: () => ({}) },
  },

  data() {
    return {
      dialog: false,
      editing: null,
      deleteDialog: false,
      toDelete: null,
      deleting: false,
      accept: ACCEPT,
      maxMb: 10,
      form: useForm({
        file: null,
        employee_document_type_id: null,
        expires_on: null,
        description: "",
      }),
    };
  },

  methods: {
    openUpload() {
      this.editing = null;
      this.form.reset();
      this.form.clearErrors();
      this.dialog = true;
    },
    openEdit(doc) {
      this.editing = doc;
      this.form.clearErrors();
      this.form.file = null;
      this.form.employee_document_type_id = doc.employee_document_type_id;
      this.form.expires_on = doc.expires_on;
      this.form.description = doc.description || "";
      this.dialog = true;
    },
    submit() {
      const done = { preserveScroll: true, onSuccess: () => { this.dialog = false; this.form.reset(); } };
      if (this.editing) {
        this.form
          .transform(({ file, ...data }) => data)
          .put(route("people.employee.documents.update", [this.employee.id, this.editing.id]), done);
      } else {
        // v-file-input may hold an array depending on the Vuetify version.
        this.form
          .transform((data) => ({ ...data, file: Array.isArray(data.file) ? data.file[0] : data.file }))
          .post(route("people.employee.documents.store", this.employee.id), { ...done, forceFormData: true });
      }
    },
    confirmDelete(doc) {
      this.toDelete = doc;
      this.deleteDialog = true;
    },
    destroy() {
      this.deleting = true;
      router.delete(route("people.employee.documents.destroy", [this.employee.id, this.toDelete.id]), {
        preserveScroll: true,
        onFinish: () => {
          this.deleting = false;
          this.deleteDialog = false;
          this.toDelete = null;
        },
      });
    },
    canPreview(doc) {
      return INLINE_TYPES.includes(doc.mime_type);
    },
    expiryState(doc) {
      const today = new Date().toISOString().slice(0, 10);
      if (doc.expires_on < today) return "expired";
      const soon = new Date(Date.now() + 30 * 86400000).toISOString().slice(0, 10);
      return doc.expires_on <= soon ? "soon" : null;
    },
    formatSize(bytes) {
      if (!bytes) return "0 KB";
      if (bytes < 1024 * 1024) return `${Math.max(1, Math.round(bytes / 1024))} KB`;
      return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
    },
    formatDate(value) {
      return value ? new Date(`${String(value).substring(0, 10)}T00:00:00`).toLocaleDateString() : "";
    },
    formatDateTime(value) {
      return value ? new Date(value).toLocaleString() : "";
    },
  },
};
</script>
