<#
.SYNOPSIS
    指定されたシンボルの参照箇所を高速にリストアップし、リファクタリングの影響範囲を可視化。
#>
param(
    [Parameter(Mandatory = $true)][string]$Symbol,
    [string[]]$Ext = @(".ts", ".cs", ".php"),
    [string]$ExcludePattern = "node_modules|bin|obj",
    [string]$RootPath = ""
)

if ([string]::IsNullOrWhiteSpace($RootPath)) {
    $RootPath = (Resolve-Path (Join-Path $PSScriptRoot "../../")).Path
}
$Files = Get-ChildItem -Path $RootPath -Recurse | Where-Object { $_.Extension -in $Ext -and $_.FullName -notmatch $ExcludePattern }

$Usages = foreach ($File in $Files) {
    $RelativePath = $File.FullName.Substring($RootPath.Length + 1)
    Select-String -Path $File.FullName -Pattern "\b$Symbol\b" | ForEach-Object {
        @{
            file = $RelativePath
            line = $_.LineNumber
            code = $_.Line.Trim()
        }
    }
}

$Output = @{
    tool        = "DependencyTracer"
    target      = $Symbol
    usageCount  = $Usages.Count
    data        = $Usages
    instruction = "Review these locations before renaming or changing the signature of '$Symbol'."
}

$Output | ConvertTo-Json -Depth 10