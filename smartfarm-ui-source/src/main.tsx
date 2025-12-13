import { StrictMode } from 'react'
import { createRoot } from 'react-dom/client'
import { BrowserRouter, Routes, Route } from 'react-router-dom'
import './index.css'
import App from './App.tsx'
import CameraPage from './pages/CameraPage.tsx'

createRoot(document.getElementById('root')!).render(
  <StrictMode>
    <BrowserRouter basename="/smartfarm-ui">
      <Routes>
        {/* 기존 스마트팜 환경제어 앱 (루트 경로) */}
        <Route path="/" element={<App />} />

        {/* Tapo 카메라 모니터링 페이지 */}
        <Route path="/camera" element={<CameraPage />} />
      </Routes>
    </BrowserRouter>
  </StrictMode>,
)
