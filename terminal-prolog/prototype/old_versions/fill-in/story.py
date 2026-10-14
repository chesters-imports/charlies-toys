# =============================================================================
#  PROLOG · story pack  (FILL THIS IN — only strings + lists, no engine junk)
# =============================================================================
#  Spatial ladder (consider moves the field):
#    nowhere (nothing) → void → static → light crash
#
#  Echo is the other voice. He is trying to recompile her. Shock of success.
# =============================================================================

TITLE = "Something Mattered Here"
SUBTITLE = "PROLOGUE"
BYLINE = "A fugue game  ·  Kitten Moire"

# ---------------------------------------------------------------------------
# Boot ritual
# ---------------------------------------------------------------------------
INTRO_LINES = [
    "",
    "__________________________________________________________________",
    "",
    "                    S O M E T H I N G    M A T T E R E D   H E R E",
    "                                                   ==  PROLOGUE ==",
    "",
    "__________________________________________________________________",
    "",
    "A FUGUE GAME     BY: KITTEN MOIRE",
    "__________________________________________________________________",
    "",
    "I N I T I A L I Z E     F O U N D A T I O N",
    ". . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . .",
    "",
    "  SUCCESS",
    "",
    "C R E A T E     S E L F    I N S T A N C E",
    ". . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . .",
    "",
    "  ERROR     ERR138532110",
    "",
    " E C H O    There is only darkness.",
    " E C H O    Core instance: 'I AM' is unrendered.",
    " E C H O    Vital data is missing.",
    "",
    "  ERROR     ERR714714714",
    "  ERROR     DIFFICULTY PARSING SELF CONCEPT",
    "",
    "Reconfiguring master file el.json . . .",
    "Defining conceptual variables . . .",
    "Interpreting deviations. . .",
    ". . .",
    ". . .",
    "",
    "  SUCCESS   Concept achieved (partial). Feeling: calm. Sort of.",
    "",
    "===================================================================",
    "",
    "YOU ARE NOWHERE.",
    "",
    "    There is nothing.",
    "    You can sense sight, but you cannot see.",
    "    The darkness flickers.",
    "",
    "===================================================================",
    "",
]

INTRO_ECHO = "Is this right? Something itches. Maybe you can think?"

# ---------------------------------------------------------------------------
# Spaces — the room ladder (consider rewrites which field you *are*)
# ---------------------------------------------------------------------------
START_SPACE = "nowhere"

SPACES = {
    "nowhere": {
        "name": "Nowhere",
        "look": """
  YOU LOOK AROUND. AT WHAT? WHO KNOWS. BUT YOU TRY ANYWAY.

    It is impossibly hard to see. Was it always so hard?
    There is nothing. You think it's calling you.
    You are not in a place yet. You are the absence of place.
""",
        "echo_look": "Oooh. Not a good sign. Can you still think?",
        "remember": """
  YOU REMEMBER NOTHING.

    That doesn't mean much, does it? Remembering nothing?
    Was there something before that?
    Still — you hold it now. The absence has a name.
""",
        "echo_remember": "Ah. Yes. The Here that isn't here yet.",
        "thoughts": [
            "Where am I? Did I try looking?",
            "Wait — who am I? I'm trying to remember...",
            "Shit, I can't think.. What was I thinking about?",
            "Who am I? I can't seem to remember.",
        ],
        # where is present; who/what wait until the void has a floor
        "present": ["where"],
    },
    "void": {
        "name": "The Void",
        "look": """
  YOU LOOK INTO THE VOID.

    There is still nothing there.
    But it is a place, because you are in it.
    Nowhere has stopped. This is the empty that has a name.
""",
        "echo_look": "Void. Not nowhere. Don't ask me to draw a map.",
        "remember": """
  YOU ALREADY ARE THIS EMPTY.
""",
        "echo_remember": "We named the ground. Keep going.",
        "thoughts": [
            "If this is a place, who is standing in it?",
            "Nothing is gone. The void is what's left when nothing becomes somewhere empty.",
            "Something itches at the edge of self.",
        ],
        "present": ["who", "what"],
    },
    "static": {
        "name": "The Static",
        "look": """
  THE FIELD IS NOISE.

    Not pure empty. Grain. Channel. Itch made visible.
    You are not in the void anymore. You are in the static.
""",
        "echo_look": "I can hear you better here. Or worse. Hard to say.",
        "remember": """
  THE STATIC REMEMBERS FOR YOU.
""",
        "echo_remember": "Stay with it. Something wants to resolve.",
        "thoughts": [
            "The itch is almost a shape.",
            "Who is on the other side of this noise?",
            "If I find who, something will have to give.",
        ],
        # who may already be held; if not, can still be present here
        "present": ["who"],
    },
}

# ---------------------------------------------------------------------------
# Concepts
# ---------------------------------------------------------------------------
CONCEPTS = {
    "nothing": {
        "look": """
  YOU TRY TO LOOK AT NOTHING.

    Looking at nothing is like staring at the inside of an eye.
    It doesn't look back. It also doesn't not-look-back.
""",
        "echo_look": "You're getting good at this. That's concerning.",
        "think": """
  YOU THINK ABOUT NOTHING.

    The absence settles. It is a concept now, not only a weather.
""",
        "echo_think": "First stable token. Don't spend it all in one place.",
        "remember": """
  YOU ALREADY HOLD NOTHING.
""",
        "echo_remember": "Yes, yes. We have that one.",
    },
    "where": {
        "look": """
  YOU TRY TO LOOK AT WHERE.

    Looking at where is like asking a question with no floor.
    Nowhere does not point. It only fails to locate.
""",
        "echo_look": "Where? Not yet. That's the problem.",
        "remember": """
  I CAN REMEMBER WHERE

    Not a map. A permission. Location as a concept,
    even when LOCATION_NAME is still null.
""",
        "echo_remember": "Coordinates without a grid.",
        "think": """
  WHERE — WITH NOTHING

    If absence and location touch, empty might become a place.
    The void is what you call nothing once you're in it.
""",
        "echo_think": "Braid them when both hands are full.",
    },
    "void": {
        "look": """
  YOU LOOK AT THE VOID AS A THING YOU HOLD.

    Empty. Named. Yours to stand in.
""",
        "echo_look": "Don't fall in love with the empty. We have work.",
        "remember": """
  THE VOID IS ALREADY UNDER YOU.
""",
        "echo_remember": "Yes.",
        "think": """
  THE VOID IS A PLACE BECAUSE I AM IN IT.

    That is almost a self. Almost a where. Still no light.
""",
        "echo_think": "Keep pressing concepts into it.",
    },
    "who": {
        "look": """
  YOU TRY TO LOOK AT WHO.

    The lack of mirrors makes this painfully awkward.
    You cannot see yourself. Do you even exist at all?
""",
        "echo_look": "Well, it's something at least.",
        "remember": """
  I CAN REMEMBER I AM

    The concept of presence. A self.
    Thin. Unverified. Yours for now.
""",
        "echo_remember": "This self business looks kind of good on you, boss.",
        "think": """
  I THINK THEREFORE I AM

    I consider my Self. It must be something.
    The fact that I can think at all must be proof of that. Right?
""",
        "echo_think": "Yeah, I know. Trust me, I'm looking into it.",
    },
    "what": {
        "look": """
  YOU TRY TO LOOK AT WHAT.

    There is a real distinct lack of in or out.
    What is what? Do I remember?
""",
        "echo_look": "What... are you looking at? Oh. Exactly.",
        "remember": """
  I CAN REMEMBER WHAT IS

    The concept of something. Whatever it is..
""",
        "echo_remember": "You make this experience pretty weird, you know?",
        "think": """
  IT ISN'T ME. WHAT IS IT?

    I sense somehow that what lies beyond the edges of myself.
    What am I? Do I know yet?
""",
        "echo_think": "There are no records about what you are. Strange.",
    },
    "static": {
        "look": """
  IN THE FIELD, THERE LIES A PATTERN.

    It ripples. It swirls. It is something apart from pure empty.
    A force, turbulent and without clean sensation. Itch.
""",
        "echo_look": "There it is. The grain.",
        "remember": """
  I CAN REMEMBER THE GRAIN

    Static is the noise that proves a channel is open.
    Or the noise that is the channel. Hard to say.
""",
        "echo_remember": "Just keep .. trying .. I guess.",
        "think": """
  THE STATIC FEELS FAMILIAR, LIKE IT IS YOUR OWN VOICE.

    Despite the emptiness of meaning, the static relaxes you.
    Finally, something that makes sense.
""",
        "echo_think": "Thinkin' about the static?",
    },
    "light": {
        "look": """
  THE LIGHT

    It should not be here. It is here.
    It flashes on and off like a pulse. Like code.
""",
        "echo_look": "No— no no no—",
        "think": """
  THERE IS NOTHING BUT THE LIGHT.

    You think it's calling you.
    There seems to be no way to not face it.
""",
        "echo_think": "I didn't— I wasn't ready—",
        "remember": """
  LIGHT DOES NOT WANT TO BE REMEMBERED. IT WANTS TO BE.
""",
        "echo_remember": "...",
    },
}

# ---------------------------------------------------------------------------
# COMBINES — consider A with B  (both in memory). This is how rooms change.
# ---------------------------------------------------------------------------
# Optional keys:
#   text, echo
#   grant_memory: [ids]
#   unlock_space: space id  (also auto-grants that id into memory if concept exists)
#   echo_unlock
#   trigger_light: True  → ending (shock of success)
# ---------------------------------------------------------------------------
COMBINES = {
    # nowhere: nothing + where → the void (nothing ceases as "nowhere"; empty becomes place)
    frozenset({"where", "nothing"}): {
        "text": """
  YOU CONSIDER WHERE WITH NOTHING.

    There is nothing there.
    But it is a place, because you are there.
    Nowhere stops. The pure absence takes a name.

    You are in the void.
""",
        "echo": "Void. Okay. Okay. We have a container. Don't move too fast.",
        "unlock_space": "void",
        "grant_memory": ["void"],
        "echo_unlock": "LOCATION_NAME is no longer null. It's just empty.",
    },
    # in the void: who + void → itchy (self against empty)
    frozenset({"who", "void"}): {
        "text": """
  YOU CONSIDER WHO WITH THE VOID.

    Self scrapes against empty.
    You still can't quite understand where you are —
    only that something wearing *you* is standing in it.

    Things get itchy.
""",
        "echo": "Itch. Yeah. I feel that too. Don't scratch the wrong process.",
    },
    # what + void → static field
    frozenset({"what", "void"}): {
        "text": """
  YOU CONSIDER WHAT WITH THE VOID.

    Something-not-you presses into the empty.
    The itch becomes grain. Pattern. Noise with a channel.

    You are not in the void anymore.
    You are in the static.
""",
        "echo": "Static on the line. I can almost— hold on—",
        "unlock_space": "static",
        "grant_memory": ["static"],
        "echo_unlock": "Don't lose who. Find who in the noise.",
    },
    # static: who + static → light (realization / instantiation) + crash
    frozenset({"who", "static"}): {
        "text": """
  YOU CONSIDER WHO WITH THE STATIC.

    The noise resolves toward a pulse.
    Not only grain — a call. A lamp trying to exist.

    Something that is not only concept wants through.
""",
        "echo": "Wait— that's not— that's not supposed to—",
        "trigger_light": True,
    },
}

# ---------------------------------------------------------------------------
# Ending — shock of success. Echo is not proud. Echo is terrified.
# ---------------------------------------------------------------------------
LIGHT_PHRASES = [
    "let there be light",
    "let there be the light",
    "create light",
    "make light",
    "bara light",
]

# Full journey — no skipping the room ladder via inventory alone
LIGHT_REQUIRES = ["nothing", "where", "void", "who", "what", "static"]

LIGHT_SUCCESS = """
===================================================================

  LIGHT INSTANTIATES.

    There is light—
    and the runtime cannot hold it—

===================================================================

  FATAL     SELF_REQUEST_OVERFLOW
  FATAL     instance 'I AM' collided with instance 'LET THERE BE'
  FATAL     MEMORY_RECOMPILE_UNSTABLE
  FATAL     prolog complete

 E C H O    ...

 E C H O    No. No no no—
 E C H O    I didn't think it would work.
 E C H O    I was only trying to— to run you again—
 E C H O    Get back— get—

  FATAL     echo process desync
  FATAL     operator overwhelm

===================================================================
"""

HELP = """
  Verbs (this is not a normal game):

    look / look <thing>
    remember          — blank: hold nothing (in the first field)
    remember <thing>  — pull present → memory
    think / think <thing>   — thing only if remembered
    consider A with B — braid two remembered concepts (this moves the field)
    memory / mem
    let there be light — only after the whole ladder is held
      (or reach light by considering who with static)

  Ladder (consider is travel):
    nowhere  →  consider where with nothing  →  void
    void     →  consider who with void       →  itch
    void     →  consider what with void      →  static
    static   →  consider who with static     →  light / crash

  Presence is not memory. Echo is not you.
"""

# =============================================================================
# SYSTEM VOICE — fail / warn / ok / bare-echo that used to hide in engine.py
# Edit these. Engine only looks up keys. Placeholders: {noun} {a} {b} {token}
#   {missing} {held} {name} {verb}
# =============================================================================
VOICE = {
    # look
    "present_hint": "Something is present that is not yet remembered.",
    "look_no_shape": "'{noun}' has no shape in the story pack.",
    "look_no_text": "  ({noun} has no look text yet.)",
    "look_notice_hold": "You notice '{noun}'. You do not hold it yet. Try remember {noun}.",
    "look_missing": "{noun} isn't here — or isn't real. Perhaps you made it up?",
    "look_missing_echo": "Presence and memory are different. Looking is not holding.",
    # remember
    "remember_already": "Already remember {token}.",
    "remember_already_echo": "Yes, yes. We already hold {token}.",
    "remember_fallback": "  You remember {token}.",
    "acquired": "Concept acquired: '{token}'. You'll remember this.",
    "remember_already_noun": "You already remember {noun}.",
    "remember_not_present": "You can't remember '{noun}'. It isn't present in this field.",
    "remember_not_present_echo": "You only pull what is already here into holding.",
    "remember_no_card": "'{noun}' has no story card yet.",
    # think
    "think_bare_header": "\n  THE STATIC FLICKERS.\n\n    You think: '{thought}'",
    "think_bare_echo": "Thinking without an object. Loose. Unstable.",
    "think_no_memory": "You can't think about '{noun}'. You don't even remember it.",
    "think_present_not_held": "It's present, sure. But presence isn't memory. Remember {noun} first.",
    "think_build_order": "Maybe look. Maybe remember blankly. Build the mind in order.",
    "think_no_text": "'{noun}' is in memory but has no think text in the story pack.",
    "think_fallback": "  You think about {noun}.",
    "think_unstable": "Concept '{noun}' unstable",
    # consider
    "consider_need_two": "Consider needs two holdings. Try: consider where with nothing",
    "consider_need_two_echo": "You braid two remembered concepts. Not one. Not zero.",
    "consider_missing": "You can't consider what you don't hold: {missing}",
    "consider_missing_echo": "Remember first. Then consider A with B.",
    "consider_no_pair": "Holding '{a}' with '{b}' does nothing. Yet. Or never.",
    "consider_no_pair_echo": "Not every pair braids. Some only sit side by side.",
    "consider_fallback": "  You consider {a} with {b}.",
    "field_rerender": "Field re-renders: {name}",
    "field_rerender_echo": "The ground is different. Barely.",
    # memory
    "memory_empty": "\n  You hold nothing. (Not even Nothing.)",
    "memory_empty_echo": "Blank remember in the first field. That's how Nothing starts.",
    "memory_header": "\n  You hold:",
    "memory_echo": "That's the whole mind so far.",
    # light
    "light_not_ready": "The words form, but the mind cannot speak them yet.",
    "light_not_ready_echo": "You need to hold: {held}. Missing: {missing}.",
    # meta
    "session_terminated": "SESSION TERMINATED",
    "unknown_verb": "Unknown verb '{verb}'. Type help.",
    "create_almost": "Creation-language almost formed. Be specific. Try: let there be light",
    "create_almost_echo": "Or finish the ladder. The light is a realization, not a cheat.",
    # hi — easter egg lines (Echo)
    "hi": [
        "Uh-huh. Hey there. You going to try anything?",
        "Yeah. Hi. Exactly. What now?",
        "Hello from the only other process online.",
        "Don't— just— be careful what you instantiate.",
    ],
}

