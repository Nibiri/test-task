import { FormEvent, useEffect, useState } from 'react'
import { useParams } from 'react-router-dom'
import { fetchDeal, refreshDeal, resolveField, retryExport, updateDeal } from '../api'
import { formatDateTime } from '../format'
import { isExportPending, isRefreshPending, statusLabel, syncMessage } from '../labels'
import { FIELD_LABELS, STATUSES, type Deal, type FieldName } from '../types'

const emptyForm = { title: '', status: 'NEW', amount: '' }

export const DealPage = () => {
  const params = useParams()
  const id = Number(params.id)
  const [deal, setDeal] = useState<Deal | null>(null)
  const [form, setForm] = useState(emptyForm)
  const [scenario, setScenario] = useState('success')
  const [notice, setNotice] = useState('')
  const [error, setError] = useState('')

  const load = () => fetchDeal(id).then((result) => {
    setDeal(result.data)
    setForm({
      title: result.data.title ?? '',
      status: result.data.status ?? 'NEW',
      amount: result.data.amount ?? '',
    })
  })

  useEffect(() => {
    load().catch((reason: Error) => setError(reason.message))
  }, [id])

  useEffect(() => {
    if (!deal || (!isRefreshPending(deal) && !isExportPending(deal))) return
    const timer = window.setTimeout(() => {
      load().catch((reason: Error) => setError(reason.message))
    }, 1000)
    return () => window.clearTimeout(timer)
  }, [deal])

  const onSave = (event: FormEvent) => {
    event.preventDefault()
    updateDeal(id, form)
      .then((result) => {
        setDeal(result.data)
        setError('')
      })
      .catch((reason: Error) => setError(reason.message))
  }

  const onRefresh = () => {
    setNotice('')
    refreshDeal(id, scenario)
      .then((result) => setDeal(result.data))
      .catch((reason: Error) => setError(reason.message))
  }

  const onResolve = (field: FieldName, action: 'accept_external' | 'keep_local') => {
    if (!deal || deal.external_version === null) return
    setNotice('')
    resolveField(id, field, action, deal.external_version)
      .then((result) => {
        setDeal(result.data)
        setForm({
          title: result.data.title ?? '',
          status: result.data.status ?? 'NEW',
          amount: result.data.amount ?? '',
        })
      })
      .catch((reason: Error & { status?: number }) => {
        if (reason.status === 409) {
          setNotice(reason.message)
          return load()
        }
        setError(reason.message)
      })
  }

  if (!deal) return <p>{error || 'Загрузка...'}</p>

  const refresh = deal.latest_refresh
  const bannerClass = isExportPending(deal)
    ? 'neutral'
    : deal.latest_export?.status === 'failed' && !deal.external_id
      ? 'error'
      : !refresh
        ? 'neutral'
        : refresh.status === 'failed'
          ? 'error'
          : deal.has_conflict
            ? 'conflict'
            : refresh.outcome === 'inconsistency' || refresh.outcome === 'skipped'
              ? 'neutral'
              : 'ok'

  return (
    <>
      <h1>Сделка {deal.id}</h1>
      {error ? <p className="banner error">{error}</p> : null}
      {notice ? <p className="banner conflict">{notice}</p> : null}
      <p className={`banner ${bannerClass}`}>{syncMessage(deal)}</p>
      <p className="muted">
        Внешняя версия: <span className="mono">{deal.external_version ?? 'нет'}</span>
        {deal.external_updated_at ? `, обновлена ${formatDateTime(deal.external_updated_at)}` : ''}
        {deal.external_id ? <>, external_id <span className="mono">{deal.external_id}</span></> : ', внешний идентификатор не задан'}
      </p>
      {deal.latest_export?.status === 'failed' && !deal.external_id ? (
        <p><button className="button-secondary" type="button" disabled={isExportPending(deal)} onClick={() => retryExport(id).then((result) => setDeal(result.data)).catch((reason: Error) => setError(reason.message))}>Повторить отправку</button></p>
      ) : null}
      <div className="fields">
        {(['title', 'status', 'amount'] as FieldName[]).map((field) => {
          const override = deal.overrides.find((item) => item.field === field)
          const external = field === 'title' ? deal.external_title : field === 'status' ? deal.external_status : deal.external_amount
          return (
            <article key={field} className={override?.is_conflicted ? 'field conflict' : 'field'}>
              <h2>
                {FIELD_LABELS[field]}
                {override?.is_conflicted ? <span className="badge">Конфликт</span> : null}
              </h2>
              <dl>
                <div><dt>На экране</dt><dd className={field === 'status' ? undefined : 'mono'}>{field === 'status' ? statusLabel(deal[field]) : (deal[field] ?? '—')}</dd></div>
                <div><dt>Внешнее</dt><dd className={field === 'status' ? undefined : 'mono'}>{field === 'status' ? statusLabel(external) : (external ?? '—')}</dd></div>
                <div><dt>Локальная правка</dt><dd className={field === 'status' ? undefined : 'mono'}>{override ? (field === 'status' ? `${statusLabel(override.local)} (база ${statusLabel(override.base)})` : `${override.local} (база ${override.base})`) : '—'}</dd></div>
              </dl>
              {override ? (
                <div className="actions">
                  <button className="button-secondary" type="button" onClick={() => onResolve(field, 'accept_external')}>Принять внешнее</button>
                  <button className="button-secondary" type="button" onClick={() => onResolve(field, 'keep_local')}>Оставить моё</button>
                </div>
              ) : null}
            </article>
          )
        })}
      </div>
      <form className="panel" onSubmit={onSave}>
        <h2>Правка</h2>
        <div className="form-grid">
          <label>Название<input value={form.title} onChange={(event) => setForm({ ...form, title: event.target.value })} /></label>
          <label>Статус
            <select value={form.status} onChange={(event) => setForm({ ...form, status: event.target.value })}>
              {(STATUSES.includes(form.status as typeof STATUSES[number]) ? STATUSES : [form.status, ...STATUSES]).map((item) => (
                <option key={item} value={item}>{statusLabel(item)}</option>
              ))}
            </select>
          </label>
          <label>Сумма<input value={form.amount} onChange={(event) => setForm({ ...form, amount: event.target.value })} /></label>
          <button className="button-primary" type="submit">Сохранить</button>
        </div>
      </form>
      <div className="panel row">
        <label>Сценарий внешнего API
          <select value={scenario} onChange={(event) => setScenario(event.target.value)}>
            <option value="success">success</option>
            <option value="rate_limit">rate_limit</option>
            <option value="failure">failure</option>
          </select>
        </label>
        <button className="button-primary" type="button" disabled={!deal.external_id || isRefreshPending(deal)} onClick={onRefresh}>Обновить из внешней CRM</button>
      </div>
    </>
  )
}
