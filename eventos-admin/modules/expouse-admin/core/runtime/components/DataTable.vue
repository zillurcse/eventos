<script setup lang="ts" generic="T extends Record<string, any>">
export interface DataTableColumn {
  key: string
  label: string
  /** Secondary line under the label, e.g. a unit ("m/d/y", "Workdays"). */
  sub?: string
  sortable?: boolean
  align?: 'left' | 'center' | 'right'
  width?: string
}

const props = withDefaults(defineProps<{
  columns: DataTableColumn[]
  items: T[]
  /** Row identity — property name, or a getter for composite keys. */
  rowKey?: string | ((row: T) => string | number)
  /** External search query — rows not matching are filtered out. */
  search?: string
  /** Text a row is matched against when searching; defaults to its column values. */
  searchText?: (row: T) => string
  /** External row predicate (e.g. from FilterSelect pills); pass undefined for "all". */
  filter?: (row: T) => boolean
  /** Enable drag-and-drop row reordering (v-model:items + `reorder` on drop). */
  reorderable?: boolean
  /** Show a checkbox column (v-model:selected holds row keys). */
  selectable?: boolean
  selected?: (string | number)[]
  perPageOptions?: number[]
  /** localStorage namespace for the per-page preference. */
  storageKey?: string
  emptyText?: string
}>(), {
  rowKey: 'id',
  perPageOptions: () => [10, 25, 50, 100],
  storageKey: 'default',
  emptyText: 'No data available',
})

const emit = defineEmits<{
  (e: 'update:items', v: T[]): void
  (e: 'update:selected', v: (string | number)[]): void
  (e: 'reorder', v: T[]): void
  (e: 'sort', v: { key: string, order: 'asc' | 'desc' }): void
  (e: 'rowClick', row: T): void
}>()

function keyOf(row: T): string | number {
  return typeof props.rowKey === 'function' ? props.rowKey(row) : row[props.rowKey]
}

// ── search (query supplied by the parent via the `search` prop) ──
const query = computed(() => props.search ?? '')

function rowText(row: T): string {
  if (props.searchText) return props.searchText(row)
  return props.columns.map(c => String(row[c.key] ?? '')).join(' ')
}

const filtered = computed(() => {
  const q = query.value.trim().toLowerCase()
  let rows = props.items
  if (props.filter) rows = rows.filter(props.filter)
  if (q) rows = rows.filter(r => rowText(r).toLowerCase().includes(q))
  return rows
})

// ── sort ──────────────────────────────────────────────────
const sortKey = ref('')
const sortOrder = ref<'asc' | 'desc'>('asc')

function toggleSort(key: string) {
  if (sortKey.value === key) {
    if (sortOrder.value === 'asc') {
      sortOrder.value = 'desc'
    } else {
      sortKey.value = '' // third click clears sorting
      sortOrder.value = 'asc'
      return
    }
  } else {
    sortKey.value = key
    sortOrder.value = 'asc'
  }
  emit('sort', { key: sortKey.value, order: sortOrder.value })
}

const sorted = computed(() => {
  if (!sortKey.value) return filtered.value
  const dir = sortOrder.value === 'asc' ? 1 : -1
  return [...filtered.value].sort((a, b) => {
    const av = a[sortKey.value]
    const bv = b[sortKey.value]
    if (typeof av === 'number' && typeof bv === 'number') return (av - bv) * dir
    return String(av ?? '').localeCompare(String(bv ?? ''), undefined, { numeric: true }) * dir
  })
})

// ── pagination (per-page preference persisted) ────────────
const storage = `dt:${props.storageKey}:perPage`
const perPage = ref(props.perPageOptions[0] ?? 10)
const page = ref(1)

onMounted(() => {
  const cached = Number.parseInt(localStorage.getItem(storage) ?? '', 10)
  if (props.perPageOptions.includes(cached)) perPage.value = cached
})
watch(perPage, (v) => {
  page.value = 1
  localStorage.setItem(storage, String(v))
})
watch([query, () => props.filter, () => props.items.length], () => { page.value = 1 })

const totalPages = computed(() => Math.max(1, Math.ceil(sorted.value.length / perPage.value)))
watch(totalPages, (t) => { if (page.value > t) page.value = t })

const paged = computed(() => sorted.value.slice((page.value - 1) * perPage.value, page.value * perPage.value))
const rangeText = computed(() => {
  const n = sorted.value.length
  if (!n) return '0 items'
  const start = (page.value - 1) * perPage.value + 1
  return `${start}–${Math.min(page.value * perPage.value, n)} of ${n} item${n === 1 ? '' : 's'}`
})

/** Page buttons with ellipsis: 1 … 4 5 6 … 12 */
const pageList = computed<(number | '…')[]>(() => {
  const t = totalPages.value
  const c = page.value
  if (t <= 7) return Array.from({ length: t }, (_, i) => i + 1)
  const pages: (number | '…')[] = [1]
  const lo = Math.max(2, c - 1)
  const hi = Math.min(t - 1, c + 1)
  if (lo > 2) pages.push('…')
  for (let p = lo; p <= hi; p++) pages.push(p)
  if (hi < t - 1) pages.push('…')
  pages.push(t)
  return pages
})

// ── selection ─────────────────────────────────────────────
const allSelected = computed(() =>
  filtered.value.length > 0 && filtered.value.every(r => props.selected?.includes(keyOf(r))),
)

function toggleAll() {
  emit('update:selected', allSelected.value ? [] : filtered.value.map(keyOf))
}

function toggleRow(row: T) {
  const k = keyOf(row)
  const cur = props.selected ?? []
  emit('update:selected', cur.includes(k) ? cur.filter(x => x !== k) : [...cur, k])
}

// ── drag reorder (only meaningful on the unfiltered, unsorted list) ──
const canReorder = computed(() => props.reorderable && !query.value.trim() && !sortKey.value && !props.filter)
const dragIndex = ref<number | null>(null)

function onDragStart(i: number) {
  if (canReorder.value) dragIndex.value = i
}

function onDragOver(i: number, e: DragEvent) {
  if (!canReorder.value) return
  e.preventDefault()
  if (dragIndex.value === null || dragIndex.value === i) return
  const from = props.items.indexOf(paged.value[dragIndex.value] as T)
  const to = props.items.indexOf(paged.value[i] as T)
  if (from < 0 || to < 0) return
  const next = [...props.items]
  const [moved] = next.splice(from, 1)
  next.splice(to, 0, moved as T)
  emit('update:items', next)
  dragIndex.value = i
}

function onDragEnd() {
  if (dragIndex.value === null) return
  dragIndex.value = null
  emit('reorder', props.items)
}

function alignClass(col: DataTableColumn) {
  return col.align === 'right' ? 'text-right' : col.align === 'center' ? 'text-center' : ''
}
</script>

<template>
  <div>
    <div class="dt-wrap">
      <table>
        <thead>
        <tr>
          <th v-if="reorderable" class="w-8" />
          <th v-if="selectable" class="w-9">
            <input type="checkbox" class="w-4 h-4 my-0 accent-brand cursor-pointer" :checked="allSelected" @change="toggleAll">
          </th>
          <th
            v-for="col in columns"
            :key="col.key"
            :class="alignClass(col)"
            :style="col.width ? { width: col.width } : undefined"
          >
            <button
              v-if="col.sortable"
              class="inline-flex items-center gap-1 uppercase font-semibold text-inherit tracking-inherit bg-transparent border-0 p-0 cursor-pointer hover:text-ink"
              :class="{ 'text-ink': sortKey === col.key }"
              @click="toggleSort(col.key)"
            >
              {{ col.label }}
              <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" :class="sortKey === col.key ? 'text-brand' : 'text-faint'">
                <path v-if="sortKey === col.key && sortOrder === 'desc'" d="m6 9 6 6 6-6"/>
                <path v-else-if="sortKey === col.key" d="m18 15-6-6-6 6"/>
                <path v-else d="M8 9l4-4 4 4M8 15l4 4 4-4"/>
              </svg>
            </button>
            <template v-else>{{ col.label }}</template>
            <span v-if="col.sub" class="block normal-case tracking-normal font-medium text-faint text-[.68rem] mt-0.5">{{ col.sub }}</span>
          </th>
          <th v-if="$slots.actions" class="text-right">Actions</th>
        </tr>
      </thead>
      <tbody>
        <tr
          v-for="(row, i) in paged"
          :key="keyOf(row) ?? i"
          class="align-middle"
          :class="[
            canReorder ? 'cursor-grab' : '',
            dragIndex === i ? 'opacity-50 bg-brand-soft/50' : '',
          ]"
          :draggable="canReorder"
          @dragstart="onDragStart(i)"
          @dragover="onDragOver(i, $event)"
          @dragend="onDragEnd"
          @click="emit('rowClick', row)"
        >
          <td v-if="reorderable" class="text-faint w-8" :title="canReorder ? 'Drag to reorder' : 'Clear search & sorting to reorder'">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor"><circle cx="8" cy="6" r="1.3"/><circle cx="8" cy="12" r="1.3"/><circle cx="8" cy="18" r="1.3"/><circle cx="16" cy="6" r="1.3"/><circle cx="16" cy="12" r="1.3"/><circle cx="16" cy="18" r="1.3"/></svg>
          </td>
          <td v-if="selectable" class="w-9" @click.stop>
            <input type="checkbox" class="w-4 h-4 my-0 accent-brand cursor-pointer" :checked="selected?.includes(keyOf(row))" @change="toggleRow(row)">
          </td>
          <td v-for="col in columns" :key="col.key" :class="alignClass(col)">
            <slot :name="`cell-${col.key}`" :row="row" :index="i" :value="row[col.key]">
              {{ row[col.key] ?? '—' }}
            </slot>
          </td>
          <td v-if="$slots.actions" class="text-right whitespace-nowrap" @click.stop>
            <slot name="actions" :row="row" :index="i" />
          </td>
        </tr>

        <!-- Empty state -->
        <tr v-if="!paged.length" class="dt-empty">
          <td :colspan="columns.length + (reorderable ? 1 : 0) + (selectable ? 1 : 0) + ($slots.actions ? 1 : 0)" class="text-center py-12">
            <p v-if="query" class="m-0 text-[.88rem] text-muted">No results match your search.</p>
            <slot v-else name="empty">
              <p class="m-0 text-[.88rem] text-muted">{{ emptyText }}</p>
            </slot>
          </td>
        </tr>
      </tbody>
      </table>

      <!-- Pagination footer -->
      <div v-if="sorted.length" class="dt-foot">
        <span class="text-muted text-[.82rem]">{{ rangeText }}</span>

        <div class="flex items-center gap-4 flex-wrap">
          <label class="flex items-center gap-2 text-[.82rem] text-muted m-0">
            Rows per page
            <select v-model.number="perPage" class="w-auto m-0 py-1.5 px-2 text-[.82rem]">
              <option v-for="n in perPageOptions" :key="n" :value="n">{{ n }}</option>
            </select>
          </label>

          <div v-if="totalPages > 1" class="flex items-center gap-1">
            <button class="dt-page-btn" :disabled="page <= 1" aria-label="Previous page" @click="page--">
              <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="m15 18-6-6 6-6"/></svg>
            </button>
            <template v-for="(p, i) in pageList" :key="i">
              <span v-if="p === '…'" class="px-1 text-faint text-[.8rem]">…</span>
              <button v-else class="dt-page-btn" :class="{ active: p === page }" @click="page = p">{{ p }}</button>
            </template>
            <button class="dt-page-btn" :disabled="page >= totalPages" aria-label="Next page" @click="page++">
              <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="m9 18 6-6-6-6"/></svg>
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
