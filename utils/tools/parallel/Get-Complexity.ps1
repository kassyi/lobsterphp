<#
.SYNOPSIS
    直書きの値（Hexカラー、px単位）を抽出・集計し、AIに変数化の判断を仰ぐ。
#>
param(
    [string[]]$Ext = @(".scss", ".html", ".css"),
    [string]$ExcludePattern = "node_modules|bin|obj",
    [string]$RootPath = ""
)

if ([string]::IsNullOrWhiteSpace($RootPath)) {
    $RootPath = (Resolve-Path (Join-Path $PSScriptRoot "../../")).Path
}
$NormalizedExt = $Ext | ForEach-Object { if ($_ -notlike ".*") { ".$_" } else { $_ } }

# 集計用ハッシュテーブル
$AssetMap = @{}

$Files = Get-ChildItem -Path $RootPath -Recurse | Where-Object { 
    $_.Extension -in $NormalizedExt -and $_.FullName -notmatch $ExcludePattern
}

foreach ($File in $Files) {
    $RelativePath = $File.FullName.Substring($RootPath.Length + 1)
    $Content = Get-Content $File.FullName

    $LineNum = 0
    foreach ($Line in $Content) {
        $LineNum++
        # 正規表現：Hexカラーコード、および px/rem/em 単位の数値
        $regex = [regex]::Matches($Line, '(#([a-fA-F0-9]{3}){1,2}\b)|(\b\d+(\.\d+)?(px|rem|em)\b)')
        
        foreach ($Match in $regex) {
            $Val = $Match.Value
            if (-not $AssetMap.ContainsKey($Val)) {
                $AssetMap[$Val] = @{ value = $Val; count = 0; locations = @() }
            }
            $AssetMap[$Val].count++
            if ($AssetMap[$Val].locations.Count -lt 3) {
                # JSON肥大化防止のため3件まで
                $AssetMap[$Val].locations += @{ file = $RelativePath; line = $LineNum }
            }
        }
    }
}

# 近似値のグループ化
$Consolidated = @()
foreach ($Key in $AssetMap.Keys) {
    if ($AssetMap[$Key].count -eq 0) { continue }
    
    $BaseAsset = $AssetMap[$Key]
    $Group = @($BaseAsset)
    
    foreach ($OtherKey in $AssetMap.Keys) {
        if ($OtherKey -eq $Key -or $AssetMap[$OtherKey].count -eq 0) { continue }
        
        $IsSimilar = $false
        if ($Key -match '^#' -and $OtherKey -match '^#') {
            if ($Key.Length -eq $OtherKey.Length -and $Key.Substring(0, 3) -eq $OtherKey.Substring(0, 3)) {
                $IsSimilar = $true
            }
        }
        elseif ($Key -match '^(\d+)(\.\d+)?(px|rem|em)$' -and $OtherKey -match '^(\d+)(\.\d+)?(px|rem|em)$') {
            $val1 = [double]($Key -replace '[^0-9\.]', '')
            $unit1 = $Key -replace '[0-9\.]', ''
            $val2 = [double]($OtherKey -replace '[^0-9\.]', '')
            $unit2 = $OtherKey -replace '[0-9\.]', ''
            if ($unit1 -eq $unit2 -and [math]::Abs($val1 - $val2) -le 2) {
                $IsSimilar = $true
            }
        }
        
        if ($IsSimilar) {
            $Group += $AssetMap[$OtherKey]
            $AssetMap[$OtherKey].count = 0
        }
    }
    
    $Consolidated += @{
        primaryValue  = $Key
        similarValues = ($Group | Select-Object -ExpandProperty value | Select-Object -Unique)
        totalCount    = ($Group | Measure-Object count -Sum).Sum
        locations     = ($Group | Select-Object -ExpandProperty locations | Select-Object -First 5)
    }
}

# 出力：出現頻度が高い順にソート
$SortedAssets = $Consolidated | Sort-Object totalCount -Descending | Select-Object -First 30

$Output = @{
    tool        = "AssetConsolidator"
    data        = $SortedAssets
    instruction = "Identify high-frequency values (count > 2). Propose a list of variables or common classes to consolidate these hardcoded values."
}

$Output | ConvertTo-Json -Depth 10