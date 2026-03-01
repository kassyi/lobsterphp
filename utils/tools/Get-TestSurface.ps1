<#
.SYNOPSIS
    ソースファイルに対応するテストファイルの有無を確認。
#>
param(
    [string[]]$Ext = @(".ts", ".cs"),
    [string]$ExcludePattern = "node_modules|bin|obj|Test",
    [string]$RootPath = ""
)

if ([string]::IsNullOrWhiteSpace($RootPath)) {
    $RootPath = (Resolve-Path (Join-Path $PSScriptRoot "../../")).Path
}
# 1. 全ファイルのキャッシュ化（ディスクI/O削減）
$AllFiles = Get-ChildItem -Path $RootPath -Recurse | Where-Object { $_.FullName -notmatch "node_modules|bin|obj" }

$SourceFiles = $AllFiles | Where-Object { $_.Extension -in $Ext -and $_.FullName -notmatch $ExcludePattern }
# "Test" が名前に含まれるファイルを抽出
$TestFilesCache = $AllFiles | Where-Object { $_.Name -match "(?i)Test" }

# 2. テスト結果の一括取得（jestプロセス起動を1回に削減）
$JestResultsMap = @{}
$HasPackageJson = Test-Path (Join-Path $RootPath "package.json")

if ($HasPackageJson) {
    if ($Host.Name -notmatch "Console") { Write-Host "Running tests once to determine coverage. This may take a moment..." -ForegroundColor Gray }
    
    $TempJsonPath = Join-Path $RootPath "ai_tmp\jest_cache_$(Get-Random).json"
    $AiTmpDir = Join-Path $RootPath "ai_tmp"
    if (-not (Test-Path $AiTmpDir)) { New-Item -ItemType Directory -Path $AiTmpDir -Force | Out-Null }

    try {
        # "--passWithNoTests" と "--json" で全体をスキャン
        $null = & npx jest --json --passWithNoTests --outputFile=$TempJsonPath --silent 2>$null
        
        if (Test-Path $TempJsonPath) {
            $JestJson = Get-Content $TempJsonPath -Raw | ConvertFrom-Json
            if ($null -ne $JestJson.testResults) {
                foreach ($res in $JestJson.testResults) {
                    $NormalizedName = $res.name -replace '\\', '/'
                    $JestResultsMap[$NormalizedName] = $res.status
                }
            }
            Remove-Item $TempJsonPath -Force
        }
    }
    catch {
        # エラーは握りつぶして N/A や unknown 扱いにする
    }
}

$Results = foreach ($File in $SourceFiles) {
    $BaseName = $File.BaseName
    $RegexSafeBase = [regex]::Escape($BaseName)
    
    # メモリ上の配列検索により一瞬で完了
    $TestFile = $TestFilesCache | Where-Object { $_.Name -match "(?i)$RegexSafeBase.*Test" } | Select-Object -First 1
    
    $TestStatus = "N/A"
    
    if ($TestFile) {
        if ($HasPackageJson) {
            $NormalizedTestPath = $TestFile.FullName -replace '\\', '/'
            $MatchedKey = $JestResultsMap.Keys | Where-Object { $_ -match [regex]::Escape($NormalizedTestPath) -or $NormalizedTestPath -match [regex]::Escape($_) } | Select-Object -First 1
            
            if ($MatchedKey) {
                $TestStatus = $JestResultsMap[$MatchedKey]
            }
            else {
                $TestStatus = "unknown"
            }
        }
        else {
            $TestStatus = "unknown"
        }
    }

    @{
        sourceFile = $File.FullName.Substring($RootPath.Length + 1)
        hasTest    = $null -ne $TestFile
        testFile   = if ($TestFile) { $TestFile.FullName.Substring($RootPath.Length + 1) } else { $null }
        testStatus = $TestStatus
    }
}

$Output = @{
    tool        = "TestSurfaceMapper"
    data        = $Results
    instruction = "Prioritize refactoring for files with 'hasTest: true'. For others, suggest creating tests first."
}

$Output | ConvertTo-Json -Depth 10