import { Link, Route, Routes } from 'react-router-dom'
import { DealListPage } from './pages/DealListPage'
import { DealPage } from './pages/DealPage'

export const App = () => (
  <>
    <header className="top-nav">
      <Link to="/" className="wordmark">Сделки</Link>
    </header>
    <main>
      <Routes>
        <Route path="/" element={<DealListPage />} />
        <Route path="/deals/:id" element={<DealPage />} />
      </Routes>
    </main>
  </>
)
