<#
.SYNOPSIS
    PHP / TypeScript / C# / SCSS の import / use 文を字句解析し、
    同一名前空間のものをグループ化してソートする再利用可能スクリプト。
.DESCRIPTION
    PHP    : use A\B\Foo; use A\B\Bar; => use A\B\{Bar, Foo};
    TS     : import {X} from './a'; import {Y} from './a'; => import {X, Y} from './a';
    C#     : using System.IO; using System.Linq; (ソート・重複除去)
    SCSS   : @use 'x'; @forward 'y'; (ソート・重複除去)

    ファイルを上書き保存します。
    Dry-run モード (-WhatIf) で差分のみ表示できます。

.PARAMETER Path
    対象ディレクトリまたはファイルパス。省略時はスクリプトの上位2階層。

.PARAMETER Exts
    処理する拡張子一覧。

.PARAMETER ExcludePattern
    除外対象パターン（正規表現）。

.PARAMETER WhatIf
    ファイルを書き換えず、変更予定の差分のみ表示する。

.EXAMPLE
    # さきに差分だけ確認
    .\Optimize-Imports.ps1 -WhatIf

    # 実際に書き換え
    .\Optimize-Imports.ps1

    # 特定ディレクトリのみ
    .\Optimize-Imports.ps1 -Path E:\Projects\myapp\src
#>
[CmdletBinding(SupportsShouldProcess)]
param(
    [string]$Path = "",
    [string[]]$Exts = @(".php", ".ts", ".tsx", ".cs", ".scss"),
    [string]$ExcludePattern = "node_modules|vendor|bin|obj|.git|dist",
    [int]$MaxLineLength = 120
)

Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'

if ([string]::IsNullOrWhiteSpace($Path)) {
    $Path = (Resolve-Path (Join-Path $PSScriptRoot "../../")).Path
}

# ─── ImportOptimizer（インターフェース契約） ──────────────────────────────────
#
#  PowerShell クラスはネイティブ interface キーワードを持たないため、
#  抽象基底クラスでコントラクトを定義する。
#  サブクラスは Optimize() を必ずオーバーライドすること。

class ImportOptimizer {

    # 折り返し文字数の上限（スクリプトパラメーターから注入される）
    [int]$MaxLineLength = 120

    # サブクラスごとに対応する拡張子の一覧を返す（契約メソッド①）
    [string[]] Extensions() {
        throw [System.NotImplementedException]::new(
            "$($this.GetType().Name) must implement Extensions()"
        )
    }

    # コンテンツを受け取り、import 文を最適化して返す（契約メソッド②）
    [string] Optimize([string]$content) {
        throw [System.NotImplementedException]::new(
            "$($this.GetType().Name) must implement Optimize()"
        )
    }

    # 共通ユーティリティ: 収集した行インデックスで元行列を置換して結合する
    hidden [string] Rebuild(
        [string[]]$lines,
        [System.Collections.Generic.List[int]]$indices,
        [System.Collections.Generic.List[string]]$newLines
    ) {
        $minIdx = ($indices | Measure-Object -Minimum).Minimum
        $maxIdx = ($indices | Measure-Object -Maximum).Maximum

        [string[]]$head = if ($minIdx -gt 0) { @($lines[0..($minIdx - 1)]) } else { @() }
        [string[]]$tail = if (($maxIdx + 1) -lt $lines.Count) { @($lines[($maxIdx + 1)..($lines.Count - 1)]) } else { @() }

        return ($head + @($newLines) + $tail) -join "`n"
    }
}

# ─── ImportDiffWriter ─────────────────────────────────────────────────────────

class ImportDiffWriter {
    static [void] Write([string]$file, [string]$before, [string]$after) {
        if ($before -eq $after) { return }
        Write-Host "`n=== $file ===" -ForegroundColor Cyan
        $bLines = $before -split "`n"
        $aLines = $after -split "`n"
        $len = [math]::Max($bLines.Count, $aLines.Count)
        for ($i = 0; $i -lt $len; $i++) {
            $b = if ($i -lt $bLines.Count) { $bLines[$i] } else { "" }
            $a = if ($i -lt $aLines.Count) { $aLines[$i] } else { "" }
            if ($b -ne $a) {
                Write-Host "  - $b" -ForegroundColor Red
                Write-Host "  + $a" -ForegroundColor Green
            }
        }
    }
}

# ─── PhpImportOptimizer ───────────────────────────────────────────────────────

class PhpImportOptimizer : ImportOptimizer {
    hidden [string] $UsePattern = '^use\s+([^;{]+?)\s*(?:\{([^}]+)\})?\s*;'

    [string[]] Extensions() { return @(".php") }

    [string] Optimize([string]$content) {
        $lines = $content -split "`r?`n"
        $prefixMap = [ordered]@{}
        $indices = [System.Collections.Generic.List[int]]::new()

        for ($i = 0; $i -lt $lines.Count; $i++) {
            $line = $lines[$i].Trim()
            if ($line -notmatch $this.UsePattern) { continue }

            $indices.Add($i)
            $prefix = $Matches[1].Trim()
            $grouped = $Matches[2]

            if ($grouped) {
                $nsPrefix = $prefix.TrimEnd('\')
                $classes = $grouped -split ',' | ForEach-Object { $_.Trim() } | Where-Object { $_ }
            }
            else {
                $lastSlash = $prefix.LastIndexOf('\')
                if ($lastSlash -ge 0) {
                    $nsPrefix = $prefix.Substring(0, $lastSlash)
                    $classes = @($prefix.Substring($lastSlash + 1))
                }
                else {
                    $nsPrefix = ""
                    $classes = @($prefix)
                }
            }

            if (-not $prefixMap.Contains($nsPrefix)) {
                $prefixMap[$nsPrefix] = [System.Collections.Generic.List[string]]::new()
            }
            foreach ($cls in $classes) {
                if (-not $prefixMap[$nsPrefix].Contains($cls)) { $prefixMap[$nsPrefix].Add($cls) }
            }
        }

        if ($indices.Count -eq 0) { return $content }

        $newLines = [System.Collections.Generic.List[string]]::new()
        foreach ($ns in ($prefixMap.Keys | Sort-Object)) {
            $classes = @($prefixMap[$ns] | Sort-Object)
            if ($ns -eq "") {
                foreach ($cls in $classes) { $newLines.Add("use $cls;") }
            }
            elseif ($classes.Count -eq 1) {
                $newLines.Add("use $ns\$($classes[0]);")
            }
            else {
                $newLines.Add($this.FormatUseGroup($ns, $classes))
            }
        }

        return $this.Rebuild($lines, $indices, $newLines)
    }

    # グループ化した use 文を MaxLineLength 文字を目安に折り返す。
    # クラス名の途中では分断しない。
    hidden [string] FormatUseGroup([string]$ns, [string[]]$classes) {
        [int]$maxLen    = $this.MaxLineLength
        [string]$indent = "    "                    # 継続行のインデント

        # 先頭行のプレフィックス: "use Ns\{"
        [string]$prefix = "use $ns\{"
        [string]$current = $prefix                  # 現在構築中の行
        [bool]$first = $true
        [System.Text.StringBuilder]$sb = [System.Text.StringBuilder]::new()

        foreach ($cls in $classes) {
            if ($first) {
                # 1クラス目はプレフィックスに直接追加
                $current += $cls
                $first = $false
            }
            else {
                # 次のクラスを ", " で繋いだときの長さを試算
                # 末尾の "};" は最終的に付くが、折り返し判定は ", Class" で十分
                $candidate = $current + ", " + $cls
                if ($candidate.Length -ge $maxLen) {
                    # 現在行を確定してから改行 + インデント
                    [void]$sb.AppendLine($current + ",")
                    $current = $indent + $cls
                }
                else {
                    $current = $candidate
                }
            }
        }

        # 最終行に "};" を付けて閉じる
        [void]$sb.Append($current + "};")
        return $sb.ToString()
    }
}

# ─── TsImportOptimizer ────────────────────────────────────────────────────────

class TsImportOptimizer : ImportOptimizer {
    hidden [string] $NamedPattern = "^import\s+(?:type\s+)?\{([^}]+)\}\s+from\s+['""]([^'""]+)['""]"
    hidden [string] $DefaultPattern = "^import\s+(\w+)\s+from\s+['""]([^'""]+)['""]"
    hidden [string] $SideEffectPattern = "^import\s+['""]([^'""]+)['""]"

    [string[]] Extensions() { return @(".ts", ".tsx") }

    [string] Optimize([string]$content) {
        $lines = $content -split "`r?`n"
        $importMap = [ordered]@{}
        $indices = [System.Collections.Generic.List[int]]::new()

        for ($i = 0; $i -lt $lines.Count; $i++) {
            $line = $lines[$i].Trim()

            if ($line -match $this.NamedPattern) {
                $indices.Add($i)
                $path = $Matches[2]
                $exports = $Matches[1] -split ',' | ForEach-Object { $_.Trim() } | Where-Object { $_ }
                $isType = ($line -match '^import\s+type\s+')
                if (-not $importMap.Contains($path)) {
                    $importMap[$path] = @{ named = [System.Collections.Generic.List[string]]::new(); default = $null; isType = $isType }
                }
                foreach ($exp in $exports) {
                    if (-not $importMap[$path].named.Contains($exp)) { $importMap[$path].named.Add($exp) }
                }
            }
            elseif ($line -match $this.DefaultPattern) {
                $indices.Add($i)
                $path = $Matches[2]
                if (-not $importMap.Contains($path)) {
                    $importMap[$path] = @{ named = [System.Collections.Generic.List[string]]::new(); default = $null; isType = $false }
                }
                $importMap[$path].default = $Matches[1]
            }
            elseif ($line -match $this.SideEffectPattern) {
                $indices.Add($i)
                $path = $Matches[1]
                if (-not $importMap.Contains($path)) {
                    $importMap[$path] = @{ named = [System.Collections.Generic.List[string]]::new(); default = $null; isType = $false; sideEffect = $true }
                }
            }
        }

        if ($indices.Count -eq 0) { return $content }

        $newLines = [System.Collections.Generic.List[string]]::new()
        foreach ($path in ($importMap.Keys | Sort-Object)) {
            $info = $importMap[$path]
            if ($info.ContainsKey('sideEffect') -and $info.sideEffect) {
                $newLines.Add("import '$path';"); continue
            }
            $keyword = if ($info.isType) { "import type " } else { "import " }
            if ($info.named.Count -gt 0) {
                $sortedNamed = @($info.named | Sort-Object)
                $namedStr    = $this.FormatImportGroup($keyword, $sortedNamed, $path, $info.default)
                $newLines.Add($namedStr)
            }
            elseif ($info.default) {
                $newLines.Add("${keyword}$($info.default) from '$path';")
            }
        }

        return $this.Rebuild($lines, $indices, $newLines)
    }

    # named import を MaxLineLength 文字を目安に折り返す。
    # import [type ]{A, B,\n    C, D} from 'path';
    # $defaultName が指定された場合: import Default, {A, B} from 'path';
    hidden [string] FormatImportGroup([string]$keyword, [string[]]$names, [string]$path, [string]$defaultName = "") {
        [int]$maxLen     = $this.MaxLineLength
        [string]$indent  = "    "
        # default がある場合は "import Default, {" 形式、ない場合は "import {"
        [string]$prefix  = if ($defaultName) { "${keyword}${defaultName}, {" } else { "${keyword}{" }
        [string]$suffix  = "} from '$path';"
        [string]$current = $prefix
        [bool]$first     = $true
        [System.Text.StringBuilder]$sb = [System.Text.StringBuilder]::new()

        foreach ($name in $names) {
            if ($first) {
                $current += $name
                $first = $false
            }
            else {
                $candidate = $current + ", " + $name
                # 末尾に suffix が付いた場合の長さで判定
                if (($candidate + $suffix).Length -ge $maxLen) {
                    [void]$sb.AppendLine($current + ",")
                    $current = $indent + $name
                }
                else {
                    $current = $candidate
                }
            }
        }

        [void]$sb.Append($current + $suffix)
        return $sb.ToString()
    }
}

# ─── CsImportOptimizer ────────────────────────────────────────────────────────

class CsImportOptimizer : ImportOptimizer {
    hidden [string] $UsingPattern = '^using\s+(?:static\s+)?[\w.]+\s*;'

    [string[]] Extensions() { return @(".cs") }

    [string] Optimize([string]$content) {
        $lines = $content -split "`r?`n"
        $usings = [System.Collections.Generic.List[string]]::new()
        $indices = [System.Collections.Generic.List[int]]::new()

        for ($i = 0; $i -lt $lines.Count; $i++) {
            $line = $lines[$i].Trim()
            if ($line -match $this.UsingPattern) { $usings.Add($line); $indices.Add($i) }
        }

        if ($indices.Count -eq 0) { return $content }

        $sorted = $usings | Select-Object -Unique | Sort-Object {
            $ns = $_ -replace '^using\s+(static\s+)?', '' -replace '\s*;$', ''
            if ($ns -like 'System*') { "0_$ns" } else { "1_$ns" }
        }

        $newLines = [System.Collections.Generic.List[string]]::new()
        $newLines.AddRange([string[]]@($sorted))
        return $this.Rebuild($lines, $indices, $newLines)
    }
}

# ─── ScssImportOptimizer ──────────────────────────────────────────────────────

class ScssImportOptimizer : ImportOptimizer {
    hidden [string] $UsePattern = "^@use\s+['""]([^'""]+)['""]"
    hidden [string] $ForwardPattern = "^@forward\s+['""]([^'""]+)['""]"
    hidden [string] $ImportPattern = "^@import\s+['""]([^'""]+)['""]"

    [string[]] Extensions() { return @(".scss") }

    [string] Optimize([string]$content) {
        $lines = $content -split "`r?`n"
        $useLines = [System.Collections.Generic.List[string]]::new()
        $fwdLines = [System.Collections.Generic.List[string]]::new()
        $importLines = [System.Collections.Generic.List[string]]::new()
        $indices = [System.Collections.Generic.List[int]]::new()

        for ($i = 0; $i -lt $lines.Count; $i++) {
            $line = $lines[$i].Trim()
            if ($line -match $this.UsePattern) { $useLines.Add($line); $indices.Add($i) }
            elseif ($line -match $this.ForwardPattern) { $fwdLines.Add($line); $indices.Add($i) }
            elseif ($line -match $this.ImportPattern) { $importLines.Add($line); $indices.Add($i) }
        }

        if ($indices.Count -eq 0) { return $content }

        $sorted = [System.Collections.Generic.List[string]]::new()
        if ($useLines.Count -gt 0) { $sorted.AddRange([string[]]@($useLines    | Select-Object -Unique | Sort-Object)) }
        if ($fwdLines.Count -gt 0) { $sorted.AddRange([string[]]@($fwdLines    | Select-Object -Unique | Sort-Object)) }
        if ($importLines.Count -gt 0) { $sorted.AddRange([string[]]@($importLines | Select-Object -Unique | Sort-Object)) }

        return $this.Rebuild($lines, $indices, $sorted)
    }
}

# ─── ImportOptimizationRunner ─────────────────────────────────────────────────

class ImportOptimizationRunner {
    hidden [hashtable] $OptimizerMap   # extension -> ImportOptimizer
    hidden [string]    $RootPath
    hidden [string]    $ExcludePattern
    hidden [string[]]  $Extensions

    ImportOptimizationRunner(
        [string]$rootPath,
        [string[]]$extensions,
        [string]$excludePattern,
        [ImportOptimizer[]]$optimizers,
        [int]$maxLineLength = 120
    ) {
        $this.RootPath = $rootPath
        $this.ExcludePattern = $excludePattern

        # 正規化された拡張子のセットを構築
        $normalized = $extensions | ForEach-Object { if ($_ -notlike ".*") { ".$_" } else { $_ } }
        $this.Extensions = $normalized

        # 各オプティマイザを対応拡張子へマッピング、MaxLineLength を注入
        $this.OptimizerMap = @{}
        foreach ($opt in $optimizers) {
            $opt.MaxLineLength = $maxLineLength
            foreach ($ext in $opt.Extensions()) {
                $this.OptimizerMap[$ext] = $opt
            }
        }
    }

    [int] Run([System.Management.Automation.PSCmdlet]$cmdlet) {
        $files = Get-ChildItem -Path $this.RootPath -Recurse -File | Where-Object {
            $_.Extension -in $this.Extensions -and $_.FullName -notmatch $this.ExcludePattern
        }

        $changedCount = 0
        foreach ($file in $files) {
            $changedCount += $this.ProcessFile($file, $cmdlet)
        }
        return $changedCount
    }

    hidden [int] ProcessFile([System.IO.FileInfo]$file, [System.Management.Automation.PSCmdlet]$cmdlet) {
        $ext = $file.Extension.ToLower()
        if (-not $this.OptimizerMap.ContainsKey($ext)) { return 0 }

        $rawContent = [System.IO.File]::ReadAllText($file.FullName)
        $content = $rawContent -replace "`r`n", "`n"

        # ポリモーフィズムで各言語の最適化処理に委譲
        $updated = $this.OptimizerMap[$ext].Optimize($content)

        if ($updated -eq $content) { return 0 }

        $relPath = $file.FullName.Substring($this.RootPath.Length).TrimStart('\', '/')

        if ($cmdlet.ShouldProcess($relPath, "Rewrite import statements")) {
            $finalContent = if ($rawContent -match "`r`n") {
                $updated -replace "(?<!`r)`n", "`r`n"
            }
            else {
                $updated
            }
            [System.IO.File]::WriteAllText($file.FullName, $finalContent, [System.Text.UTF8Encoding]::new($false))
            Write-Host "[UPDATED] $relPath" -ForegroundColor Green
        }
        else {
            [ImportDiffWriter]::Write($relPath, $content, $updated)
        }
        return 1
    }
}

# ─── Entry Point ──────────────────────────────────────────────────────────────

$optimizers = [ImportOptimizer[]]@(
    [PhpImportOptimizer]::new()
    [TsImportOptimizer]::new()
    [CsImportOptimizer]::new()
    [ScssImportOptimizer]::new()
)

$runner = [ImportOptimizationRunner]::new($Path, $Exts, $ExcludePattern, $optimizers, $MaxLineLength)
$changedCount = $runner.Run($PSCmdlet)

Write-Host "`n[$changedCount file(s) affected]" -ForegroundColor Yellow
