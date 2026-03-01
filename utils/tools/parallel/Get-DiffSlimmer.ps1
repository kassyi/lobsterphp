<#
.SYNOPSIS
    Gitの差分からコンテキスト（@@行）のみを抽出し、AIが修正結果を最小コストで検証できるようにする。
#>
param()

$RootPath = Resolve-Path (Join-Path $PSScriptRoot "../../")
# 未コミットの差分を取得（行番号情報を含む）
$Diff = git -C $RootPath.Path diff -U0

$Output = @{
    tool        = "DiffSlimmer"
    rawDiff     = $Diff
    instruction = "Verify that the changes align with the refactoring goal. Focus only on modified chunks."
}

$Output | ConvertTo-Json -Depth 10