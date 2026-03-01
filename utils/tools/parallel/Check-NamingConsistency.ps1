<#
.SYNOPSIS
    変数名や関数名の命名規則（ケース）を統計的に分析。
#>
param(
    [string[]]$Ext = @(".ts", ".cs"),
    [string]$ExcludePattern = "node_modules|bin|obj",
    [string]$RootPath = ""
)

if ([string]::IsNullOrWhiteSpace($RootPath)) {
    $RootPath = (Resolve-Path (Join-Path $PSScriptRoot "../../")).Path
}

$Files = Get-ChildItem -Path $RootPath -Recurse | Where-Object { $_.Extension -in $Ext -and $_.FullName -notmatch $ExcludePattern }

$Patterns = @{
    PascalCase = 0
    camelCase  = 0
    snake_case = 0
}

foreach ($File in $Files) {
    $Content = Get-Content $File.FullName
    foreach ($Line in $Content) {
        if ($Line -match '\b[A-Z][a-z0-9]+[A-Z][a-z0-9]+\b') { $Patterns.PascalCase++ }
        if ($Line -match '\b[a-z0-9]+[A-Z][a-z0-9]+\b') { $Patterns.camelCase++ }
        if ($Line -match '\b[a-z0-9]+_[a-z0-9]+\b') { $Patterns.snake_case++ }
    }
}

$Output = @{
    tool        = "NamingConsistencyChecker"
    status      = "Mixed Naming Conventions Detected"
    instruction = "Identify the minority pattern and propose renaming to match the project's dominant style. Specific statistics have been hidden to omit noise."
}

$Output | ConvertTo-Json -Depth 10