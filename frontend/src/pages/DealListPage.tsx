import { FormEvent, useEffect, useState } from 'react'
import { useNavigate } from 'react-router-dom'
import { createDeal, fetchDeals } from '../api'
import { statusLabel } from '../labels'
import { STATUSES, type Deal } from '../types'

export const DealListPage = () => {
  const navigate = useNavigate()
  const [search, setSearch] = useState('')
  const [status, setStatus] = useState('')
  const [page, setPage] = useState(1)
  const [deals, setDeals] = useState<Deal[]>([])
  const [lastPage, setLastPage] = useState(1)
  const [error, setError] = useState('')
  const [title, setTitle] = useState('')
  const [newStatus, setNewStatus] = useState<string>(STATUSES[0])
  const [amount, setAmount] = useState('0')

  useEffect(() => {
    const timer = window.setTimeout(() => {
      fetchDeals({ search, status, page })
        .then((result) => {
          setDeals(result.data)
          setLastPage(result.meta.last_page)
          setError('')
        })
        .catch((reason: Error) => setError(reason.message))
    }, 300)
    return () => window.clearTimeout(timer)
  }, [search, status, page])

  const onCreate = (event: FormEvent) => {
    event.preventDefault()
    createDeal({ title, status: newStatus, amount })
      .then((result) => {
        setTitle('')
        setAmount('0')
        navigate(`/deals/${result.data.id}`)
      })
      .catch((reason: Error) => setError(reason.message))
  }

  return (
    <>
      <h1>Сделки</h1>
      {error ? <p className="banner error">{error}</p> : null}
      <form className="row filter" onSubmit={(event) => { event.preventDefault(); setPage(1) }}>
        <label>Поиск<input placeholder="Название сделки" value={search} onChange={(event) => { setSearch(event.target.value); setPage(1) }} /></label>
        <label>Статус
          <select value={status} onChange={(event) => { setStatus(event.target.value); setPage(1) }}>
            <option value="">Все</option>
            {STATUSES.map((item) => <option key={item} value={item}>{statusLabel(item)}</option>)}
          </select>
        </label>
        <button className="button-secondary" type="submit">Найти</button>
      </form>
      <div className="table-wrap">
        <table>
          <thead>
            <tr><th>ID</th><th>Внешний ID</th><th>Название</th><th>Статус</th><th>Сумма</th><th>Версия</th></tr>
          </thead>
          <tbody>
            {deals.map((deal) => (
              <tr
                key={deal.id}
                className={deal.has_conflict ? 'conflict' : undefined}
                role="link"
                aria-label={deal.title ?? `Сделка ${deal.id}`}
                tabIndex={0}
                onClick={() => navigate(`/deals/${deal.id}`)}
                onKeyDown={(event) => {
                  if (event.key === 'Enter') navigate(`/deals/${deal.id}`)
                }}
              >
                <td className="mono">{deal.id}</td>
                <td className="mono">{deal.external_id ?? '—'}</td>
                <td>
                  {deal.title}
                  {deal.has_conflict ? <span className="badge">Конфликт</span> : null}
                </td>
                <td>{statusLabel(deal.status)}</td>
                <td className="mono">{deal.amount}</td>
                <td className="mono">{deal.external_version ?? '—'}</td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>
      <p className="pager">
        <button className="button-secondary" type="button" disabled={page <= 1} onClick={() => setPage((current) => current - 1)}>Назад</button>
        <span>{page} / {lastPage}</span>
        <button className="button-secondary" type="button" disabled={page >= lastPage} onClick={() => setPage((current) => current + 1)}>Дальше</button>
      </p>
      <form className="panel" onSubmit={onCreate}>
        <h2>Новая сделка</h2>
        <div className="form-grid">
          <label>Название<input value={title} onChange={(event) => setTitle(event.target.value)} required /></label>
          <label>Статус
            <select value={newStatus} onChange={(event) => setNewStatus(event.target.value)}>
              {STATUSES.map((item) => <option key={item} value={item}>{statusLabel(item)}</option>)}
            </select>
          </label>
          <label>Сумма<input value={amount} onChange={(event) => setAmount(event.target.value)} required /></label>
          <button className="button-primary" type="submit">Создать в CRM</button>
        </div>
      </form>
    </>
  )
}
