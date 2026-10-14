# Prolog · fill-in edition

Same game. **Story is only text.** Engine is not your job tonight.

## Run

```text
cd fill-in
python play.py
```

## Write story

Edit **`story.py` only** (for voice).

| In story.py | What |
|-------------|------|
| `INTRO_*`, `SPACES`, `CONCEPTS`, `COMBINES` | Field prose + ladder |
| `LIGHT_*`, `HELP` | Ending + help |
| **`VOICE`** | Fail / warn / ok / bare Echo lines that used to hide in the engine |

Engine should stay verbs + state. If a line sounds industrial, rewrite it in **`VOICE`** — not buried in `engine.py`.

## The rules (cognition ladder)

1. You start **nowhere** — there is nothing (failed self-instance).
2. **`look` / `remember` / `think`** as before (presence ≠ memory; think needs memory).
3. **`consider A with B`** is how the **field moves** (both in memory):

```text
nowhere  — remember nothing, remember where
         — consider where with nothing  →  VOID
void     — remember who, consider who with void  →  itch
         — remember what, consider what with void →  STATIC
static   — consider who with static  →  LIGHT / CRASH
```

4. Light ending is **shock of success** (Echo overwhelm), not a victory lap.
5. `let there be light` only after the full ladder is held (same crash).

Echo is the other voice. It is not you. (In the myth: him, recompiling.)

## Authoring without dying in Python

| Tool | Use |
|------|-----|
| `sheets/concept_form.html` | Open in browser; fill form; copy YAML |
| `sheets/concepts/*.yaml` | One file per concept |
| `sheets/combines.yaml` | consider-pairs |
| `sheets/thoughts-void.txt` | Random bare-think lines |
| `python tools/sheets_to_story.py` | Emit pasteable CONCEPTS snippet |

Live game still loads **`story.py`** (so one source of truth while playing). Sheets are the **comfortable editor**; merge into story.py when ready.
