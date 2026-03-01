<#
.SYNOPSIS
    論理構造の密度を計算し、リファクタリングの優先順位（Why）をAIに提示。
#>
param(
    [string[]]$Ext = @(".ts", ".php", ".cs"),
    [string]$ExcludePattern = "node_modules|bin|obj",
    [string]$RootPath = ""
)

if ([string]::IsNullOrWhiteSpace($RootPath)) {
    $RootPath = (Resolve-Path (Join-Path $PSScriptRoot "../../")).Path
}
$NormalizedExt = $Ext | ForEach-Object { if ($_ -notlike ".*") { ".$_" } else { $_ } }

$Results = Get-ChildItem -Path $RootPath -Recurse | Where-Object { 
    $_.Extension -in $NormalizedExt -and $_.FullName -notmatch $ExcludePattern
} | ForEach-Object {
    $Content = Get-Content $_.FullName
    $MaxScore = 0
    $CurrentScore = 0
    $BraceLevel = 0
    $PeakHotspots = @()
    $CurrentHotspots = @()
    $LineNum = 0

    foreach ($Line in $Content) {
        $LineNum++
        $BraceLevel += ([regex]::Matches($Line, '\{').Count - [regex]::Matches($Line, '\}').Count)

        # 複雑度を上げるキーワード（if, for, while, switch, catch, &&, ||）
        if ($Line -match '\b(if|for|while|switch|catch|foreach|case)\b|&&|\|\|') {
            $CurrentScore++
            $CurrentHotspots += @{ line = $LineNum; code = $Line.Trim() }
        }

        # クラス直下またはグローバルスコープに戻った時点でブロック終了とみなす
        if ($BraceLevel -le 1) {
            if ($CurrentScore -gt $MaxScore) {
                $MaxScore = $CurrentScore
                $PeakHotspots = $CurrentHotspots
            }
            $CurrentScore = 0
            $CurrentHotspots = @()
        }
    }
    if ($CurrentScore -gt $MaxScore) {
        $MaxScore = $CurrentScore
        $PeakHotspots = $CurrentHotspots
    }

    if ($MaxScore -gt 5) {
        # スコアが低いものはノイズとして除外
        @{
            file           = $_.FullName.Substring($RootPath.Length + 1)
            peakComplexity = $MaxScore
            majorHotspots  = $PeakHotspots | Select-Object -First 3 # 代表的な箇所のみ
        }
    }
}

$Output = @{
    tool        = "ComplexityHeatmap"
    filter      = $NormalizedExt
    data        = ($Results | Sort-Object peakComplexity -Descending)
    instruction = "High peakComplexity indicates refactoring candidates. Focus on peak blocks across files."
}

$Output | ConvertTo-Json -Depth 10