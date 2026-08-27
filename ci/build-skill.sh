#!/usr/bin/env bash
# Build the installable skill from GRIDKIT_SKILL.md.
#
# One source, two shapes. GRIDKIT_SKILL.md stays the whole thing in one file —
# it is what the site serves at /skill and what a paste-into-context user gets.
# skill/ is the same content split so an assistant loads the rules first and
# fetches a reference only when it needs one: 61 KB is a lot to spend on a
# question about one method.
#
# Generated, never edited by hand. A test fails if the two drift apart.
#
#   bash ci/build-skill.sh
set -eu
cd "$(dirname "$0")/.."

python3 - <<'PY'
import os, re, shutil

src = open('GRIDKIT_SKILL.md', encoding='utf-8').read()
version = open('VERSION').read().strip()

# Split on level-2 headings, keeping each heading with its body.
parts = re.split(r'\n(?=## )', src)
head, sections = parts[0], parts[1:]
by_title = {}
for s in sections:
    by_title[s.split('\n', 1)[0].removeprefix('## ').strip()] = s.rstrip() + '\n'

def take(*titles):
    out = []
    for t in titles:
        for k in list(by_title):
            if k.startswith(t):
                out.append(by_title.pop(k))
                break
    return '\n'.join(out)

# What an assistant has to know BEFORE it writes a line — the rules that are
# not guessable from the API surface.
core = take(
    'The one rule to read first',
    'Page skeleton',
    'Filters forget each other',
    'Common Pitfalls',
)
components = take('Component Reference')
javascript = take('JavaScript API')

# Three browser-side sections live as ### under other headings — Global search,
# Live Tables and AJAX navigation. They belong with the JavaScript, not with the
# components, or reference/javascript.md is twenty lines and useless.
def lift(body, *titles):
    lifted, kept = [], body
    for t in titles:
        m = re.search(r'\n(### ' + re.escape(t) + r'.*?)(?=\n### |\Z)', kept, re.S)
        if m:
            lifted.append(m.group(1).rstrip() + '\n')
            kept = kept[:m.start()] + kept[m.end():]
    return '\n'.join(lifted), kept

# They sit under "Filters forget each other", which is a must-know rule that
# then runs on into reference material. Keep the rule in SKILL.md, move the
# reference out.
browser, core = lift(core, 'Global search', 'Live Tables', 'AJAX Navigation')
pager,   core = lift(core, 'Pagination + PageSize')
css        = take('CSS Classes Reference', 'Utility Classes')
# BelegModal and ActionGroup are filed under "CSS Classes Reference" in the
# source document. They are components; they go with the components.
misfiled, css = lift(css, 'BelegModal', 'ActionGroup')

javascript = javascript.rstrip() + '\n\n' + browser
components = components.rstrip() + '\n\n' + pager + '\n\n' + misfiled
# Anything left over rides along with the components reference.
rest = '\n'.join(by_title.values())

shutil.rmtree('skill', ignore_errors=True)
os.makedirs('skill/reference', exist_ok=True)

def write(path, text):
    open(path, 'w', encoding='utf-8').write(text)
    print('  %-34s %5d lines' % (path, text.count('\n')))

write('skill/SKILL.md', f'''---
name: gridkit
description: >-
  Build admin UI in PHP with GridKit — tables with search, sort, filter and
  paging over AJAX, forms on a 16-column grid, sidebars, headers, modals, six
  themes and dark mode. Use when writing or changing PHP that renders an admin
  page, dashboard, CRUD list or data table with GridKit, or when a project has
  GridKit in composer.json.
---

# GridKit {version}

PHP components for admin dashboards. Zero dependencies, no build step,
PHP 8.2+. A checkout is a working install.

**Read this file first.** It holds the rules that are not guessable from the
API — the ones every agent given only the reference has tripped over. Then open
a reference below for the component you need, rather than all of them.

| Reference | What is in it |
|---|---|
| [reference/components.md](reference/components.md) | Every component, every option, every public method |
| [reference/javascript.md](reference/javascript.md) | `GK.*` — modals, toasts, live tables, global search |
| [reference/css.md](reference/css.md) | Class names and the utility classes |

The same content in one file, for pasting into a context that has no file
access: <https://gridkit.ssi.at/skill>

---

{core}''')

write('skill/reference/components.md',
      f'# GridKit {version} — components\n\n'
      f'Generated from GRIDKIT_SKILL.md. Rules first: see ../SKILL.md.\n\n'
      f'{components}\n{rest}')
write('skill/reference/javascript.md',
      f'# GridKit {version} — JavaScript\n\n'
      f'Generated from GRIDKIT_SKILL.md. Rules first: see ../SKILL.md.\n\n{javascript}')
write('skill/reference/css.md',
      f'# GridKit {version} — CSS\n\n'
      f'Generated from GRIDKIT_SKILL.md. Rules first: see ../SKILL.md.\n\n{css}')

write('skill/README.md', f'''# The installable skill

`GRIDKIT_SKILL.md` in the repository root is the whole API in one file — paste
it into any assistant's context and it works.

This directory is the same content, split so an assistant reads the rules first
and fetches one reference on demand instead of loading 61 KB to answer a
question about one method.

## Install it for Claude Code

```bash
mkdir -p ~/.claude/skills
cp -r skill ~/.claude/skills/gridkit
```

Or per project, in `.claude/skills/gridkit/`. Claude picks it up by the
`description` in `SKILL.md` — you do not have to mention it.

## Any other assistant

Point it at <https://gridkit.ssi.at/skill>, or drop `GRIDKIT_SKILL.md` into the
project. Same content, one file.

## Do not edit these files

They are generated: `bash ci/build-skill.sh`. Change `GRIDKIT_SKILL.md` and
rebuild — a test fails if the two have drifted apart.
''')
PY
