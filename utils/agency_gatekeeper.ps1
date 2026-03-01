<#
.SYNOPSIS
    AIエージェント専用：自律型統合診断ゲートキーパー
    agency_gatekeeper/tools フォルダ内の全スクリプトを自動実行します。
#>
param(
    [string[]]$Ext = @(".ts", ".php", ".scss", ".cs", ".html"),
    [string]$ExcludePattern = "node_modules|vendor|bin|obj|\.git",
    [string]$TargetRoot = ""
)
if ([string]::IsNullOrWhiteSpace($TargetRoot)) {
    $TargetRoot = (Resolve-Path (Join-Path $PSScriptRoot "../")).Path
}

$BaseDir = Split-Path -Parent $MyInvocation.MyCommand.Path
$ToolsDir = Join-Path $BaseDir "tools"
$ParallelDir = Join-Path $ToolsDir "parallel"
$SerialDir = Join-Path $ToolsDir "serial"

$ParallelTools = if (Test-Path $ParallelDir) { Get-ChildItem -Path $ParallelDir -Filter *.ps1 } else { @() }
$SerialTools = if (Test-Path $SerialDir) { Get-ChildItem -Path $SerialDir -Filter *.ps1 } else { @() }

$TotalTools = $ParallelTools.Count + $SerialTools.Count
Write-Host "Found $TotalTools tools ($($ParallelTools.Count) parallel, $($SerialTools.Count) serial). Starting execution..." -ForegroundColor Cyan

$AllResults = @()


# -------------------------------------------------------------
# [アーキテクチャ上の決定事項 - 意図的なコードの重複]
# PowerShellの -Parallel は別プロセス空間(Runspace)で実行されるため、
# 親スコープで定義した通常の関数をそのまま呼び出すことができません。
# 複雑なワークアラウンド（文字列コンパイル等）を用いてDRY原則を固守するよりも、
# 直感的で可読性の高いコード構造を維持することを優先し、
# あえてフェーズ1とフェーズ2で実行ロジックの重複を許容しています。
# -------------------------------------------------------------

# -------------------------------------------------------------
# フェーズ1：並列実行 (I/O競合がないツール群)
# -------------------------------------------------------------
if ($ParallelTools.Count -gt 0) {
    Write-Host "`n[Phase 1] Running $($ParallelTools.Count) tools in PARALLEL..." -ForegroundColor Cyan
    
    $ParallelResults = $ParallelTools | ForEach-Object -Parallel {
        $ToolFile = $_
        # 別スレッドのため $using: で親スコープの変数を持ち込む
        $Ext = $using:Ext
        $ExcludePattern = $using:ExcludePattern
        $TargetRoot = $using:TargetRoot
        
        Write-Host " [Parallel] Executing: $($ToolFile.Name)..." -ForegroundColor DarkGray
        
        try {
            $CmdInfo = Get-Command $ToolFile.FullName
            if ([bool]($CmdInfo.Parameters.Values | Where-Object { $_.Attributes.Mandatory -contains $true })) { return }

            $CommandParams = @{}
            if ($CmdInfo.Parameters.ContainsKey('Ext')) { $CommandParams['Ext'] = $Ext }
            if ($CmdInfo.Parameters.ContainsKey('ExcludePattern')) { $CommandParams['ExcludePattern'] = $ExcludePattern }
            if ($CmdInfo.Parameters.ContainsKey('RootPath')) { $CommandParams['RootPath'] = $TargetRoot }

            $RawOutput = & $ToolFile.FullName @CommandParams
            if (-not [string]::IsNullOrWhiteSpace($RawOutput)) {
                @{ tool_name = $ToolFile.BaseName; status = "success"; result = ($RawOutput | ConvertFrom-Json) }
            }
        }
        catch {
            Write-Host " [Parallel] Failed $($ToolFile.Name): $($_.Exception.Message)" -ForegroundColor Yellow
            @{ tool_name = $ToolFile.BaseName; status = "failed"; error = $_.Exception.Message }
        }
    } -ThrottleLimit 5

    $AllResults += @($ParallelResults | Where-Object { $null -ne $_ })
}

# -------------------------------------------------------------
# フェーズ2：直列実行 (I/Oを独占するツール群)
# -------------------------------------------------------------
if ($SerialTools.Count -gt 0) {
    Write-Host "`n[Phase 2] Running $($SerialTools.Count) tools in SERIAL..." -ForegroundColor Cyan
    
    $SerialResults = $SerialTools | ForEach-Object {
        $ToolFile = $_
        # 同一スレッドなので親スコープの変数をそのまま使用可能
        Write-Host " [Serial] Executing: $($ToolFile.Name)..." -ForegroundColor DarkGray
        
        try {
            $CmdInfo = Get-Command $ToolFile.FullName
            if ([bool]($CmdInfo.Parameters.Values | Where-Object { $_.Attributes.Mandatory -contains $true })) { return }

            $CommandParams = @{}
            if ($CmdInfo.Parameters.ContainsKey('Ext')) { $CommandParams['Ext'] = $Ext }
            if ($CmdInfo.Parameters.ContainsKey('ExcludePattern')) { $CommandParams['ExcludePattern'] = $ExcludePattern }
            if ($CmdInfo.Parameters.ContainsKey('RootPath')) { $CommandParams['RootPath'] = $TargetRoot }

            $RawOutput = & $ToolFile.FullName @CommandParams
            if (-not [string]::IsNullOrWhiteSpace($RawOutput)) {
                @{ tool_name = $ToolFile.BaseName; status = "success"; result = ($RawOutput | ConvertFrom-Json) }
            }
        }
        catch {
            Write-Host " [Serial] Failed $($ToolFile.Name): $($_.Exception.Message)" -ForegroundColor Yellow
            @{ tool_name = $ToolFile.BaseName; status = "failed"; error = $_.Exception.Message }
        }
    }

    $AllResults += @($SerialResults | Where-Object { $null -ne $_ })
}

# -------------------------------------------------------------
# 結果の統合と出力
# -------------------------------------------------------------
$OutputPackage = @{
    timestamp    = (Get-Date -Format "yyyy-MM-dd HH:mm:ss")
    details      = $AllResults
    ai_directive = "Based on the collected data in 'details', identify refactoring priorities. Use 'tool_name' as context. Focus on complexity and deep-nesting first."
}

$OutputPackage | ConvertTo-Json -Depth 10 -Compress