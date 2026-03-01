<#
.SYNOPSIS
    AIエージェント専用：自律型統合診断ゲートキーパー
    agency_gatekeeper/tools フォルダ内の全スクリプトを自動実行します。
#>
param(
    [string[]]$Ext = @(".ts", ".php", ".scss", ".cs", ".html"),
    [string]$ExcludePattern = "node_modules|bin|obj|\.git",
    [string]$TargetRoot = ""
)

if ([string]::IsNullOrWhiteSpace($TargetRoot)) {
    $TargetRoot = (Resolve-Path (Join-Path $PSScriptRoot "../")).Path
}

# 自身のディレクトリとツールフォルダの特定
$BaseDir = Split-Path -Parent $MyInvocation.MyCommand.Path
$ToolsDir = Join-Path $BaseDir "tools"

$OutputPackage = @{
    timestamp = (Get-Date -Format "yyyy-MM-dd HH:mm:ss")
    details   = @()
}

# ツールフォルダが存在するか確認
if (-not (Test-Path $ToolsDir)) {
    Write-Host "Error: 'tools' folder not found at $ToolsDir" -ForegroundColor Red
    exit 1
}

# tools フォルダ内の .ps1 ファイルを全件取得 (自身は除外)
$ToolFiles = Get-ChildItem -Path $ToolsDir -Filter *.ps1 | Where-Object { $_.Name -ne $MyInvocation.MyCommand.Name }

# 進捗は Write-Host (ホスト出力) を使用。標準出力 (Stream 1) を汚さない。
Write-Host "Scanning tools in: $ToolsDir" -ForegroundColor Cyan
Write-Host "Found $($ToolFiles.Count) tools. Starting execution..." -ForegroundColor Cyan

$ToolResults = $ToolFiles | ForEach-Object -Parallel {
    $ToolFile = $_
    $Ext = $using:Ext
    $ExcludePattern = $using:ExcludePattern
    $TargetRoot = $using:TargetRoot
    Write-Host "Executing tool: $($ToolFile.Name)..." -ForegroundColor Gray
    
    try {
        # スクリプトのパラメータを動的に検査
        $CmdInfo = Get-Command $ToolFile.FullName
        $HasMandatory = [bool]($CmdInfo.Parameters.Values | Where-Object { $_.Attributes.Mandatory -contains $true })
        $HasExtParam = $CmdInfo.Parameters.ContainsKey('Ext')
        $HasExcludeParam = $CmdInfo.Parameters.ContainsKey('ExcludePattern')
        $HasRootParam = $CmdInfo.Parameters.ContainsKey('RootPath')

        # 必須パラメータ（手動入力前提）があるツールは、一括スキャンではスキップ
        if ($HasMandatory) {
            Write-Host "Skipping tool (requires manual context): $($ToolFile.Name)" -ForegroundColor DarkGray
            return
        }

        # 動的パラメータの構築
        $CommandParams = @{}
        if ($HasExtParam) { $CommandParams['Ext'] = $Ext }
        if ($HasExcludeParam) { $CommandParams['ExcludePattern'] = $ExcludePattern }
        if ($HasRootParam) { $CommandParams['RootPath'] = $TargetRoot }

        $RawOutput = & $ToolFile.FullName @CommandParams
        
        # 出力が空でないか確認してからデコード
        if (-not [string]::IsNullOrWhiteSpace($RawOutput)) {
            $DecodedOutput = $RawOutput | ConvertFrom-Json
            
            @{
                tool_name = $ToolFile.BaseName
                status    = "success"
                result    = $DecodedOutput
            }
        }
    }
    catch {
        # 実行エラーは JSON 内に記録し、ホストにも警告を出す
        Write-Host "Failed to execute $($ToolFile.Name): $($_.Exception.Message)" -ForegroundColor Yellow
        @{
            tool_name = $ToolFile.BaseName
            status    = "failed"
            error     = $_.Exception.Message
        }
    }
} -ThrottleLimit 5

$OutputPackage.details = @($ToolResults | Where-Object { $null -ne $_ })

# AIへの動的インストラクション
$OutputPackage.ai_directive = "Based on the collected data in 'details', identify refactoring priorities. Use 'tool_name' as context. Focus on complexity and deep-nesting first."

# 最終的な単一JSONの放出 (標準出力)
$OutputPackage | ConvertTo-Json -Depth 10 -Compress