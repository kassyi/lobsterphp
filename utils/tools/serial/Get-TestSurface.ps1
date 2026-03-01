<#
.SYNOPSIS
    ソースファイルに対応するテストファイルの有無を確認。
#>
<#
.NOTES
    【アーキテクチャ上の決定事項：直列実行の採用】
    当ツールでは複数ツールの自動実行において、意図的に直列実行を採用しています。
    PowerShellの別スレッド（Runspace）から php.exe などの外部コンソールアプリを呼び出すと、
    I/Oハンドル（特に標準入力）が競合し、プロセスがデッドロック状態に陥る構造的欠陥があるためです。
    メインスレッドに限定して直列実行することで、I/Oを正常に処理し、本来の速度と安定性を確保しています。
#>
param(
    [string[]]$Ext = @(".ts", ".cs", ".php"),
    [string]$ExcludePattern = "node_modules|vendor|bin|obj|Test",
    [string]$RootPath = ""
)

if ([string]::IsNullOrWhiteSpace($RootPath)) {
    $RootPath = (Resolve-Path (Join-Path $PSScriptRoot "../../")).Path
}

function Get-JestTestResults {
    param([string]$RootDir)
    $resultsMap = @{}
    $TempJsonPath = Join-Path $RootDir "ai_tmp\jest_cache_$(Get-Random).json"
    $AiTmpDir = Join-Path $RootDir "ai_tmp"
    if (-not (Test-Path $AiTmpDir)) { New-Item -ItemType Directory -Path $AiTmpDir -Force | Out-Null }

    if ($Host.Name -notmatch "Console") { Write-Host "Running tests once to determine coverage. This may take a moment..." -ForegroundColor Gray }
    
    try {
        $null = & npx jest --json --passWithNoTests --outputFile=$TempJsonPath --silent 2>$null
        if (Test-Path $TempJsonPath) {
            $JestJson = Get-Content $TempJsonPath -Raw | ConvertFrom-Json
            if ($null -ne $JestJson.testResults) {
                foreach ($res in $JestJson.testResults) {
                    $NormalizedName = $res.name -replace '\\', '/'
                    $resultsMap[$NormalizedName] = $res.status
                }
            }
        }
    }
    catch {
        # エラーは握りつぶす
    }
    finally {
        if (Test-Path $TempJsonPath) { Remove-Item $TempJsonPath -Force }
    }
    return $resultsMap
}

function Get-PhpUnitTestResults {
    param([string]$RootDir)
    $resultsMap = @{}
    $TempXmlPath = Join-Path $RootDir "ai_tmp\phpunit_cache_$(Get-Random).xml"
    $AiTmpDir = Join-Path $RootDir "ai_tmp"
    
    if (-not (Test-Path $AiTmpDir)) { New-Item -ItemType Directory -Path $AiTmpDir -Force | Out-Null }
    if ($Host.Name -notmatch "Console") { Write-Host "Running PHPUnit to determine coverage. This may take a moment..." -ForegroundColor Gray }
    
    try {
        $phpunitScript = Join-Path $RootDir "vendor\phpunit\phpunit\phpunit"
        if (-not (Test-Path $phpunitScript)) {
            $phpunitScript = Join-Path $RootDir "vendor\bin\phpunit"
        }
        
        # Run PHP directly and isolate stdout/stderr to files to prevent hanging in ForEach-Object -Parallel
        $null = & php $phpunitScript --log-junit $TempXmlPath 2>&1 | Out-Null
        
        if (Test-Path $TempXmlPath) {
            [xml]$PhpUnitXml = Get-Content $TempXmlPath -Raw
            $suitesWithFile = $PhpUnitXml.SelectNodes("//testsuite[@file]")
            if ($suitesWithFile) {
                foreach ($suite in $suitesWithFile) {
                    $NormalizedName = $suite.file -replace '\\', '/'
                    $failures = [int]($suite.failures)
                    $errors = [int]($suite.errors)
                    if ($failures -gt 0 -or $errors -gt 0) {
                        $resultsMap[$NormalizedName] = "failed"
                    }
                    else {
                        $resultsMap[$NormalizedName] = "passed"
                    }
                }
            }
        }
    }
    catch {
        # エラーは握りつぶす
    }
    finally {
        if (Test-Path $TempXmlPath) { Remove-Item $TempXmlPath -Force }
    }
    return $resultsMap
}

# 1. 全ファイルのキャッシュ化（ディスクI/O削減）
$AllFiles = Get-ChildItem -Path $RootPath -Recurse | Where-Object { $_.FullName -notmatch "node_modules|vendor|bin|obj" }
$SourceFiles = $AllFiles | Where-Object { $_.Extension -in $Ext -and $_.FullName -notmatch $ExcludePattern }
$TestFilesCache = $AllFiles | Where-Object { $_.Name -match "(?i)Test" }

# 2. テスト結果の一括取得
$TestResultsMap = @{}
$HasPackageJson = Test-Path (Join-Path $RootPath "package.json")
$HasComposerJson = Test-Path (Join-Path $RootPath "composer.json")

if ($HasPackageJson) {
    $TestResultsMap = Get-JestTestResults -RootDir $RootPath
}
elseif ($HasComposerJson -and (Test-Path (Join-Path $RootPath "vendor\bin\phpunit"))) {
    $TestResultsMap = Get-PhpUnitTestResults -RootDir $RootPath
}

$HasTestsRun = ($TestResultsMap.Count -gt 0)

$Results = foreach ($File in $SourceFiles) {
    $BaseName = $File.BaseName
    $RegexSafeBase = [regex]::Escape($BaseName)
    $TestFile = $TestFilesCache | Where-Object { $_.Name -match "(?i)$RegexSafeBase.*Test" } | Select-Object -First 1
    
    $TestStatus = "N/A"
    
    if ($TestFile) {
        if ($HasTestsRun) {
            $NormalizedTestPath = $TestFile.FullName -replace '\\', '/'
            $MatchedKey = $TestResultsMap.Keys | Where-Object { $_ -match [regex]::Escape($NormalizedTestPath) -or $NormalizedTestPath -match [regex]::Escape($_) } | Select-Object -First 1
            
            if ($MatchedKey) {
                $TestStatus = $TestResultsMap[$MatchedKey]
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
        sourceFile = $File.FullName.Substring($RootPath.Length).TrimStart('\', '/')
        hasTest    = $null -ne $TestFile
        testFile   = if ($TestFile) { $TestFile.FullName.Substring($RootPath.Length).TrimStart('\', '/') } else { $null }
        testStatus = $TestStatus
    }
}

$Output = @{
    tool        = "TestSurfaceMapper"
    data        = $Results
    instruction = "Prioritize refactoring for files with 'hasTest: true'. For others, suggest creating tests first."
}

$Output | ConvertTo-Json -Depth 10