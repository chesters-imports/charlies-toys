"""
Prolog engine — cognition ladder only.

Story prose: story.py  (spaces, concepts, combines, LIGHT_*, HELP, VOICE)
Engine: verbs + state. Look up VOICE keys — do not invent new player-facing lines here.
"""
from __future__ import annotations

import random
import re
import string
from dataclasses import dataclass, field
from typing import Any

import story as S


# --- ansi -------------------------------------------------------------------
class C:
    x = "\033[0m"
    dim = "\033[2m"
    red = "\033[91m"
    green = "\033[92m"
    yellow = "\033[93m"
    blue = "\033[94m"
    bold = "\033[1m"
    under = "\033[4m"
    echo = "\033[91m"
    ok = "\033[92m"
    bad = "\033[91m"
    warn = "\033[93m"


def voice(key: str, **kw: Any) -> str:
    """Pull a line from story.VOICE and format placeholders."""
    table = getattr(S, "VOICE", {}) or {}
    template = table.get(key, key)
    if isinstance(template, list):
        template = random.choice(template) if template else key
    try:
        return str(template).format(**kw)
    except (KeyError, ValueError):
        return str(template)


def echo(text: str) -> None:
    print(f"{C.echo} E C H O {C.x}  {text}")


def ok(text: str) -> None:
    print(f"\n  {C.ok}SUCCESS{C.x}  {text}")


def fail(text: str) -> None:
    print(f"\n  {C.bad}FAIL{C.x}  {text}")


def warn(text: str) -> None:
    print(f"  {C.warn}…{C.x}  {text}")


def say(block: str) -> None:
    if not block:
        return
    print(block.rstrip("\n"))


# --- state ------------------------------------------------------------------
@dataclass
class Mind:
    space_id: str
    memory: list[str] = field(default_factory=list)
    present: dict[str, list[str]] = field(default_factory=dict)
    light_done: bool = False

    def __post_init__(self) -> None:
        for sid, sp in S.SPACES.items():
            self.present[sid] = list(sp.get("present") or [])


def clean_command(raw: str) -> list[str]:
    raw = raw.lower().strip()
    raw = raw.translate(str.maketrans("", "", string.punctuation.replace("-", "")))
    fillers = {"at", "the", "a", "an", "to", "about", "of", "i", "am", "my", "me"}
    words = [w for w in raw.split() if w and w not in fillers]
    return words


def space(mind: Mind) -> dict[str, Any]:
    return S.SPACES[mind.space_id]


def concept(cid: str) -> dict[str, Any] | None:
    return S.CONCEPTS.get(cid)


def in_memory(mind: Mind, cid: str) -> bool:
    return cid in mind.memory


def is_present(mind: Mind, cid: str) -> bool:
    return cid in mind.present.get(mind.space_id, [])


def acquire(mind: Mind, cid: str) -> None:
    if cid not in mind.memory:
        mind.memory.append(cid)


def pull_from_present(mind: Mind, cid: str) -> None:
    plist = mind.present.get(mind.space_id, [])
    if cid in plist:
        plist.remove(cid)


# --- commands ---------------------------------------------------------------
def cmd_look(mind: Mind, words: list[str]) -> None:
    if len(words) == 1:
        say(space(mind).get("look", ""))
        el = space(mind).get("echo_look")
        if el:
            echo(el)
        pres = mind.present.get(mind.space_id) or []
        if pres:
            warn(voice("present_hint"))
        return

    noun = words[1]
    if noun == "nothing" and mind.space_id in ("void", "nowhere") and "nothing" in mind.memory:
        c = concept("nothing")
        if c:
            say(c.get("look", ""))
            if c.get("echo_look"):
                echo(c["echo_look"])
            return

    if is_present(mind, noun) or in_memory(mind, noun):
        c = concept(noun)
        if not c:
            fail(voice("look_no_shape", noun=noun))
            return
        say(c.get("look") or voice("look_no_text", noun=noun))
        if c.get("echo_look"):
            echo(c["echo_look"])
        if is_present(mind, noun) and not in_memory(mind, noun):
            warn(voice("look_notice_hold", noun=noun))
        return

    fail(voice("look_missing", noun=noun))
    echo(voice("look_missing_echo"))


def cmd_remember(mind: Mind, words: list[str]) -> None:
    if len(words) == 1:
        sp = space(mind)
        if mind.space_id in ("nowhere", "void"):
            token = "nothing"
        else:
            token = sp.get("blank_remember") or sp.get("name", mind.space_id).lower()
        if token in mind.memory:
            ok(voice("remember_already", token=token))
            echo(voice("remember_already_echo", token=token))
            return
        say(sp.get("remember") or voice("remember_fallback", token=token))
        if sp.get("echo_remember"):
            echo(sp["echo_remember"])
        acquire(mind, token)
        ok(voice("acquired", token=token))
        return

    noun = words[1]
    if in_memory(mind, noun):
        ok(voice("remember_already_noun", noun=noun))
        c = concept(noun)
        if c and c.get("echo_remember"):
            echo(c["echo_remember"])
        return

    if not is_present(mind, noun):
        fail(voice("remember_not_present", noun=noun))
        echo(voice("remember_not_present_echo"))
        return

    c = concept(noun)
    if not c:
        fail(voice("remember_no_card", noun=noun))
        return

    say(c.get("remember") or voice("remember_fallback", token=noun))
    if c.get("echo_remember"):
        echo(c["echo_remember"])
    acquire(mind, noun)
    pull_from_present(mind, noun)
    ok(voice("acquired", token=noun))


def cmd_think(mind: Mind, words: list[str]) -> None:
    if len(words) == 1:
        thoughts = space(mind).get("thoughts") or ["..."]
        t = random.choice(thoughts)
        print(voice("think_bare_header", thought=t))
        echo(voice("think_bare_echo"))
        return

    noun = words[1]
    if not in_memory(mind, noun):
        fail(voice("think_no_memory", noun=noun))
        if is_present(mind, noun):
            echo(voice("think_present_not_held", noun=noun))
        else:
            echo(voice("think_build_order"))
        return

    c = concept(noun)
    if not c:
        fail(voice("think_no_text", noun=noun))
        return
    say(c.get("think") or voice("think_fallback", noun=noun))
    if c.get("echo_think"):
        echo(c["echo_think"])
    warn(voice("think_unstable", noun=noun))


def parse_consider_pair(words: list[str]) -> tuple[str, str] | None:
    if len(words) < 2:
        return None
    rest = words[1:]
    if "with" in rest:
        i = rest.index("with")
        left, right = rest[:i], rest[i + 1 :]
    elif "and" in rest:
        i = rest.index("and")
        left, right = rest[:i], rest[i + 1 :]
    elif len(rest) >= 2:
        left, right = rest[:1], rest[1:2]
    else:
        return None
    if not left or not right:
        return None
    return left[0], right[0]


def cmd_consider(mind: Mind, words: list[str]) -> None:
    pair = parse_consider_pair(words)
    if not pair:
        fail(voice("consider_need_two"))
        echo(voice("consider_need_two_echo"))
        return

    a, b = pair
    missing = [x for x in (a, b) if not in_memory(mind, x)]
    if missing:
        fail(voice("consider_missing", missing=", ".join(missing)))
        echo(voice("consider_missing_echo"))
        return

    combines = getattr(S, "COMBINES", None) or {}
    key = frozenset({a, b})
    entry = combines.get(key)
    if entry is None:
        for k, v in combines.items():
            if isinstance(k, str):
                parts = {p.strip() for p in k.replace("+", " ").replace(",", " ").split()}
                if parts == {a, b}:
                    entry = v
                    break

    if not entry:
        fail(voice("consider_no_pair", a=a, b=b))
        echo(voice("consider_no_pair_echo"))
        return

    say(entry.get("text") or entry.get("body") or voice("consider_fallback", a=a, b=b))
    if entry.get("echo"):
        echo(entry["echo"])

    for cid in entry.get("grant_memory") or []:
        if cid not in mind.memory:
            acquire(mind, cid)
            ok(voice("acquired", token=cid))

    unlock = entry.get("unlock_space")
    if unlock and unlock in S.SPACES and mind.space_id != unlock:
        mind.space_id = unlock
        if unlock in (S.CONCEPTS or {}) and unlock not in mind.memory:
            acquire(mind, unlock)
            ok(voice("acquired", token=unlock))
        name = S.SPACES[unlock].get("name", unlock)
        ok(voice("field_rerender", name=name))
        echo(entry.get("echo_unlock") or voice("field_rerender_echo"))

    if entry.get("trigger_light"):
        acquire(mind, "light")
        say(S.LIGHT_SUCCESS)
        mind.light_done = True


def cmd_memory(mind: Mind) -> None:
    if not mind.memory:
        print(voice("memory_empty"))
        echo(voice("memory_empty_echo"))
        return
    print(voice("memory_header"))
    for cid in mind.memory:
        print(f"    · {cid}")
    echo(voice("memory_echo"))


def try_light(mind: Mind, raw: str) -> bool:
    norm = re.sub(r"[^\w\s]", "", raw.lower()).strip()
    norm = re.sub(r"\s+", " ", norm)
    if norm not in S.LIGHT_PHRASES and not (
        norm.startswith("let there be") and "light" in norm
    ):
        return False

    missing = [r for r in S.LIGHT_REQUIRES if r not in mind.memory]
    if missing:
        fail(voice("light_not_ready"))
        echo(
            voice(
                "light_not_ready_echo",
                held=", ".join(S.LIGHT_REQUIRES),
                missing=", ".join(missing),
            )
        )
        return True

    acquire(mind, "light")
    say(S.LIGHT_SUCCESS)
    mind.light_done = True
    return True


def cmd_help() -> None:
    say(S.HELP)


def play_intro() -> None:
    for line in S.INTRO_LINES:
        print(line)
    if S.INTRO_ECHO:
        echo(S.INTRO_ECHO)


def handle(mind: Mind, raw: str) -> str | None:
    if not raw.strip():
        return None

    low = raw.lower().strip()
    if low in ("terminate", "quit", "exit", "end", "q"):
        ok(voice("session_terminated"))
        return "quit"

    if try_light(mind, raw):
        return "crash" if mind.light_done else None

    words = clean_command(raw)
    if not words:
        return None

    verb = words[0]

    if verb in ("help", "?"):
        cmd_help()
        return None
    if verb in ("hi", "hello", "hey"):
        echo(voice("hi"))
        return None
    if verb in ("mem", "memory", "inventory", "inv"):
        cmd_memory(mind)
        return None
    if verb == "look":
        cmd_look(mind, words)
        return None
    if verb == "remember":
        cmd_remember(mind, words)
        return None
    if verb == "think":
        cmd_think(mind, words)
        return None
    if verb == "consider":
        cmd_consider(mind, words)
        if mind.light_done:
            return "crash"
        return None
    if verb in ("create", "make", "let"):
        fail(voice("create_almost"))
        echo(voice("create_almost_echo"))
        return None

    fail(voice("unknown_verb", verb=verb))
    return None


def run() -> None:
    mind = Mind(space_id=S.START_SPACE)
    play_intro()
    print(f"{C.dim}__________________________________________________________________{C.x}")

    while True:
        try:
            raw = input(f"{C.blue} > {C.x}")
        except (EOFError, KeyboardInterrupt):
            print()
            ok(voice("session_terminated"))
            break
        result = handle(mind, raw)
        if result in ("quit", "crash"):
            break
        print(f"{C.dim}__________________________________________________________________{C.x}")
