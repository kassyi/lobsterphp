<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lobster.php Showcase</title>
    <style>
        /* Import a base theme for testing */
        @import url('../docs/style.css');

        body, html {
            margin: 0;
            padding: 0;
            height: 100%;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            background-color: var(--md-sys-color-background, #f7f9fa);
            color: var(--md-sys-color-on-background, #000);
        }
        .header {
            background-color: var(--md-sys-color-primary, #2c3e50);
            color: var(--md-sys-color-on-primary, white);
            padding: 1rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .header h1 {
            margin: 0;
            font-size: 1.25rem;
        }
        .container {
            display: flex;
            height: calc(100vh - 60px); /* Adjust for header */
        }
        .pane {
            flex: 1;
            display: flex;
            flex-direction: column;
            border-right: 1px solid var(--md-sys-color-outline-variant, #ccc);
        }
        .pane:last-child {
            border-right: none;
        }
        .pane-header {
            background-color: var(--md-sys-color-surface-variant, #ecf0f1);
            color: var(--md-sys-color-on-surface-variant, #000);
            padding: 0.5rem 1rem;
            font-weight: bold;
            border-bottom: 1px solid var(--md-sys-color-outline-variant, #ccc);
        }
        textarea.editor {
            flex: 1;
            width: 100%;
            box-sizing: border-box;
            border: none;
            padding: 1rem;
            font-family: Consolas, "Courier New", monospace;
            font-size: 14px;
            resize: none;
            outline: none;
            background-color: var(--md-sys-color-surface, #fff);
            color: var(--md-sys-color-on-surface, #000);
        }
        .preview {
            flex: 1;
            padding: 1rem;
            overflow-y: auto;
            background-color: var(--md-sys-color-surface, #fff);
            color: var(--md-sys-color-on-surface, #000);
        }
        .btn {
            padding: 0.5rem 1rem;
            cursor: pointer;
            background: var(--md-sys-color-secondary, #3498db);
            color: var(--md-sys-color-on-secondary, #fff);
            border: none;
            border-radius: var(--radius-xs, 4px);
            font-family: inherit;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>Lobster.php Showcase</h1>
        <div style="display: flex; gap: 0.5rem;">
            <button id="theme-toggle" class="btn">Toggle Dark Mode</button>
            <button id="render-btn" class="btn">Render manually</button>
        </div>
    </div>
    
    <div class="container">
        <div class="pane">
            <div class="pane-header">Markdown Input</div>
            <textarea id="markdown-input" class="editor" placeholder="Type lobster.php Markdown here...">
:::header
# Lobster.php Comprehensive Test Document
This header is placed at the top of the document.
:::

## 1. Headings & Anchors {#headings}

# H1
## H2
### H3
#### H4
##### H5
###### H6

## 2. Paragraphs & Line Breaks

This is a paragraph.
It has a line break right here.
But wait, there's more.

This is another paragraph separated by a blank line.

## 3. Horizontal Rule
---

## 4. Code Blocks

```js:example.js
console.log("Hello, Lobster!");
```

## 5. Blockquotes

> Outer blockquote
> > Inner blockquote
> > Still inner

## 6. Lists

### Bullet Lists
- Apple
- Banana
  - Yellow
  - Sweet
- Cherry

### Ordered Lists
1. First
2. Second
   1. Nested one
   2. Nested two

### Checklists
- [ ] Task 1 (Unchecked)
- [x] Task 2 (Checked)

## 7. Tables

### Auto Alignment
| Name  | Age |
| ----- | --- |
| Alice | 30  |
| Bob   | 25  |

### Specific Alignment
| Left | Center | Right |
| :--- | :---: | ---: |
| L    | C     | R    |

### Cell Merging (Lobster extension)
| Merge Horizontal | \| |
| Merge Vertical   | B |
| \---             | C |

## 8. Inline Elements

- **Code Span**: `inline code`
- **Emphasis**: *italic* or _italic_
- **Strong**: **bold** or __bold__
- **Strikethrough**: ~~strike~~
- **Inline Link**: [Example Site](https://example.com "Title")
- **Reference Link**: [Ref Link][ref_id]
- **Image**: ![Logo](https://placehold.co/100x100 "Dummy Image" =50x50)

[ref_id]: https://example.com "Ref Title"

## 9. Footnotes

This is a text with a footnote reference[^1].
This is an inline footnote^[Inline footnote content].

[^1]: This is the definition of the footnote.

## 10. Lobster Extensions

### Details Block
:::details Click to expand
Hidden details content!
:::

### Warp & Silent Table
~ | [~col-left] | [~col-right] |
~ | :---        | :---        |

:::warp col-left
**Left Column**
Content for the left side.
:::

:::warp col-right
**Right Column**
Content for the right side.
:::

:::footer
© 2026 Lobster.php Test Footer
:::
</textarea>
        </div>
        <div class="pane">
            <div class="pane-header">HTML Output Preview</div>
            <div id="html-preview" class="preview"></div>
        </div>
    </div>

    <script>
        // Theme toggling
        const themeToggle = document.getElementById('theme-toggle');
        const currentTheme = localStorage.getItem('theme') || 'light';
        document.documentElement.setAttribute('data-theme', currentTheme);

        themeToggle.addEventListener('click', () => {
            const newTheme = document.documentElement.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
            document.documentElement.setAttribute('data-theme', newTheme);
            localStorage.setItem('theme', newTheme);
        });

        const input = document.getElementById('markdown-input');
        const preview = document.getElementById('html-preview');
        const renderBtn = document.getElementById('render-btn');

        const previewHeader = document.querySelector('.pane:last-child .pane-header');

        async function renderMarkdown() {
            previewHeader.textContent = 'HTML Output Preview (Rendering...)';
            const formData = new FormData();
            formData.append('markdown', input.value);

            try {
                const response = await fetch('render.php', {
                    method: 'POST',
                    body: formData
                });
                if (response.ok) {
                    const html = await response.text();
                    preview.innerHTML = html;
                } else {
                    preview.innerHTML = `<p style="color:red">Error parsing Markdown. Server returned ${response.status}</p>`;
                }
            } catch (error) {
                preview.innerHTML = `<p style="color:red">Network error: ${error.message}</p>`;
            } finally {
                previewHeader.textContent = 'HTML Output Preview';
            }
        }

        // Render on initial load
        renderMarkdown();

        // Render on button click
        renderBtn.addEventListener('click', () => {
            clearTimeout(debounceTimer);
            renderMarkdown();
        });

        // Debounce for auto-render: wait until typing is finished
        let debounceTimer;
        input.addEventListener('input', () => {
            previewHeader.textContent = 'HTML Output Preview (Waiting for typing to finish...)';
            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(renderMarkdown, 500);
        });
    </script>
</body>
</html>
