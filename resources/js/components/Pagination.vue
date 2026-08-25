
<template>
  <div v-if="meta && meta.links && meta.links.length > 3">
    <div class="d-flex w-100 justify-space-between align-center">
      <p class="text-subtitle-2">
        Showing {{ meta.from }} - {{ meta.to }} of {{ meta.total }}
      </p>
      <div class="flex flex-wrap -mb-1">
        <template v-for="(link, i) in meta.links" :key="i">
          <template v-if="link.url !== null">
            <!-- Custom click handler for POST requests -->
            <template v-if="customClickHandler">
              <v-btn
                :variant="link.active ? 'flat' : 'flat'"
                size="small"
                :color="link.active ? 'grey-lighten-2' : 'default'"
                @click="$emit('page-click', link.url)"
                v-html="link.label"
                class="text-black text-decoration-none"
              ></v-btn>
            </template>
            
            <!-- render inertia link with state preservation only if it got partial props -->
            <Link
              v-else-if="partials.length"
              :href="getLinkWithFilters(link.url)"
              class="text-black text-decoration-none"
              preserve-state
              preserve-scroll
              :only="partials"
            >
              <template v-if="link.active">
                <v-btn
                  variant="flat"
                  size="small"
                  color="grey-lighten-2"
                  v-html="link.label"
                ></v-btn>
              </template>
              <template v-else>
                <v-btn variant="flat" size="small" v-html="link.label"></v-btn>
              </template>
            </Link>

            <!-- else render regular inertia link -->
            <Link
              v-else
              :href="getLinkWithFilters(link.url)"
              preserve-state
              preserve-scroll
              class="text-black text-decoration-none"
            >
              <template v-if="link.active">
                <v-btn
                  variant="flat"
                  size="small"
                  color="grey-lighten-2"
                  v-html="link.label"
                ></v-btn>
              </template>
              <template v-else>
                <v-btn variant="flat" size="small" v-html="link.label"></v-btn>
              </template>
            </Link>
          </template>
          <template v-else>
            <v-btn
              disabled
              v-if="link.url === null"
              v-html="link.label"
              variant="flat"
              size="small"
            ></v-btn>
          </template>
        </template>
      </div>
    </div>
  </div>
</template>

<script>
export default {
  props: {
    meta: {
      type: Object,
      required: true,
      default: () => ({ links: [] }),
    },

    partials: {
      type: Array,
      default: [],
    },

    customClickHandler: {
      type: Boolean,
      default: false,
    },

    filters: {
      type: Object,
      default: () => ({}),
    },
  },

  emits: ['page-click'],

  methods: {
    getLinkWithFilters(url) {
      if (!url || !this.filters || Object.keys(this.filters).length === 0) {
        return url;
      }

      try {
        const urlObj = new URL(url);
        
        // Add filter parameters to the URL
        Object.keys(this.filters).forEach(key => {
          if (this.filters[key] !== null && this.filters[key] !== undefined && this.filters[key] !== '') {
            urlObj.searchParams.set(key, this.filters[key]);
          }
        });

        return urlObj.toString();
      } catch (error) {
        // If URL parsing fails, return original URL
        return url;
      }
    },
  },
};
</script>

<style>
</style>
