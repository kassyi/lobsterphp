<#
.SYNOPSIS
    定義（export/public）されたシンボルが他所で使われているか簡易走査。
#>
param(
    [string[]]$Ext = @(".ts", ".cs", ".php"),
    [string]$ExcludePattern = "node_modules|vendor|bin|obj|Test",
    [string]$RootPath = ""
)

if ([string]::IsNullOrWhiteSpace($RootPath)) {
    $RootPath = (Resolve-Path (Join-Path $PSScriptRoot "../../")).Path
}
$NormalizedExt = $Ext | ForEach-Object { if ($_ -notlike ".*") { ".$_" } else { $_ } }

$AllFiles = Get-ChildItem -Path $RootPath -Recurse | Where-Object { 
    $_.Extension -in $NormalizedExt -and $_.FullName -notmatch $ExcludePattern
}

# 1. 全ファイルの内容をメモリに一括ロード（ディスクI/Oの爆発を防ぐ）
$FileContentsCache = @{}
foreach ($File in $AllFiles) {
    # System.IO.File を使って高速に全テキストを読み込む
    $FileContentsCache[$File.FullName] = [System.IO.File]::ReadAllText($File.FullName)
}

# 2. 定義されているシンボルを抽出
$Definitions = @()
foreach ($File in $AllFiles) {
    # ディスクからではなくメモリキャッシュから取得
    $Content = $FileContentsCache[$File.FullName]
    
    # 行ごとに分割して処理
    $Lines = $Content -split '\r?\n' | Where-Object { 
        $_ -match 'export\s+(const|class|function|type|interface|enum)\s+' -or
        $_ -match 'public\s+(?:static\s+)?(?:function|const|readonly\s+\w+|\w+)\s+' -or
        $_ -match 'public\s+(?:static\s+)?\$' -or
        $_ -match '^\s*(?:abstract\s+|final\s+)?(?:class|interface|trait)\s+'
    }
    
    foreach ($Line in $Lines) {
        $Symbol = $null
        
        if ($Line -match '(?:class|interface|trait|function|const|enum|type)\s+(?<symbol>\w+)') {
            $Symbol = $Matches['symbol']
        }
        elseif ($Line -match 'public\s+(?:static\s+)?\$(?<symbol>\w+)') {
            $Symbol = $Matches['symbol']
        }
        elseif ($Line -match 'public\s+(?:static\s+)?(?:\w+\s+)?(?<symbol>\w+)\s*[:=\({]') {
            $Symbol = $Matches['symbol']
        }

        if ($Symbol -and $Symbol -notmatch '^__') {
            $Definitions += @{
                symbol = $Symbol
                file   = $File.FullName.Substring($RootPath.Length).TrimStart('\', '/')
            }
        }
    }
}

# 3. メモリ上で使用箇所を確認（超高速化）
$Results = foreach ($Def in $Definitions) {
    $Symbol = $Def.symbol
    $IsUsed = $false
    
    foreach ($File in $AllFiles) {
        # 自分のファイルはスキップ
        if ($File.FullName -match [regex]::Escape($Def.file)) { continue }
        
        # Select-String (ディスクI/O) をやめ、メモリ上の文字列に対して正規表現マッチ
        if ($FileContentsCache[$File.FullName] -match "\b$Symbol\b") {
            $IsUsed = $true
            break # 1つでも使用箇所が見つかれば即座に次のシンボルの検証へ進む
        }
    }

    if (-not $IsUsed) {
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