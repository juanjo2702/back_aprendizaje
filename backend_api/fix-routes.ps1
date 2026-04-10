$content = Get-Content -Path backend_api/routes/api.php -Raw
$content = $content -replace 'Route::post\\(\\'/categories\\', \\[CategoryController::class, \\'store\\'\\]\\);', 'Route::post(\\'/categories\\', [CategoryController::class, \\'store\\'])->middleware(\\'admin\\');'
$content = $content -replace 'Route::put\\(\\'/categories/\\{category\\}\\', \\[CategoryController::class, \\'update\\'\\]\\);', 'Route::put(\\'/categories/{category}\\', [CategoryController::class, \\'update\\'])->middleware(\\'admin\\');'
$content = $content -replace 'Route::delete\\(\\'/categories/\\{category\\}\\', \\[CategoryController::class, \\'destroy\\'\\]\\);', 'Route::delete(\\'/categories/{category}\\', [CategoryController::class, \\'destroy\\'])->middleware(\\'admin\\');'
Set-Content -Path backend_api/routes/api.php -Value $content -Encoding UTF8
