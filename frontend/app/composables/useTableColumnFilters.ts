import type { MaybeRefOrGetter } from 'vue'

type ColumnValue = string | number | boolean | null | undefined

export type TableColumnDefinition<Row> = {
  key: string
  value: (row: Row) => ColumnValue
}

export function useTableColumnFilters<Row>(
  rows: MaybeRefOrGetter<Row[]>,
  columns: TableColumnDefinition<Row>[],
) {
  const filters = reactive<Record<string, string>>(
    Object.fromEntries(columns.map(column => [column.key, ''])),
  )

  const filteredRows = computed(() => {
    const activeFilters = columns
      .map(column => ({
        ...column,
        query: filters[column.key]?.trim().toLocaleLowerCase() || '',
      }))
      .filter(column => column.query)

    if (activeFilters.length === 0) {
      return toValue(rows)
    }

    return toValue(rows).filter(row =>
      activeFilters.every(column =>
        String(column.value(row) ?? '')
          .toLocaleLowerCase()
          .includes(column.query),
      ),
    )
  })

  function clearFilters() {
    for (const key of Object.keys(filters)) {
      filters[key] = ''
    }
  }

  return {
    filters,
    filteredRows,
    clearFilters,
  }
}
