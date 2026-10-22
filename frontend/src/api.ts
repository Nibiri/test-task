import type { Deal, DealPage, FieldName } from './types'

const json = async <T>(response: Response): Promise<T> => {
  const body = await response.json().catch(() => ({}))
  if (!response.ok) {
    const error = new Error(body.message ?? 'Ошибка запроса') as Error & { status: number }
    error.status = response.status
    throw error
  }
  return body as T
}

export const fetchDeals = (params: { search: string; status: string; page: number }) => {
  const query = new URLSearchParams()
  if (params.search) query.set('search', params.search)
  if (params.status) query.set('status', params.status)
  query.set('page', String(params.page))
  return fetch(`/api/deals?${query}`).then((response) => json<DealPage>(response))
}

export const fetchDeal = (id: number) =>
  fetch(`/api/deals/${id}`).then((response) => json<{ data: Deal }>(response))

export const createDeal = (input: { title: string; status: string; amount: string }) =>
  fetch('/api/deals', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
    body: JSON.stringify({
      title: input.title,
      status: input.status,
      amount: Number(input.amount),
    }),
  }).then((response) => json<{ data: Deal }>(response))

export const retryExport = (id: number) =>
  fetch(`/api/deals/${id}/export`, {
    method: 'POST',
    headers: { Accept: 'application/json' },
  }).then((response) => json<{ data: Deal }>(response))

export const updateDeal = (id: number, input: { title: string; status: string; amount: string }) =>
  fetch(`/api/deals/${id}`, {
    method: 'PATCH',
    headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
    body: JSON.stringify({
      title: input.title,
      status: input.status,
      amount: Number(input.amount),
    }),
  }).then((response) => json<{ data: Deal }>(response))

export const refreshDeal = (id: number, scenario: string) =>
  fetch(`/api/deals/${id}/refresh`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
    body: JSON.stringify({ scenario }),
  }).then((response) => json<{ data: Deal }>(response))

export const resolveField = (id: number, field: FieldName, action: 'accept_external' | 'keep_local', externalVersion: number) =>
  fetch(`/api/deals/${id}/resolutions`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
    body: JSON.stringify({ field, action, external_version: externalVersion }),
  }).then((response) => json<{ data: Deal }>(response))
