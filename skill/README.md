# The installable skill

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
