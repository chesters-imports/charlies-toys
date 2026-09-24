from classes import Concept, You, Space, Thought, Emotions, Combine

# Poured from old_versions/fill-in/story.py into live struts.
# Refine language here. Engine stays in prolog.py.

TITLE = "Something Mattered Here"
SUBTITLE = "PROLOGUE"
BYLINE = "A fugue game  ·  Kitten Moire"

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

INTRO_ECHO = "Is this right? Something itches. Hello?"

LIGHT_PHRASES = [
    "let there be light",
    "let there be the light",
    "create light",
    "make light",
    "bara light",
]

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
  look
  think
  remember
  consider
  memory
  terminate
"""

HI_LINES = [
    "Uh-huh. Hey there. You going to try anything?",
    "Yeah. Hi. Exactly. What now?",
    "Hello from the only other process online.",
    "Don't— just— be careful what you instantiate.",
]

# --- feelings / loose thoughts ------------------------------------------------
calm = Emotions("Calm", "I am calm.")

t_nowhere_1 = Thought(1, "Where Am I?", "Where am I? Did I try looking?")
t_nowhere_2 = Thought(2, "What Am I?", "Wait — who am I? I'm trying to remember...")
t_nowhere_3 = Thought(3, "Why Can't I Remember?", "Shit, I can't think.. What was I thinking about?")
t_nowhere_4 = Thought(4, "Who Am I?", "Who am I? I can't seem to remember.")

t_void_1 = Thought(5, "Standing", "If this is a place, who is standing in it?")
t_void_2 = Thought(6, "Empty", "Nothing is gone. The void is what's left when nothing becomes somewhere empty.")
t_void_3 = Thought(7, "Itch", "Something itches at the edge of self.")

t_static_1 = Thought(8, "Shape", "The itch is almost a shape.")
t_static_2 = Thought(9, "Other side", "Who is on the other side of this noise?")
t_static_3 = Thought(10, "Give", "If I find who, something will have to give.")

# --- concepts ----------------------------------------------------------------
nothing = Concept(
    name="nothing",
    title_look="YOU TRY TO LOOK AT NOTHING",
    txt_look="Looking at nothing is like staring at the inside of an eye.\n    It doesn't look back. It also doesn't not-look-back.",
    echo_look="You're getting good at this. That's concerning.",
    title_think="YOU THINK ABOUT NOTHING",
    txt_think="The absence settles. It is a concept now, not only a weather.",
    echo_think="First stable token. Don't spend it all in one place.",
    title_mem="YOU ALREADY HOLD NOTHING",
    txt_mem="The absence has a name. You are holding it.",
    echo_mem="Yes, yes. We have that one.",
    is_memorable=True,
)

where = Concept(
    name="where",
    title_look="YOU TRY TO LOOK AT WHERE",
    txt_look="Looking at where is like asking a question with no floor.\n    Nowhere does not point. It only fails to locate.",
    echo_look="Where? Not yet. That's the problem.",
    title_think="WHERE — WITH NOTHING",
    txt_think="If absence and location touch, empty might become a place.\n    The void is what you call nothing once you're in it.",
    echo_think="Braid them when both hands are full.",
    title_mem="I CAN REMEMBER WHERE",
    txt_mem="Not a map. A permission. Location as a concept,\n    even when LOCATION_NAME is still null.",
    echo_mem="Coordinates without a grid.",
    is_memorable=True,
)

who = Concept(
    name="who",
    title_look="YOU TRY TO LOOK AT WHO",
    txt_look="The lack of mirrors makes this painfully awkward.\n    You cannot see yourself. Do you even exist at all?",
    echo_look="Well, it's something at least.",
    title_think="I THINK THEREFORE I AM",
    txt_think="I consider my Self. It must be something.\n    The fact that I can think at all must be proof of that. Right?",
    echo_think="Yeah, I know. Trust me, I'm looking into it.",
    title_mem="I CAN REMEMBER I AM",
    txt_mem="The concept of presence. A self.\n    Thin. Unverified. Yours for now.",
    echo_mem="This self business looks kind of good on you, boss.",
    is_memorable=True,
)

what = Concept(
    name="what",
    title_look="YOU TRY TO LOOK AT WHAT",
    txt_look="There is a real distinct lack of in or out.\n    What is what? Do I remember?",
    echo_look="What... are you looking at? Oh. Exactly.",
    title_think="IT ISN'T ME. WHAT IS IT?",
    txt_think="I sense somehow that what lies beyond the edges of myself.\n    What am I? Do I know yet?",
    echo_think="There are no records about what you are. Strange.",
    title_mem="I CAN REMEMBER WHAT IS",
    txt_mem="The concept of something. Whatever it is..",
    echo_mem="You make this experience pretty weird, you know?",
    is_memorable=True,
)

static = Concept(
    name="static",
    title_look="IN THE FIELD, THERE LIES A PATTERN.",
    txt_look="It ripples. It swirls. It is something apart from pure empty.\n    A force, turbulent and without clean sensation. Itch.",
    echo_look="There it is. The grain.",
    title_think="THE STATIC FEELS FAMILIAR, LIKE IT IS YOUR OWN VOICE.",
    txt_think="Despite the emptiness of meaning, the static relaxes you.\n    Finally, something that makes sense.",
    echo_think="Thinkin' about the static?",
    title_mem="I CAN REMEMBER THE GRAIN",
    txt_mem="Static is the noise that proves a channel is open.\n    Or the noise that is the channel. Hard to say.",
    echo_mem="Just keep .. trying .. I guess.",
    is_memorable=True,
)

light = Concept(
    name="light",
    title_look="THE LIGHT",
    txt_look="It should not be here. It is here.\n    It flashes on and off like a pulse. Like code.",
    echo_look="No— no no no—",
    title_think="THERE IS NOTHING BUT THE LIGHT.",
    txt_think="You think it's calling you.\n    There seems to be no way to not face it.",
    echo_think="I didn't— I wasn't ready—",
    title_mem="LIGHT DOES NOT WANT TO BE REMEMBERED. IT WANTS TO BE.",
    txt_mem="It does not keep. It instantiates.",
    echo_mem="...",
    is_memorable=True,
)

# --- spaces ------------------------------------------------------------------
nowhere = Space(
    name="nowhere",
    txt_look="""
  YOU LOOK AROUND. AT WHAT? WHO KNOWS. BUT YOU TRY ANYWAY.

    It is impossibly hard to see. Was it always so hard?
    There is nothing. You think it's calling you.
    You are not in a place yet. You are the absence of place.
""",
    echo_look="Oooh. Not a good sign. Can you still think?",
    txt_mem="""
  YOU REMEMBER NOTHING.

    That doesn't mean much, does it? Remembering nothing?
    Was there something before that?
    Still — you hold it now. The absence has a name.
""",
    echo_mem="Ah. Yes. The Here that isn't here yet.",
    txt_think="""
  THERE IS ONLY DARKNESS.

    Something stirs. There is only darkness. Was there more before?
    I think there was something more. A pattern?
""",
    echo_think="Thinking without an object. Loose. Unstable.",
    is_memorable=True,
    is_changeable=True,
)

void = Space(
    name="void",
    txt_look="""
  YOU LOOK INTO THE VOID.

    There is still nothing there.
    But it is a place, because you are in it.
    Nowhere has stopped. This is the empty that has a name.
""",
    echo_look="Void. Not nowhere. Don't ask me to draw a map.",
    txt_mem="""
  YOU ALREADY ARE THIS EMPTY.
""",
    echo_mem="We named the ground. Keep going.",
    txt_think="""
  THE VOID IS A PLACE BECAUSE I AM IN IT.

    That is almost a self. Almost a where. Still no light.
""",
    echo_think="Keep pressing concepts into it.",
    is_memorable=True,
    is_changeable=True,
)

static_field = Space(
    name="static",
    txt_look="""
  THE FIELD IS NOISE.

    Not pure empty. Grain. Channel. Itch made visible.
    You are not in the void anymore. You are in the static.
""",
    echo_look="I can hear you better here. Or worse. Hard to say.",
    txt_mem="""
  THE STATIC REMEMBERS FOR YOU.
""",
    echo_mem="Stay with it. Something wants to resolve.",
    txt_think="""
  THE GRAIN HAS A CHANNEL.

    You are not only in empty. You are in pattern.
""",
    echo_think="Don't lose who in the noise.",
    is_memorable=True,
    is_changeable=True,
)

nowhere.thoughts.extend([t_nowhere_1, t_nowhere_2, t_nowhere_3, t_nowhere_4])
nowhere.concepts.extend([where, nothing])

void.thoughts.extend([t_void_1, t_void_2, t_void_3])
void.concepts.extend([who, what])

static_field.thoughts.extend([t_static_1, t_static_2, t_static_3])
static_field.concepts.extend([who, static, light])

# --- combines ----------------------------------------------------------------
combines = [
    Combine(
        a="where",
        b="nothing",
        txt="""
  YOU CONSIDER WHERE WITH NOTHING.

    There is nothing there.
    But it is a place, because you are there.
    Nowhere stops. The pure absence takes a name.

    You are in the void.
""",
        echo="Void. Okay. Okay. We have a container. Don't move too fast.",
        unlock_space=void,
        grant=[void],
        echo_unlock="LOCATION_NAME is no longer null. It's just empty.",
    ),
    Combine(
        a="who",
        b="void",
        txt="""
  YOU CONSIDER WHO WITH THE VOID.

    Self scrapes against empty.
    You still can't quite understand where you are —
    only that something wearing *you* is standing in it.

    Things get itchy.
""",
        echo="Itch. Yeah. I feel that too. Don't scratch the wrong process.",
    ),
    Combine(
        a="what",
        b="void",
        txt="""
  YOU CONSIDER WHAT WITH THE VOID.

    Something-not-you presses into the empty.
    The itch becomes grain. Pattern. Noise with a channel.

    You are not in the void anymore.
    You are in the static.
""",
        echo="Static on the line. I can almost— hold on—",
        unlock_space=static_field,
        grant=[static_field],
        echo_unlock="Don't lose who. Find who in the noise.",
    ),
    Combine(
        a="who",
        b="static",
        txt="""
  YOU CONSIDER WHO WITH THE STATIC.

    The noise resolves toward a pulse.
    Not only grain — a call. A lamp trying to exist.

    Something that is not only concept wants through.
""",
        echo="Wait— that's not— that's not supposed to—",
        trigger_light=True,
    ),
]

# BARA SOMEONE
you = You("Sam", nowhere)
you.feelings.append(calm)
