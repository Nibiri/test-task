export type FieldName = 'title' | 'status' | 'amount'

export type Override = {
  field: FieldName
  base: string
  local: string
  external: string | null
  is_conflicted: boolean
}

export type RefreshState = {
  id: number
  status: 'accepted' | 'running' | 'succeeded' | 'failed'
  outcome: 'applied' | 'skipped' | 'inconsistency' | null
  outcome_detail: {
    reason?: string | null
    conflicted?: string[]
    preserved?: string[]
    accepted?: string[]
    converged?: string[]
  } | null
  attempt_count: number
  last_error: string | null
  finished_at?: string | null
}

export type ExportState = {
  id: number
  status: 'accepted' | 'running' | 'succeeded' | 'failed'
  attempt_count: number
  last_error: string | null
  finished_at?: string | null
}

export type Deal = {
  id: number
  external_id: string | null
  title: string | null
  status: string | null
  amount: string | null
  external_title: string | null
  external_status: string | null
  external_amount: string | null
  external_version: number | null
  external_updated_at: string | null
  has_conflict: boolean
  overrides: Override[]
  latest_refresh: RefreshState | null
  latest_export: ExportState | null
}

export type DealPage = {
  data: Deal[]
  meta: {
    current_page: number
    last_page: number
    per_page: number
    total: number
  }
}

export const STATUSES = ['NEW', 'IN_PROGRESS', 'WON', 'LOST'] as const

export const FIELD_LABELS: Record<FieldName, string> = {
  title: 'Название',
  status: 'Статус',
  amount: 'Сумма',
}
