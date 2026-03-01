<#
.SYNOPSIS
    定義（export/public）されたシンボルが他所で使われているか簡易走査。
#>
param(
    [string[]]$Ext = @(".ts", ".cs", ".php"),
    [string]$ExcludePattern = "node_modules|bin|obj",
    [string]$RootPath = ""
)

if ([string]::IsNullOrWhiteSpace($RootPath)) {
    $RootPath = (Resolve-Path (Join-Path $PSScriptRoot "../../")).Path
}
$NormalizedExt = $Ext | ForEach-Object { if ($_ -notlike ".*") { ".$_" } else { $_ } }

$AllFiles = Get-ChildItem -Path $RootPath -Recurse | Where-Object { 
    $_.Extension -in $NormalizedExt -and $_.FullName -notmatch $ExcludePattern
}

# 1. 定義されているシンボルを抽出
$Definitions = @()
foreach ($File in $AllFiles) {
    $Content = Get-Content $File.FullName
    $Lines = $Content | Where-Object { $_ -match '(export\s+(const|class|function|type|interface|enum)\s+|public\s+\w+\s+)(\w+)' }
    
    foreach ($Line in $Lines) {
        if ($Line -match '(?<symbol>\w+)\s*[:=\({]') {
            $Definitions += @{
                symbol = $Matches['symbol']
                file   = $File.FullName.Substring($RootPath.Length + 1)
            }
        }
    }
}

# 2. 全ファイルに対してGrep（簡易的な利用確認）
$Results = foreach ($Def in $Definitions) {
    $Symbol = $Def.symbol
    # 自分以外のファイルで、その文字列が出現するか確認
    $Usages = $AllFiles | Where-Object { $_.FullName -notmatch [regex]::Escape($Def.file) } | 
    Select-String -Pattern "\b$Symbol\b" -Quiet

    if (-not $Usages) {
        @{
            symbol    = $Symbol
            definedIn = $Def.file
            status    = "Potential Orphan"
        }
    }
}

$Output = @{
    tool        = "OrphanedSymbolFinder"
    data        = $Results
    instruction = "These symbols are defined as public/export but not referenced in other files. Confirm if they can be deleted or made private."
}

$Output | ConvertTo-Json -Depth 10