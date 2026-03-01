<#
.SYNOPSIS
    SCSSファイルのネスト深度をチェックし、AIが理解可能なJSON形式で出力します。
#>
param(
    [string[]]$Ext = @(".scss"),
    [string]$ExcludePattern = "node_modules|bin|obj",
    [string]$RootPath = ""
)

if ([string]::IsNullOrWhiteSpace($RootPath)) {
    $RootPath = (Resolve-Path (Join-Path $PSScriptRoot "../../")).Path
}
$Threshold = 4 # 警告を出す深度のしきい値

$Files = Get-ChildItem -Path $RootPath -Recurse -Filter *.scss | Where-Object { $_.FullName -notmatch $ExcludePattern }

$Results = foreach ($File in $Files) {
    $RelativePath = $File.FullName.Substring($RootPath.Length + 1)
    $Content = Get-Content -Path $File.FullName -Encoding UTF8
    $Depth = 0
    $LineNumber = 0
    $Violations = @()

    foreach ($Line in $Content) {
        $LineNumber++
        $TrimmedLine = $Line.Trim()

        if ([string]::IsNullOrWhiteSpace($TrimmedLine) -or $TrimmedLine.StartsWith("//")) {
            continue
        }

        $OpenCount = ($TrimmedLine.Length - $TrimmedLine.Replace("{", "").Length)
        $CloseCount = ($TrimmedLine.Length - $TrimmedLine.Replace("}", "").Length)

        # 深度が4以上になる瞬間を記録
        if ($OpenCount -gt 0 -and $Depth -ge ($Threshold - 1)) {
            $Violations += @{
                line         = $LineNumber
                currentDepth = $Depth + 1
                code         = $TrimmedLine
            }
        }
        $Depth += ($OpenCount - $CloseCount)
    }

    if ($Violations.Count -gt 0) {
        @{
            file           = $RelativePath
            violationCount = $Violations.Count
            details        = $Violations
        }
    }
}

$Output = @{
    tool        = "ScssDepthChecker"
    threshold   = $Threshold
    data        = $Results
    instruction = "Files with deep nesting (Depth >= 4) should be refactored using BEM or Mixins to flatten the structure."
}

$Output | ConvertTo-Json -Depth 10