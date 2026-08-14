param(
    [string]$commitMessage = "chore: deploy update"
)

Write-Host "=========================================" -ForegroundColor Cyan
Write-Host "🚀 Wawi Kadio - Deployment Prep Script" -ForegroundColor Cyan
Write-Host "=========================================" -ForegroundColor Cyan
Write-Host ""


Write-Host "🔄 2/4 - Adding changes to Git..." -ForegroundColor Yellow
git add .

Write-Host "📝 3/4 - Committing changes..." -ForegroundColor Yellow
git commit -m $commitMessage

Write-Host "☁️ 4/4 - Pushing to GitHub (origin main)..." -ForegroundColor Yellow
git push origin main
if ($LASTEXITCODE -ne 0) {
    Write-Host "❌ Gagal melakukan push ke GitHub!" -ForegroundColor Red
    exit 1
}

Write-Host ""
Write-Host "✅ SELESAI! Semua perubahan berhasil di-build dan di-push ke GitHub." -ForegroundColor Green
Write-Host "=========================================" -ForegroundColor Cyan
Write-Host "Langkah selanjutnya di server aaPanel Anda:" -ForegroundColor White
Write-Host "  1. Buka terminal aaPanel di folder proyek"
Write-Host "  2. Ketik: git pull origin main"
Write-Host "  3. Ketik: php artisan migrate"
Write-Host "  4. Ketik: php artisan optimize:clear"
Write-Host "=========================================" -ForegroundColor Cyan
