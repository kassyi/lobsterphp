<#
.SYNOPSIS
    引数で指定された拡張子のファイルから、AIが構造理解に必要な行（class, method等）を抽出。
.EXAMPLE
    ./Get-Interfaces.ps1 -Ext .ts, .cs
#>
param(
    [string[]]$Ext = @(".ts", ".php", ".scss", ".cs", ".html"),
    [string]$ExcludePattern = "node_modules|bin|obj|\.git",
    [string]$RootPath = ""
)

if ([string]::IsNullOrWhiteSpace($RootPath)) {
    $RootPath = (Resolve-Path (Join-Path $PSScriptRoot "../../")).Path
}

# 拡張子のドットを許容する正規化処理
$NormalizedExt = $Ext | ForEach-Object { if ($_ -notlike ".*") { ".$_" } else { $_ } }

$Files = Get-ChildItem -Path $RootPath -Recurse | Where-Object { 
    $_.Extension -in $NormalizedExt -and $_.FullName -notmatch $ExcludePattern
}

$Results = foreach ($File in $Files) {
    $Content = Get-Content $File.FullName
    
    $Signatures = @()
    $LineNum = 0
    $ActiveBlock = $null
    $BraceCount = 0

    foreach ($Line in $Content) {
        $LineNum++

        # ブロックの開始条件
        $isMatch = ($Line -match '^\s*(export\s+)?(class|interface|type|enum|public|protected|private|function)\s+\w+') -or
        ($Line -match '^\s*[\$@]\w+' -and $File.Extension -eq ".scss")

        if (-not $ActiveBlock -and $isMatch) {
            $ActiveBlock = @{ sig = $Line.Trim(); start = $LineNum; end = $LineNum }
            $BraceCount = ([regex]::Matches($Line, '\{').Count - [regex]::Matches($Line, '\}').Count)
            
            if ($BraceCount -le 0 -and (-not ($Line -match '\{') -or ($Line -match '\{.*\}'))) {
                $Signatures += $ActiveBlock
                $ActiveBlock = $null
            }
        }
        elseif ($ActiveBlock) {
            $BraceCount += ([regex]::Matches($Line, '\{').Count - [regex]::Matches($Line, '\}').Count)
            if ($BraceCount -le 0) {
                $ActiveBlock.end = $LineNum
                $Signatures += $ActiveBlock
                $ActiveBlock = $null
            }
        }
    }
    if ($ActiveBlock) {
        $ActiveBlock.end = $LineNum
        $Signatures += $ActiveBlock
    }

    if ($Signatures.Count -gt 0) {
        @{
            file = $File.FullName.Substring($RootPath.Length + 1)
            ext  = $File.Extension
            sigs = $Signatures
        }
    }
}

$Output = @{
    tool        = "InterfaceExtractor"
    filter      = $NormalizedExt
    data        = $Results
    instruction = "Review the structure. To see full logic of a specific file, request it by 'file' path."
}

$Output | ConvertTo-Json -Depth 10