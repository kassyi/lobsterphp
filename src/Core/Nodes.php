<?php

declare(strict_types=1);

namespace Kassyi\LobsterPhp\Core;

use Kassyi\LobsterPhp\Core\Visitor\NodeVisitorInterface;

// ============================================================
// Base Interfaces
// ============================================================

/**
 * 構文解析ツリー（AST）の基底ノードインターフェース
 * 
 * すべてのASTノードは、Visitorパターンに基づく巡回（レンダリング等）を
 * 処理するための `accept` メソッドを実装する必要があります。
 */
interface AstNode {
    /**
     * Visitorによる処理を受け入れます
     *
     * @param NodeVisitorInterface $visitor 対象のVisitorインスタンス
     * @return mixed Visitorによる処理の戻り値
     */
    public function accept(NodeVisitorInterface $visitor): mixed;
}

/**
 * インライン要素（テキスト、強調、リンク、画像など）を表す基底インターフェース
 * 
 * これらは段落や見出しなどの「ブロック要素」の中に含まれる要素です。
 */
interface InlineNode extends AstNode {}

/**
 * ブロック要素（段落、見出し、リスト、テーブルなど）を表す基底インターフェース
 * 
 * これらは文書の骨格を形成する大きな単位の要素です。
 */
interface BlockNode extends AstNode {}


// ============================================================
// Inline AST Nodes
// ============================================================

/**
 * 単純なテキスト文字列を表すインラインノード
 */
readonly class TextNode implements InlineNode {
    /**
     * @param string $text 実際のテキスト内容
     */
    public function __construct(
        public string $text
    ) {}

    /**
     * {@inheritDoc}
     */
    public function accept(NodeVisitorInterface $visitor): mixed { return $visitor->visitTextNode($this); }
}

/**
 * 斜体（Emphasis: `*` または `_`）で囲まれたインラインノード
 */
readonly class EmphasisNode implements InlineNode {
    /**
     * @param InlineNode[] $children 斜体として装飾される子ノード群
     */
    public function __construct(
        public array $children
    ) {}

    /**
     * {@inheritDoc}
     */
    public function accept(NodeVisitorInterface $visitor): mixed { return $visitor->visitEmphasisNode($this); }
}

/**
 * 太字（Strong: `**` または `__`）で囲まれたインラインノード
 */
readonly class StrongNode implements InlineNode {
    /**
     * @param InlineNode[] $children 太字として装飾される子ノード群
     */
    public function __construct(
        public array $children
    ) {}

    /**
     * {@inheritDoc}
     */
    public function accept(NodeVisitorInterface $visitor): mixed { return $visitor->visitStrongNode($this); }
}

/**
 * 取り消し線（Strikethrough: `~~`）で囲まれたインラインノード
 */
readonly class StrikethroughNode implements InlineNode {
    /**
     * @param InlineNode[] $children 取り消し線として装飾される子ノード群
     */
    public function __construct(
        public array $children
    ) {}

    /**
     * {@inheritDoc}
     */
    public function accept(NodeVisitorInterface $visitor): mixed { return $visitor->visitStrikethroughNode($this); }
}

/**
 * インラインコード（Code Span: バッククォート `` ` `` で囲まれた短いコード）を表すノード
 */
readonly class CodeSpanNode implements InlineNode {
    /**
     * @param string $code コードの内容
     */
    public function __construct(
        public string $code
    ) {}

    /**
     * {@inheritDoc}
     */
    public function accept(NodeVisitorInterface $visitor): mixed { return $visitor->visitCodeSpanNode($this); }
}

/**
 * インラインリンク（`[表示テキスト](URL "タイトル")`）を表すノード
 */
readonly class InlineLinkNode implements InlineNode {
    /**
     * @param InlineNode[] $text リンクとして表示されるテキスト（ノードの配列）
     * @param string $href リンク先のURL
     * @param string|null $title 任意で指定されるリンクのタイトル属性
     */
    public function __construct(
        public array $text,
        public string $href,
        public ?string $title = null
    ) {}

    /**
     * {@inheritDoc}
     */
    public function accept(NodeVisitorInterface $visitor): mixed { return $visitor->visitInlineLinkNode($this); }
}

/**
 * 参照リンク（`[表示テキスト][参照ID]`）を表すノード
 */
readonly class LinkNode implements InlineNode {
    /**
     * @param InlineNode[] $text リンクとして表示されるテキスト（ノードの配列）
     * @param string $href 文書内で定義された参照先URL
     * @param string|null $title 文書内で定義された参照先タイトル
     */
    public function __construct(
        public array $text,
        public string $href,
        public ?string $title = null
    ) {}

    /**
     * {@inheritDoc}
     */
    public function accept(NodeVisitorInterface $visitor): mixed { return $visitor->visitLinkNode($this); }
}

/**
 * 画像（`![代替テキスト](URL "タイトル" =幅x高さ)`）を表すノード
 * ※ `=WxH` はLobster独自の拡張書式です
 */
readonly class ImageNode implements InlineNode {
    /**
     * @param string $alt 画像が表示できない場合などの代替テキスト
     * @param string $src 画像のURL
     * @param string|null $title 任意で指定される画像のタイトル属性
     * @param int|null $width Lobster独自の幅指定
     * @param int|null $height Lobster独自の高さ指定
     */
    public function __construct(
        public string $alt,
        public string $src,
        public ?string $title = null,
        public ?int $width = null,
        public ?int $height = null
    ) {}

    /**
     * {@inheritDoc}
     */
    public function accept(NodeVisitorInterface $visitor): mixed { return $visitor->visitImageNode($this); }
}

/**
 * 脚注への参照（`[^識別ID]`）を表すノード
 */
readonly class FootnoteRefNode implements InlineNode {
    /**
     * @param string $id 参照する脚注の識別ID
     */
    public function __construct(
        public string $id
    ) {}

    /**
     * {@inheritDoc}
     */
    public function accept(NodeVisitorInterface $visitor): mixed { return $visitor->visitFootnoteRefNode($this); }
}

/**
 * 文章中に直接定義されたインライン脚注（`^[脚注テキスト]`）を表すノード
 */
readonly class InlineFootnoteNode implements InlineNode {
    /**
     * @param InlineNode[] $children 脚注として展開される内容
     */
    public function __construct(
        public array $children
    ) {}

    /**
     * {@inheritDoc}
     */
    public function accept(NodeVisitorInterface $visitor): mixed { return $visitor->visitInlineFootnoteNode($this); }
}

/**
 * ワープ参照（`[~定義ID]`）を表すノード
 * 別の場所で定義（`:::warp`）されたブロック要素を展開して挿入する機能（Lobster独自拡張）
 */
readonly class WarpRefNode implements InlineNode {
    /**
     * @param string $id 展開対象となるワープ定義のID
     */
    public function __construct(
        public string $id
    ) {}

    /**
     * {@inheritDoc}
     */
    public function accept(NodeVisitorInterface $visitor): mixed { return $visitor->visitWarpRefNode($this); }
}

/**
 * 改行（行末の2スペース以上、または明確な改行タグなど）を表すノード
 */
readonly class LineBreakNode implements InlineNode {
    /**
     * {@inheritDoc}
     */
    public function accept(NodeVisitorInterface $visitor): mixed { return $visitor->visitLineBreakNode($this); }
}


// ============================================================
// Block AST Nodes
// ============================================================

/**
 * 見出し（Heading: `#` 〜 `######`）を表すブロックノード
 */
readonly class HeadingNode implements BlockNode {
    /**
     * @param int $level 見出しのレベル（1〜6）
     * @param InlineNode[] $children 見出しのテキスト内容（インライン要素）
     * @param string|null $id 見出しにリンクするためのアンカーID（例: `{#custom-id}`）
     */
    public function __construct(
        public int $level,
        public array $children,
        public ?string $id = null
    ) {}

    /**
     * {@inheritDoc}
     */
    public function accept(NodeVisitorInterface $visitor): mixed { return $visitor->visitHeadingNode($this); }
}

/**
 * 一般的な段落（Paragraph）を表すブロックノード
 */
readonly class ParagraphNode implements BlockNode {
    /**
     * @param InlineNode[] $children 段落を構成するインライン要素
     */
    public function __construct(
        public array $children
    ) {}

    /**
     * {@inheritDoc}
     */
    public function accept(NodeVisitorInterface $visitor): mixed { return $visitor->visitParagraphNode($this); }
}

/**
 * 水平線（Horizontal Rule: `---`, `***` など）を表すブロックノード
 */
readonly class HorizontalRuleNode implements BlockNode {
    /**
     * {@inheritDoc}
     */
    public function accept(NodeVisitorInterface $visitor): mixed { return $visitor->visitHorizontalRuleNode($this); }
}

/**
 * コードブロック（Fenced Code Block: バッククォート3つ等で囲まれた複数行コード）を表すブロックノード
 */
readonly class CodeBlockNode implements BlockNode {
    /**
     * @param string $code 囲まれたコード全体の内容
     * @param string|null $language シンタックスハイライト用の言語名（例: `php`）
     * @param string|null $filename ファイル名やタイトルなど（Lobster拡張機能による `:` 指定）
     */
    public function __construct(
        public string $code,
        public ?string $language = null,
        public ?string $filename = null
    ) {}

    /**
     * {@inheritDoc}
     */
    public function accept(NodeVisitorInterface $visitor): mixed { return $visitor->visitCodeBlockNode($this); }
}

/**
 * 引用ブロック（Blockquote: `>` で開始する行の集合）を表すブロックノード
 */
readonly class BlockquoteNode implements BlockNode {
    /**
     * @param BlockNode[] $children 引用内の要素群（ネスト可能）
     */
    public function __construct(
        public array $children
    ) {}

    /**
     * {@inheritDoc}
     */
    public function accept(NodeVisitorInterface $visitor): mixed { return $visitor->visitBlockquoteNode($this); }
}

/**
 * リストアイテム（箇条書きの一項目）を表すノード
 */
readonly class ListItemNode {
    /**
     * @param InlineNode[] $children アイテム本文のインライン要素
     * @param bool|null $checked チェックリストの場合の状態（true: チェックあり、false: チェックなし、null: 通常のリスト）
     * @param BulletListNode|OrderedListNode|null $sublist ネストされた子リスト群
     */
    public function __construct(
        public array $children,
        public ?bool $checked = null,
        public BulletListNode|OrderedListNode|null $sublist = null
    ) {}

    /**
     * {@inheritDoc}
     */
    public function accept(NodeVisitorInterface $visitor): mixed { return $visitor->visitListItemNode($this); }
}

/**
 * 記号による箇条書きリスト（Bullet List: `-`, `*`, `+`）を表すブロックノード
 */
readonly class BulletListNode implements BlockNode {
    /**
     * @param int $depth リストのネスト段階（0開始）
     * @param ListItemNode[] $items リストに含まれる項目群
     */
    public function __construct(
        public int $depth,
        public array $items
    ) {}

    /**
     * {@inheritDoc}
     */
    public function accept(NodeVisitorInterface $visitor): mixed { return $visitor->visitBulletListNode($this); }
}

/**
 * 番号付きリスト（Ordered List: `1.`, `2.`）を表すブロックノード
 */
readonly class OrderedListNode implements BlockNode {
    /**
     * @param int $depth リストのネスト段階（0開始）
     * @param int $start リストの開始番号（例: 最初の項目が `3.` ならば 3）
     * @param ListItemNode[] $items リストに含まれる項目群
     */
    public function __construct(
        public int $depth,
        public int $start,
        public array $items
    ) {}

    /**
     * {@inheritDoc}
     */
    public function accept(NodeVisitorInterface $visitor): mixed { return $visitor->visitOrderedListNode($this); }
}

/**
 * テーブルのカラムのアラインメント（揃え位置）を定義する列挙型
 */
enum TableAlignment: string {
    case Default = 'default';
    case Left = 'left';
    case Center = 'center';
    case Right = 'right';
}

/**
 * テーブルの1つのセル（表の中の1マス）を表すノード
 */
readonly class TableCellNode {
    /**
     * @param InlineNode[] $children セルの内容
     * @param int|null $colspan 水平結合（Colspan）のセル数（Lobster独自拡張）
     * @param int|null $rowspan 垂直結合（Rowspan）のセル数（Lobster独自拡張）
     */
    public function __construct(
        public array $children,
        public ?int $colspan = null,
        public ?int $rowspan = null
    ) {}
}

/**
 * テーブル（表）全体を表すブロックノード
 */
readonly class TableNode implements BlockNode {
    /**
     * @param bool $isSilent 境界線を引かない「サイレントテーブル（`~|` で開始）」かどうか
     * @param TableCellNode[] $headers テーブルのヘッダー行にあるセルの配列
     * @param TableAlignment[] $alignments 各カラムごとのアライメント（左・中央・右）
     * @param TableCellNode[][] $rows テーブルのボディ行（行 > セルの二次元配列）
     */
    public function __construct(
        public bool $isSilent,
        public array $headers,
        public array $alignments,
        public array $rows
    ) {}

    /**
     * {@inheritDoc}
     */
    public function accept(NodeVisitorInterface $visitor): mixed { return $visitor->visitTableNode($this); }
}

/**
 * ページ全体のヘッダーを定義するコンテナ（`:::header` ブロック）を表すブロックノード
 */
readonly class HeaderContainerNode implements BlockNode {
    /**
     * @param BlockNode[] $children ヘッダー内部に配置されるブロック要素群
     */
    public function __construct(
        public array $children
    ) {}

    /**
     * {@inheritDoc}
     */
    public function accept(NodeVisitorInterface $visitor): mixed { return $visitor->visitHeaderContainerNode($this); }
}

/**
 * ページ全体のフッターを定義するコンテナ（`:::footer` ブロック）を表すブロックノード
 */
readonly class FooterContainerNode implements BlockNode {
    /**
     * @param BlockNode[] $children フッター内部に配置されるブロック要素群
     */
    public function __construct(
        public array $children
    ) {}

    /**
     * {@inheritDoc}
     */
    public function accept(NodeVisitorInterface $visitor): mixed { return $visitor->visitFooterContainerNode($this); }
}

/**
 * アコーディオン（折りたたみ可能な要素、`:::details タイトル`）を表すブロックノード
 */
readonly class DetailsNode implements BlockNode {
    /**
     * @param string $title アコーディオンのラベル（要約タイトル）
     * @param BlockNode[] $children 展開時に表示されるブロック要素群
     */
    public function __construct(
        public string $title,
        public array $children
    ) {}

    /**
     * {@inheritDoc}
     */
    public function accept(NodeVisitorInterface $visitor): mixed { return $visitor->visitDetailsNode($this); }
}

/**
 * ワープ定義（変数のような再利用ブロック、`:::warp 識別ID`）を表すブロックノード
 * ※ 定義ブロック自体はレンダリング結果に出力されず、WarpRefNode から参照されて展開されます
 */
readonly class WarpDefinitionNode implements BlockNode {
    /**
     * @param string $id 他の場所から呼び出すための識別ID
     * @param BlockNode[] $children 展開対象のブロック要素群
     */
    public function __construct(
        public string $id,
        public array $children
    ) {}

    /**
     * {@inheritDoc}
     */
    public function accept(NodeVisitorInterface $visitor): mixed { return $visitor->visitWarpDefinitionNode($this); }
}


// ============================================================
// Document & Parse Context
// ============================================================

/**
 * ドキュメント内にある参照リンクの定義（`[ID]: URL "Title"`）の情報を保持するクラス
 */
readonly class LinkDef {
    /**
     * @param string $href 関連付ける参照先URL
     * @param string|null $title 任意で指定されるリンクのタイトル属性
     */
    public function __construct(
        public string $href,
        public ?string $title = null
    ) {}
}

/**
 * Markdown解析の結果得られたASTの頂点となるルートのドキュメントクラス
 * ボディ全体や集約された脚注、ヘッダー/フッターなどを包括します。
 */
class Document {
    /**
     * @param BlockNode[] $body 記事本文となるブロック要素群
     * @param array<string, LinkDef> $linkDefs ドキュメント全体のリンクの定義（ID => 情報）
     * @param array<string, InlineNode[]> $footnoteDefs 抽出・定義されたすべての脚注の中身
     * @param string[] $footnoteRefs ドキュメント内での脚注参照が出現した順序を管理するIDの配列
     * @param array<string, WarpDefinitionNode> $warpDefs 宣言されたすべてのワープ定義
     * @param HeaderContainerNode|null $header ページヘッダー（`:::header`で定義された場合）
     * @param FooterContainerNode|null $footer ページフッター（`:::footer`で定義された場合）
     */
    public function __construct(
        public array $body,
        public array $linkDefs,
        public array $footnoteDefs,
        public array $footnoteRefs,
        public array $warpDefs,
        public ?HeaderContainerNode $header = null,
        public ?FooterContainerNode $footer = null
    ) {}
}

/**
 * Markdownをパースしている途中で参照を追跡するための作業コンテキストクラス
 */
class ParseContext {
    /**
     * @param array<string, LinkDef> $linkDefs 事前解析されたリンク定義
     * @param array<string, InlineNode[]> $footnoteDefs パース中に登録・収集された脚注テキスト
     * @param array<string, WarpDefinitionNode> $warpDefs 事前に抽出されたワープノードの定義
     * @param string[] $footnoteRefs 文章内で使用された脚注のIDリスト。レンダリング時の番号振りに利用。
     * @param int $inlineFootnoteCount インライン脚注に自動生成IDを付与するためのカウンタ
     */
    public function __construct(
        public array $linkDefs,
        public array $footnoteDefs,
        public array $warpDefs,
        public array $footnoteRefs,
        public int $inlineFootnoteCount
    ) {}
}
