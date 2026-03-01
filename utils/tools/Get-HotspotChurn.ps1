<#
.SYNOPSIS
    Gitのログからファイルの変更頻度（Churn）を算出し、複雑度と掛け合わせるための基礎データを提供。
#>
param(
    [int]$Days = 30,
    [string[]]$Ext = @(".ts", ".cs", ".php", ".scss"),
    [string]$ExcludePattern = "node_modules|bin|obj",
    [string]$RootPath = ""
)

if ([string]::IsNullOrWhiteSpace($RootPath)) {
    $RootPath = (Resolve-Path (Join-Path $PSScriptRoot "../../")).Path
}

# Gitが利用可能か確認
if (-not (Get-Command git -ErrorAction SilentlyContinue)) {
    return @{ error = "Git is not installed or not in PATH" } | ConvertTo-Json
}

# 過去N日間の変更ファイル統計を取得
$LogData = git -C $RootPath log --since="$Days days ago" --name-only --pretty=format:
$Stats = $LogData | Where-Object { $_ -match "\.($($Ext -join '|').*)$" } | Group-Object | Select-Object Name, Count

$Results = foreach ($Stat in $Stats) {
    @{
        file  = $Stat.Name
        churn = $Stat.Count
    }
}

$Output = @{
    tool        = "HotspotChurnAnalyzer"
    periodDays  = $Days
    data        = ($Results | Sort-Object churn -Descending)
    instruction = "High churn files are volatile. Prioritize refactoring if these also have high complexity scores."
}

$Output | ConvertTo-Json -Depth 10