import { defineConfig } from 'vite'
import react from '@vitejs/plugin-react'

// https://vite.dev/config/
export default defineConfig({
  plugins: [react()],
  base: '/smartfarm-admin/',  // Apache Alias와 일치
  build: {
    outDir: 'dist',
    emptyOutDir: false,  // 기존 파일 삭제하지 않고 덮어쓰기
    rollupOptions: {
      output: {
        entryFileNames: `assets/[name]-[hash]-v${Date.now()}.js`,
        chunkFileNames: `assets/[name]-[hash]-v${Date.now()}.js`,
        assetFileNames: `assets/[name]-[hash]-v${Date.now()}.[ext]`
      }
    }
  }
})
