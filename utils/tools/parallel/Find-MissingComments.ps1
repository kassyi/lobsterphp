param (
    [string]$SearchPath = ".",
    [string[]]$Extensions = @("*.php", "*.ts", "*.cs"),
    [int]$ThrottleLimit = 8
)

# 正規表現: public, protected, abstract, class, interface などの宣言を検出
# TypeScriptの `export` などの修飾子も一応考慮
$GetItemRegex = '^\s*(?:export\s+)?(?:public|protected|abstract|class|interface)\s+'

# 並列処理でファイルを検査
$results = Get-ChildItem -Path $SearchPath -Include $Extensions -Recurse -File | ForEach-Object -ThrottleLimit $ThrottleLimit -Parallel {
    $file = $_
    $regex = $using:GetItemRegex
    
    $lines = [System.IO.File]::ReadAllLines($file.FullName)
    $findings = @()

    $inConstructorParams = $false

    for ($i = 0; $i -lt $lines.Count; $i++) {
        $line = $lines[$i]
        
        if ($inConstructorParams -and ($line -match '\)' -or $line -match '^\s*\{')) {
            $inConstructorParams = $false
        }

        # PHPコンストラクタのプロモーションプロパティ（public string $code など）の検出
        # C# のプライマリコンストラクタ/レコードプロパティの検出
        if ($line -match '__construct\s*\(' -or $line -match '\bconstructor\s*\(' -or $line -match '\b(?:class|record|struct)\s+\w+(?:<[^>]+>)?\s*\(') {
            if (-not ($line -match '\)')) {
                $inConstructorParams = $true
            }
        }

        if ($inConstructorParams) {
            continue
        }
        
        if ($line -match $regex) {
            $hasComment = $false
            $prevLines = $i - 1
            
            # 宣言の前の空行をスキップ
            while ($prevLines -ge 0 -and [string]::IsNullOrWhiteSpace($lines[$prevLines])) {
                $prevLines--
            }
            
            if ($prevLines -ge 0) {
                $prevLine = $lines[$prevLines].Trim()
                
                # 直前の行がコメントであるか、または属性・アトリビュート等であるか判定
                # - */ で終わる (PHPDoc / JSDoc)
                # - // または /// で始まる (単一行コメント, XMLドキュメント)
                # - # で始まる (PHP 8のアトリビュート `#[...]`するなど)
                # - ] や > で終わる (C#などのAttribute `<...>`, `[...]`)
                if ($prevLine.EndsWith("*/") -or 
                    $prevLine.StartsWith("//") -or 
                    $prevLine.StartsWith("///") -or 
                    $prevLine.StartsWith("#") -or 
                    $prevLine.EndsWith("]") -or
                    $prevLine.EndsWith(">")) {
                    $hasComment = $true
                }
            }
            
            if (-not $hasComment) {
                $findings += [PSCustomObject]@{
                    File = $file.FullName
                    Line = $i + 1
                    Code = $line.Trim()
                }
            }
        }
    }
    
    # 見つかった結果をパイプラインに流す
    $findings
}

$output = @{
    TotalFindings = @($results).Count
    Findings      = @($results)
}

$output | ConvertTo-Json -Depth 3
