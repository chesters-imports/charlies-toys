import random
import re
import string
from render import *
from classes import Concept, You, Space, Thought, Emotions
from story import *
from randoms import *


### LET US DEFINE THE OBJECTS OF PRODUCTION:::

#=====================================================================================================================#
######## b e g i n   p r o l o g   I N T R O                                    
#=====================================================================================================================#

# she awakens from the nothing to find it is no more. where did he go? was there ever a where to being with?
# bara elohim aubel kra and there was light and it was good.

for line in INTRO_LINES:
    print(line)
echoPrint(INTRO_ECHO)

#=======================================================================================================================#
######## e n d   p r o l o g   I N T R O                                    
#=======================================================================================================================#

# VERBS
#  - THINK (noun)
#       Will produce random thoughts (about location if no noun present)
#  - REMEMBER (noun)
#       Stores memorable content into your memory
#  - CONSIDER noun1 with noun2
#       Braids two held memories · may rewrite you.space (room change)
#  - GENERATE noun
#       Creates a Space or Thing
#  - LOOK (noun)
#  - GO (noun)
#  - MEM / MEMORY
# 
# YOU ARE SOMEWHERE -> 
# (block restored from old_versions · your wire notes · Hands 2026-03)

crashed = False
while True:
    if crashed:
        break
    print(f'{paintIt.gray}__________________________________________________________________{paintIt.x}')

    def clean_command(command):
        command = command.lower()
        command = command.translate(str.maketrans('', '', string.punctuation))
        return command.strip()

    raw = input(paintIt.sHb + " ꓘ " + paintIt.x + " ")
    command = clean_command(raw)
    filler_words = ["at", "the", "a", "an", "to", "about", "of", "I", "am"]

    words = command.lower().split()
    words = [word for word in words if word not in filler_words]

    verb = None
    noun_error = "IMPOSSIBLECOMBINATIONERROR"

    if "terminate" in command or command in ("quit", "exit", "q"):
        success(" SESSION TERMINATED")
        break

    # let there be light — before verb parse (shock of success)
    norm = re.sub(r"\s+", " ", re.sub(r"[^\w\s]", "", raw.lower())).strip()
    light_ask = norm in LIGHT_PHRASES or (norm.startswith("let there be") and "light" in norm)
    if light_ask:
        held_names = [getattr(item, "name", "").lower() for item in you.memory]
        missing = [r for r in LIGHT_REQUIRES if r not in held_names]
        if missing:
            fail("The words form, but the mind cannot speak them yet.")
            echoPrint("You need to hold: " + ", ".join(LIGHT_REQUIRES) + ". Missing: " + ", ".join(missing) + ".")
            continue
        print(LIGHT_SUCCESS)
        break

    if len(words) > 0:
        verb = words[0]
    if len(words) > 1:
        noun1 = words[1]
    if len(words) > 2:
        noun2 = words[2]

    if verb == "look":
        if len(words) == 1:
            print(you.space.txt_look)
            echoPrint(you.space.echo_look)
        if len(words) > 1:
            for item in you.space.concepts:
                if item.name == noun1:
                    flag = True
                    warning(f"[Stabilzation value: {random.uniform(0.0, 1.0)}]")
                    print(item.title_look)
                    print(item.txt_look)
                    success(f"CONCEPT VALID: {noun1} exists in system.")
                    echoPrint(item.echo_look)
                    break
            else:
                fail(f"{noun1} isn't real. Perhaps you made it up?")

    if verb == "think":
        Spacer()
        if len(words) == 1:
            event_fail(f"TRYING TO THINK")
            thought = random.choice(you.space.thoughts)
            print(generate_quote())
            print()
            echoPrintY(thought.txt)
        if len(words) > 1:
            # held Spaces/Concepts match case-insensitively (cmd is lower; names may be "Nothing")
            # one fail at end — never spam fail/error on every non-match in the bag
            event_fail(f"TRYING TO THINK ABOUT {noun1}")
            target = noun1.lower()
            found = False

            for item in you.memory:
                if getattr(item, "name", "").lower() == target:
                    found = True
                    title = getattr(item, "title_think", None)
                    if title:
                        print(title)
                    print(item.txt_think)
                    warning(f"Concept '{item.name}' unstable")
                    if getattr(item, "echo_think", None):
                        echoPrint(item.echo_think)
                    break

            if not found:
                for item in you.space.concepts:
                    if item.is_memorable and getattr(item, "name", "").lower() == target:
                        found = True
                        print(generate_quote())
                        warning(f"[Fail rate {random.randint(10, 20)}] Try remembering?")
                        break

            if not found:
                print(generate_quote())
                fail(f"{noun1} not found. Perhaps you made it up?")

    if verb == "remember":
        if len(words) == 1:
            # first fields: blank remember holds NOTHING (not the room name)
            if you.space.name in ("nowhere", "void"):
                already = any(getattr(item, "name", "").lower() == "nothing" for item in you.memory)
                if already:
                    event_partialsuccess("ALREADY REMEMBER")
                    echoPrint("Yes, yes. We already hold nothing.")
                else:
                    print(you.space.txt_mem)
                    echoPrint(you.space.echo_mem)
                    you.memory.append(nothing)
                    success("Concept acquired: 'nothing'. You'll remember this.")
            elif you.space.is_memorable == True:
                if you.space in you.memory:
                    print("")
                    event_partialsuccess("ALREADY REMEMBER")
                    echoPrint("Yes, yes. We already remember {}.".format(you.space.name))
                else:
                    success("Concept acquired: '{}'".format(you.space.name) + " You'll remember this.")
                    print(you.space.txt_mem)
                    echoPrint(you.space.echo_mem)
                    you.memory.append(you.space)
            else:
                fail("This place won't stick. Not memorable.")
                echoPrint("Some rooms refuse to be kept.")
        if len(words) > 1:
            target = noun1.lower()
            found = False
            for item in you.memory:
                if getattr(item, "name", "").lower() == target:
                    found = True
                    if item.is_memorable == True:
                        success(f"You already remember {item.name}.")
                    break
            if not found:
                for item in you.space.concepts:
                    if getattr(item, "name", "").lower() == target:
                        found = True
                        if item.is_memorable == True:
                            warning(f"[ID UNSTABLE {random.randint(100, 200)}]")
                            print(item.title_mem)
                            print(item.txt_mem)
                            success("Concept aquir: '{}'.".format(item.name) + " You'll remember this.")
                            echoPrint(item.echo_mem)
                            you.memory.append(item)
                            you.space.concepts.remove(item)
                        else:
                            fail(f"{noun1} isn't memorable. Just leave it alone.")
                        break
            if not found:
                fail(f"{noun1} isn't here to remember. Perhaps you made it up?")
                echoPrint("Can't store what isn't present.")
 
    if verb in ["mem", "memory"]:
        print("You have the following: ")
        for item in you.memory:
            print(item.name)
            print(item.txt_mem)
            echoPrint(item.echo_mem)

    if verb in ["hi", "hello", "hey", "hello?"]:
        print("")
        echoPrint(random.choice(HI_LINES))

    if verb in ["help", "?"]:
        print(HELP)

    # ---------------------------------------------------------------------
    # consider A with B  ·  both in memory  ·  optional room change
    #   consider who with what
    #   consider what static
    # room change = you.space = combine.unlock_space  (already how you live)
    # ---------------------------------------------------------------------
    if verb == "consider":
        # strip glue words so "who with what" / "who and what" work
        glue = {"with", "and", "against", "into", "to"}
        nouns = [w for w in words[1:] if w not in glue]
        if len(nouns) < 2:
            fail("Consider WHAT with WHAT? Need two names.")
            echoPrint("Two tokens. Both held. Then braid.")
        else:
            a_name, b_name = nouns[0], nouns[1]

            def held(name):
                """Find a remembered concept/space by .name (case-insensitive)."""
                for item in you.memory:
                    if getattr(item, "name", "").lower() == name.lower():
                        return item
                return None

            A = held(a_name)
            B = held(b_name)
            if A is None or B is None:
                missing = []
                if A is None:
                    missing.append(a_name)
                if B is None:
                    missing.append(b_name)
                fail("Not held: " + ", ".join(missing) + ". Remember first.")
                echoPrint("Can't braid what you don't have. Memory first.")
            else:
                pair = {a_name.lower(), b_name.lower()}
                hit = None
                for c in combines:
                    if {c.a, c.b} == pair:
                        hit = c
                        break
                if hit is None:
                    fail("No braid for {} with {}.".format(a_name, b_name))
                    echoPrint("Not wired yet. Or not real. Your call.")
                else:
                    print(hit.txt)
                    if hit.echo:
                        echoPrint(hit.echo)
                    for gifted in getattr(hit, "grant", []) or []:
                        if gifted not in you.memory:
                            you.memory.append(gifted)
                            success("Concept acquired: '{}'.".format(gifted.name))
                    if hit.unlock_space is not None and you.space is not hit.unlock_space:
                        you.space = hit.unlock_space
                        success("FIELD RERENDER · you are in: " + you.space.name)
                        echoPrint(hit.echo_unlock or "The ground is different. Barely.")
                    if getattr(hit, "trigger_light", False):
                        if light not in you.memory:
                            you.memory.append(light)
                        print(LIGHT_SUCCESS)
                        crashed = True

    # unknown verb / bare noise — was silent fallthrough; touch the lines up anytime
    known_verbs = {
        "look", "think", "remember", "mem", "memory",
        "hi", "hello", "hey", "consider", "help", "?",
    }
    if verb is None:
        fail("Empty command. Say something.")
        echoPrint("Silence is already doing enough. Try a verb.")
    elif verb not in known_verbs:
        fail(f"'{verb}' isn't a verb I know.")
        echoPrint("Try look / think / remember / mem / consider. Or terminate.")
