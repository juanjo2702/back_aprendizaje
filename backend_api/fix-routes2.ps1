$lines = Get-Content -Path backend_api/routes/api.php
$newLines = @()
foreach ($line in $lines) {
    if ($line -match 'Route::post\\(\\'/categories\\', \\[CategoryController::class, \\'store\\'\\]\\);') {
        $newLines += $line -replace 'Route::post\\(\\'/categories\\', \\[CategoryController::class, \\'store\\'\\]\\);', 'Route::post(\\'/categories\\', [CategoryController::class, \\'store\\'])->middleware(\\'admin\\');'
    } elseif ($line -match 'Route::put\\(\\'/categories/\\{category\\}\\', \\[CategoryController::class, \\'update\\'\\]\\);') {
        $newLines += $line -replace 'Route::put\\(\\'/categories/\\{category\\}\\', \\[CategoryController::class, \\'update\\'\\]\\);', 'Route::put(\\'/categories/{category}\\', [CategoryController::class, \\'update\\'])->middleware(\\'admin\\');'
    } elseif ($line -match 'Route::delete\\(\\'/categories/\\{category\\}\\', \\[CategoryController::class, \\'destroy\\'\\]\\);') {
        $newLines += $line -replace 'Route::delete\\(\\'/categories/\\{category\\}\\', \\[CategoryController::class, \\'destroy\\'\\]\\);', 'Route::delete(\\'/categories/{category}\\', [CategoryController::class, \\'destroy\\'])->middleware(\\'admin\\');'
    } else {
        $newLines += $line
    }
}
Set-Content -Path backend_api/routes/api.php -Value $newLines -Encoding UTF8
