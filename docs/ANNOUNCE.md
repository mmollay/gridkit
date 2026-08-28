# Announcement drafts

Four channels, four registers. Nothing here is scheduled or sent — these are
drafts to edit and post by hand, in the order the launch checklist gives.

**The gate is clear.** `composer require mmollay/gridkit` installs the current version, the
package is auto-updated from GitHub, the repo has a release, a social preview
and Discussions. Checked 2026-08-28.

Post in this order, one channel a day: the blog post first — everything else
links to it — then Hacker News, then r/PHP, then Mastodon.

---

## r/PHP — a discussion post, not an ad

Reddit's PHP community reacts badly to launch copy and well to a specific
problem with a specific answer. Lead with the problem.

> **Title:** I wrote a component library for admin dashboards, and then spent a
> week finding out my own docs were wrong
>
> GridKit renders admin UI from PHP — tables with search, sort, filter and
> paging over AJAX, forms on a 16-column grid, six themes, dark mode, a mobile
> layout. Zero dependencies, no build step, PHP 8.2+.
>
> The part I want to talk about is the testing. I gave five agents a page to
> build and my own API document as their only source, with the source tree off
> limits, and watched what they wrote. It found things no test of mine had:
> a documented method that never existed, a `required` option sitting on a
> hidden input where the browser cannot validate it, an install command in my
> README that ended in a fatal error on the first line.
>
> Nine of eleven sections of my demo page had been rendering underneath the
> sidebar for months because one `</div>` too many closes the wrapper — and the
> tag count nets to zero, so nothing caught it.
>
> Repo: <https://github.com/mmollay/gridkit> · Demo: <https://gridkit.ssi.at/demo/>
>
> Happy to go into any of it.

---

## Hacker News — Show HN

HN wants the thing itself and one honest sentence about what it is not.

**Pick the title deliberately — on HN it decides more than the text does.**
Three that fit, most concrete first:

1. `Show HN: GridKit – a searchable, sortable, paginated table in 15 lines of PHP`
   Names the payoff and quantifies it. A reader knows in one line whether they
   care. Strongest of the three.
2. `Show HN: GridKit – PHP components for admin dashboards, no build step`
   Plain and safe. "No build step" is the part that still earns attention.
3. `Show HN: GridKit – admin UI in PHP, zero dependencies, one CSS and one JS file`
   Leans hardest on the constraint. Good if the comments you want are about
   dependency weight.

Avoid putting "AI" in the title. It sets the wrong expectation for a component
library and invites an argument about the wrong thing — the skill belongs in the
text, where it reads as a feature rather than a pitch.

> **Show HN: GridKit – a searchable, sortable, paginated table in 15 lines of PHP**
>
> A table with search, sort, filter, paging over AJAX, row actions and a modal
> form is about fifteen lines of PHP. Six themes derived from one hue in OKLCH,
> dark mode, a mobile card layout.
>
> No npm, no compilation, no framework. A checkout is a working install.
>
> It is not a full-stack framework and not a SPA: no routing, no ORM, no
> client-side state. It renders UI from data you already have, and the
> contributor base is one person — read the code before you depend on it.
>
> The API also ships as something an assistant can read: one document, or an
> installable skill, or a URL — `https://gridkit.ssi.at/skill/`, with an
> `llms.txt` at the site root pointing at it. I have been testing that document
> by giving it to agents with the source tree off limits and measuring how far
> they get. The current number is in the README, along with what it found wrong.

---

## phpc.social / Mastodon — short

> GridKit 1.56.0: PHP components for admin dashboards. Tables with AJAX search
> and paging, forms, six themes, dark mode, zero dependencies, no build step.
>
> The last twenty releases came out of pointing AI agents at my own
> documentation and fixing everything they tripped over. It was humbling.
>
> The API now installs as a skill, or reads from a URL — llms.txt included.
>
> https://github.com/mmollay/gridkit
>
> #php #opensource

---

## Dev.to / a blog post — the long one

The piece worth actually writing, because it is the part nobody else has:

> **Title:** I tested my documentation by giving it to five AI agents. It failed.
>
> The shape: a library claims its docs let an agent write correct code. That is
> a testable claim, so test it — five agents, one page each, the API document as
> the only source, the source tree off limits. Then read what they wrote.
>
> Round one: none of the five got a working first draft. Thirty confirmed gaps.
> The failure mode was never a crash — it was exit 0, no warning, and a page
> with a piece silently missing. A theme switcher that renders nothing because
> the method returns a string and the docs called it as a statement.
>
> Fix those, run the same five tasks again: four of five. Run five *different*
> tasks: none of five. The second number is the one that matters — the first
> round measured five patched holes, not a sound document.
>
> What finally moved it was counting: 43 of 128 public methods were named
> nowhere in the file. The components with a real section were right every
> time; every failure landed on one documented as a table row.
>
> Ends with the uncomfortable one: three agents reported that there was no way
> to register application translations. There was. I had not found it either,
> and I had written their workaround into the documentation as the recommended
> pattern.

---

## Curated lists — later, and only once

[awesome-php](https://github.com/ziadoz/awesome-php) is worth having and worth
not rushing. Its bar is "widely known or recognised within the PHP community"
and "established and mature", it discourages submissions made for self
promotion, and — the part that decides the timing — an entry that has been
rejected once "won't be reviewed if previously rejected". One attempt, no
second chance.

GridKit clears everything else the list asks for: Composer-installable, PSR-4,
semantically versioned, thoroughly tested, actively maintained, documented in
English, on a supported PHP. What it does not yet have is the recognition, and
that is precisely what the channels above are for.

**Submit after there is real usage** — a few hundred downloads that are not
yours, an issue or two from a stranger, something written about it that you did
not write. Three to six months, not this week. The entry, ready to paste into
the "Frameworks" or "Components" section in alphabetical order:

```
* [GridKit](https://github.com/mmollay/gridkit) - A component library for admin interfaces, rendering tables, forms and modals from PHP with no build step.
```

The pull request description should link the project and say what it does — not
that you wrote it, and not why it deserves inclusion.

## What not to do

- Do not post to more than one channel a day. A thing appearing everywhere at
  once reads as marketing.
- Do not answer criticism with a feature list. If someone says it is not for
  them, they are right.
- The single download counter is not a secret. If asked how many people use
  it: one, and it is me.
