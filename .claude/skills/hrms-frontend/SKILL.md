---
name: hrms-frontend
description: Use when creating or editing a Vue page, dialog, form, table, filter bar, status chip, or sidebar menu entry in the General Core HRMS (Vue 3.5 Options API, Inertia, Vuetify 3.4, Ziggy) - including displaying money, currencies, dates, or locale-specific formats - or when a page is missing from the menu, a form doesn't submit, a flash toast doesn't show, or something looks wrong in dark mode.
---

# HRMS Frontend

Project conventions on top of `inertia-vue-development` (Inertia API) and
`frontend-design` (visual direction). For brand-new UI, consult `frontend-design`
for layout ideas, but keep to Vuetify, the theme tokens, and the page skeleton below.

## Page skeleton (copy `resources/js/pages/app/Recruitment/Requisitions/Index.vue`)

```vue
<template>
  <SidebarLayout>
    <Head title="Job Requisitions" />
    <v-container fluid class="pa-6">
      <!-- header: h1.text-h5 + subtitle + primary action gated by can.create -->
      <!-- filters: v-card variant="outlined" rounded-lg, density="compact" fields -->
      <!-- list: v-card > v-table (or v-data-table) + empty row + <Pagination :meta="..."/> -->
    </v-container>
  </SidebarLayout>
</template>

<script>
import { Head, router } from "@inertiajs/vue3";
import SidebarLayout from "@/layouts/SidebarLayout.vue";   // lowercase "layouts"
import Pagination from "@/components/Pagination.vue";

export default {
  name: "RequisitionIndex",
  components: { SidebarLayout, Head, Pagination },
  props: { requisitions: { type: Object, required: true }, filters: { type: Object, default: () => ({}) }, can: { type: Object, default: () => ({}) } },
  data() { return { form: { search: this.filters.search || "" } }; },
  methods: {
    apply() {
      router.get(route("recruitment.requisitions.index"), this.form, { preserveState: true, preserveScroll: true, replace: true });
    },
  },
};
</script>
```

Rules:

- **Options API only** for new files. Don't copy the `<script setup>` pages in People/Administration.
- Path = render string: `Inertia::render('app/<Module>/<Entity>/Index')` maps to
  `resources/js/pages/app/<Module>/<Entity>/Index.vue`. Other pages are `Show.vue`,
  `Form.vue` (shared create/edit), or Create/Edit, depending on the module's existing choice.
- Import with `@/layouts/...` and `@/components/...`. `@/Layouts` breaks on Linux.
- Every URL uses Ziggy `route('name', params)`. Never hardcode paths.
- Links: `:href="route(...)" @click.prevent="go(route(...))"` with `router.visit`, as the exemplar does.

## Forms

- `useForm()` inside `data()`: `form: useForm({ ...fields })`.
- Verb must match the route: `form.post`, `form.put` (PUT), `form.delete`.
  When uploading files: `form.transform(d => ({ ...d, _method: 'put' })).post(url, { forceFormData: true })`.
- Errors: `:error-messages="form.errors.field_name"`. Field names must exactly match
  the FormRequest keys, which match the migration columns.
- Disable submit with `:loading="form.processing"`.

## Status, labels, shared helpers

- Status label/color maps live once per module in `resources/js/utils/<module>.js`
  (`REQUISITION_STATUS`, `statusInfo()`, `personName()` in `utils/recruitment.js`).
  Add new statuses there, not inline in pages. Keep them in sync with the model constants.
- Reuse `resources/js/components`: `Pagination`, `DeleteDialog`, `FilterWrapper`,
  `TableWrapper`, `Button*`. Module components go in `components/<Module>/`.
- Do **not** import legacy tab components (`LeavesTabs`, `MyLeavesTabs`,
  `LeaveManagementTabs`, `components/Payroll/*Tabs`) or pages under `HrManagement/`, `RolesAndPermissions/`.

## Authorization in the UI

- Record actions: use the server-computed `can` prop (`v-if="can.approve"`).
- Generic capability: `mixins/permissions.js` `can('permission.name')`.
- Menu: add an item to the right group in `SidebarLayout.vue` `modules()`:
  `{ title, icon: "mdi-...", route: "module.entity.index", permission: "entity.view", routePrefix: "module.entity" }`.
  Use `access:` only for recruitment-style assignment access shared via props.

## Theme

- Colors: `color="primary"`, `"success"`, `"warning"`, `"error"`, `"info"`, `"grey"`,
  or `rgb(var(--v-theme-primary))` in CSS. **No hex values.** `primary`/`secondary` come
  from `corporate_brandings` at runtime.
- Text emphasis: `text-medium-emphasis` and similar classes, not gray hex values. Check both light and dark mode.

## Money, currencies, and localization

- Never calculate money, tax, or contributions in JavaScript. The server sends
  each amount as a decimal string **together with its ISO 4217 currency code**. The page
  only formats it.
- Never hardcode a currency or locale (`'PHP'`, `'en-PH'`). Format with the amount's own
  currency and the viewer's locale; a payslip uses its localization's locale:
  ```js
  formatMoney(amount, currency, locale) {
    // Intl handles symbol, decimal separator, grouping and the currency's minor units.
    return new Intl.NumberFormat(locale, { style: "currency", currency }).format(Number(amount));
  }
  ```
  `Number()` here is for display only. Put the helper in one shared util (e.g. `utils/money.js`),
  not in each page. If the precision of very large amounts matters, display the server-formatted string.
- Never add amounts in different currencies. Totals come from the server, labelled with their
  currency (and with the exchange rate for consolidated views).
- Dates: the server sends ISO 8601 (`YYYY-MM-DD` for pay dates). Format with
  `Intl.DateTimeFormat(locale, ...)`. Don't shift pay dates through the browser's timezone.
- Money inputs: send strings back, and let the FormRequest and service validate the precision per currency.
- Show the country, legal entity, and currency on payroll screens wherever more than one may exist.

## Known gap: success flash

`HandleInertiaRequests::share()` shares only `flash.status`, `flash.error`, and
`flash.psaLoadedData`, so controllers' `->with('success', ...)` never reaches the page.
Fix it once in the middleware (add `'success' => $request->session()->get('success')`)
and show it in the layout, as a deliberate change you tell the user about.
Don't work around it page by page.

## Done means

`npm run build` succeeds, the page renders in light and dark mode, the menu
entry appears for a permitted role and is hidden otherwise.
