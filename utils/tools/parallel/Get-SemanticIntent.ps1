<#
.SYNOPSIS
    JSDoc, XMLコメント, TODOのみを抽出し、ロジック全文を読まずに意図を把握する。
#>
param(
    [string[]]$Ext = @(".ts", ".cs", ".php"),
    [Parameter(Mandatory = $true)][string]$FilePath,
    [string]$RootPath = ""
)

if ([string]::IsNullOrWhiteSpace($RootPath)) {
    $RootPath = (Resolve-Path (Join-Path $PSScriptRoot "../../")).Path
}

$FullContextPath = Resolve-Path (Join-Path $RootPath "\$FilePath")
$Content = Get-Content $FullContextPath

$Intents = @()
$LineNum = 0

foreach ($Line in $Content) {
    $LineNum++
    # JSDoc/XML/TODOを抽出する正規表現
    if ($Line -match '(\/\*\*|\/\/\/|<summary>|TODO:|FIXME:)' -or $Line -match '^\s*\*') {
        $Intents += @{
            line = $LineNum
            text = $Line.Trim()
        }
    }
}

$Output = @{
    tool        = "SemanticIntentExtractor"
    file        = $FilePath
    data        = $Intents
    instruction = "Use these comments to understand the 'Why' behind the implementation without reading the full logic."
}

$Output | ConvertTo-Json -Depth 10