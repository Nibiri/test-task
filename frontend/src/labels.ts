import type { Deal } from './types'

const STATUS_LABELS: Record<string, string> = {
  NEW: 'Новая',
  IN_PROGRESS: 'В работе',
  WON: 'Выиграна',
  LOST: 'Проиграна',
}

export const statusLabel = (value: string | null | undefined): string => {
  if (!value) return '—'
  return STATUS_LABELS[value] ?? value
}

export const refreshMessage = (deal: Deal): string => {
  const refresh = deal.latest_refresh
  if (!refresh) return 'Обновлений ещё не было'
  if (refresh.status === 'accepted') return 'Запрос принят'
  if (refresh.status === 'running') return 'Обновление выполняется'
  if (refresh.status === 'failed') return `Ошибка синхронизации${refresh.last_error ? `: ${refresh.last_error}` : ''}`
  if (deal.has_conflict) return 'Синхронизация выполнена. Есть конфликт полей'
  if (refresh.outcome === 'inconsistency') return 'Та же внешняя версия пришла с другими данными. Сделка не изменена'
  if (refresh.outcome === 'skipped') return 'Ответ получен, версия не новее — данные не изменены'
  return 'Успешно обновлено'
}

export const isRefreshPending = (deal: Deal): boolean =>
  deal.latest_refresh?.status === 'accepted' || deal.latest_refresh?.status === 'running'

export const isExportPending = (deal: Deal): boolean =>
  deal.latest_export?.status === 'accepted' || deal.latest_export?.status === 'running'

export const syncMessage = (deal: Deal): string => {
  const exported = deal.latest_export
  if (exported?.status === 'accepted' || exported?.status === 'running') return 'Создаём сделку в CRM'
  if (exported?.status === 'failed' && !deal.external_id) {
    return `Не удалось создать сделку в CRM${exported.last_error ? `: ${exported.last_error}` : ''}`
  }
  if (exported?.status === 'succeeded' && !deal.latest_refresh) return 'Сделка создана в CRM'
  return refreshMessage(deal)
}
