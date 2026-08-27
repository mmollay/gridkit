# Announcement drafts

Four channels, four registers. Nothing here is scheduled or sent — these are
drafts to edit and post by hand, in the order the launch checklist gives.

**Do not post any of this until `composer require mmollay/gridkit` installs the
current version.** Until Packagist is updated it installs v1.4.0, from March.

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

> **Show HN: GridKit — PHP components for admin dashboards, no build step**
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
> The API also ships as a single document for AI agents, which I have been
> testing by giving it to agents and measuring how far they get. The current
> number is in the README, along with what it found wrong.

---

## phpc.social / Mastodon — short

> GridKit 1.51.0: PHP components for admin dashboards. Tables with AJAX search
> and paging, forms, six themes, dark mode, zero dependencies, no build step.
>
> The last twenty releases came out of pointing AI agents at my own
> documentation and fixing everything they tripped over. It was humbling.
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

## What not to do

- Do not post to more than one channel a day. A thing appearing everywhere at
  once reads as marketing.
- Do not answer criticism with a feature list. If someone says it is not for
  them, they are right.
- The single download counter is not a secret. If asked how many people use
  it: one, and it is me.
