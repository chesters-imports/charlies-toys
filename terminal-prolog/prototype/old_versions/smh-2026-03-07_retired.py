import time
import sys
import random
import string
import readline

boot_screen = [
    "           =========================================",
    "           =========================================",
    " ",
    "                    O.I.X. OPERATING SYSTEM",
    "                            v3.15",
    " ",
    "           =========================================",
    "           =========================================",
    " ",
    " ",

    "   software developed by CHESTER'S IMPORTS ",
    "   'Quality provider of unique imports, including you!' ",
    "   copyright '43 all rights reserved ",
    " ",
    " ",
    " "
]

class s:
    r = '\033[91m'
    sHg = '\033[102m'
    sHb = '\033[1;33;44m'
    sHy = '\033[103m'

    sHr = '\033[101m'
    sHYr = '\033[1;31;43m'

    g = '\033[1;92m'
    ga = '\033[1;30;49m'
    HWr = '\033[0;31;47m'
    b = '\033[94m'
    y = '\033[93m'
    sU = '\033[4m'
    sB = "\033[2m"
    x = '\033[00m'

FAST_MODE = True # true when testing


def prompt(text):
    print(f'{s.ga}__________________________________________________________________{s.x}')
    print(s.HWr + text + s.x, end="", flush=True)

def slowPrint(string, speed=0.01):
    for char in string:
        sys.stdout.write(char)
        sys.stdout.flush()
        if not FAST_MODE:
           time.sleep(speed)
    print("", flush=True)
    if not FAST_MODE:
        time.sleep(0.8)

def typing(string, speed=0.12):
    for char in string:
        sys.stdout.write(char)
        sys.stdout.flush()
        if not FAST_MODE:
            time.sleep(speed)
    print()


def timerSS(speed=1.5):
    if not FAST_MODE:
        time.sleep(speed)
def timerS(speed=0.8):
    if not FAST_MODE:
        time.sleep(speed)
def timerM(speed=0.5):
    if not FAST_MODE:
        time.sleep(speed)
def timerF(speed=0.2):
    if not FAST_MODE:
        time.sleep(speed)

def echoPrintY(text):
    print(s.y + " E C H O " + s.x, flush=True, end="  ")
    timerS()
    slowPrint(text)
    timerM()

def echoPrint(text):
    print(s.r + " E C H O " + s.x, flush=True, end="  ")
    timerS()
    slowPrint(text)
    timerM()

def thoughtPrint(text):
    print(s.y + " SDK 808 " + s.x, flush=True, end="  ")
    timerS()
    slowPrint(text)
    timerM()

def event_success(text):
    print(f"  {s.sHg} {text} {s.x}\n", flush=True)
    
def event_partialsuccess(text):
    print(f" {s.ga} {s.sHy} {text} {s.x}\n", flush=True)

def error(text):
    print(s.sHr + " ERROR    " + s.x, flush=True, end="")
    timerF()
    print(f"{s.sHYr} {text} {s.x} ")
    timerM()

def success(text):
    print("\n  " + s.sHg + " SUCCESS " + s.x, flush=True, end="")
    timerF()
    print(f" {s.y} {text} {s.x} ")
    timerM()

def fail(text):
    print("\n  " + s.sHr + " ERR-808 " + s.x, flush=True, end="")
    timerF()
    print(f" {s.r} {text} {s.x} ")
    timerM()

def errorb(text):
    print(s.sHr + " ERROR    " + s.x, flush=True, end="")
    timerF()
    print(f"{s.r}{s.sU} {text} {s.x} ")
    timerM()

def load_bar(width=25, char=" █"):
    print("")
    slowPrint(f"{char * width}")
    print("")
    
def Spacer():
    print()


### define EMOTIONS

class Emotions(object):
    def __init__(self, name, description):
        self.name = name
        self.description = description

### define SPACES

class Space(object):
    def __init__(self, name, txt_mem, txt_look, echo_look, echo_mem, is_memorable):
        self.name = name
        self.txt_mem = txt_mem
        self.txt_look = txt_look
        self.echo_look = echo_look
        self.echo_mem = echo_mem
        self.is_memorable = is_memorable
        self.thoughts = []
        self.exits = {}
        self.things = []

class You(object):
    def __init__(self, name, space):
        self.name = name
        self.space = space
        self.feelings = []
        self.memory = []

class Thing(object):
    def __init__(self, name, txt_mem, txt_look, is_memorable, is_movable):
        self.name = name
        self.txt_mem = txt_mem
        self.txt_look = txt_look
        self.is_memorable = is_memorable
        self.is_movable = is_movable
        self.thoughts = []

class Thought(object):
    def __init__(self, name, txt, is_changable):
        self.name = name
        self.txt = txt
        self.is_changable = is_changable


### bara EMOTIONS
calm = Emotions("Calm", "I am calm.")



# bara THOUGHTS


static_thought1 = Thought("Where Am I?", "I wonder where I am? Did I try looking?", False)
static_thought2 = Thought("What Am I?", "Wait! What am I? I'm trying to remember...", False)
static_thought3 = Thought("Why Can't I Remember?", "Shit, I can't think.. What was I thinking about?", False)
static_thought4 = Thought("Who Am I?", "Who am I? I can't seem to remember.", False)

pulse = Thing(
    name = "pulse",
    txt_mem = "A memory about the thing.",
    txt_look = """
  LOOKING AT THE PULSE

    It blinks like morse code. It might be a code. Do you remember?""",
    is_memorable = True,
    is_movable = False)

pulse = Thing(
    name = "static",
    txt_mem = "A memory about the thing.",
    txt_look = """
  LOOKING AT THE PULSE

    It blinks like morse code. It might be a code. Do you remember?""",
    is_memorable = True,
    is_movable = False)

who = Thing(
    name = "who",
    txt_mem = """
  I AM.

        It seems improbable, and yet, it is. Regardless of what I think,
        somehow it appears that I am.
""",
    txt_look = """
  YOU SEE NOTHING.

    The lack of mirrors make this painfully awkward.
    Do you even have eyes?
""",
    is_memorable = True,
    is_movable = False
)

### bara SPACES
void = Space(
    name = "The Void", 
    txt_mem = f"""
  THERE IS ONLY DARKNESS.

    Something stirs. There is only darkness. Was there more before?
    I think there was something more. A pattern?
""",
    txt_look = f"""
  YOU LOOK AROUND. AT WHAT? WHO KNOWS. BUT YOU TRY ANYWAY.

    It is impossibly hard to see. Was it always so hard?
    There is nothing. You think its calling you.
""",
    echo_look = "Oooh. Not a good sign. Can you still think?",
    echo_mem = "Ah. Yes. The Here, that lies between all things.",
    is_memorable = True)
void.thoughts.append(static_thought1)
void.thoughts.append(static_thought2)
void.thoughts.append(static_thought3)
void.thoughts.append(static_thought4)
void.things.append(pulse)
void.things.append(who)


static = Space(
    name = "static", 
    txt_mem = f"""
  REMEMBERING THE STATIC

    The static is everywhere. The static was everywhere.
    I remember the static. Is there anything else?
""",
    txt_look = f"""
  YOU LOOK AROUND. AT WHAT? WHO KNOWS. BUT YOU TRY ANYWAY.

    You realize it is impossibly hard to see. Was it always so hard?
    There is nothing static. You think its calling you. 
""",
    echo_look = "Is there anything to see here? Is there even somewhere?",
    echo_mem = "Ah. Yes. The static that lies between all things.",
    is_memorable = True)

# BARA adm
you = You("Sam", void)
you.feelings.append(calm)


print(f"""
__________________________________________________________________


                    S O M E T H I N G    M A T T E R E D   H E R E
                                                   ==  PROLOGUE ==

__________________________________________________________________

A FUGUE GAME     BY: KITTEN MOIRE
__________________________________________________________________
""")


for line in boot_screen:
    print(line)
    time.sleep(0)

prompt(" USER  > ")
timerM()
typing(" sdk808")
timerM()
print(f"\n  User exists. Enter password.\n")
prompt(" PASS  > ")
timerS()
typing(" ********")
timerM()
print(f"\n  Password accepted. Accessing source.\n")
echoPrintY("Welcome back, Sam.\n")
prompt(text=" INPUT > ")
timerSS()
typing(" INITIALIZE foundation")
timerM()
load_bar()
event_success("SUCCESS")
prompt(" INPUT > ")
timerS()
typing(" CREATE self instance")
timerM()
load_bar()
event_partialsuccess("PARTIAL SUCCESS")
timerM()
error("ERR138532110")
echoPrintY("There is only darkness.")
echoPrintY(f"Core instance: '{s.sU}I AM{s.x}' is unrenderable.")
echoPrintY("Vital data is missing. Attempting repairs.")
timerSS()
error("ERR714714714")
errorb(f"DIFFICULTY PARSING SELF CONCEPT")
slowPrint("""
   Reconfiguring master file el.json . . .
   Defining conceptual variables . . .
   Interpreting deviations. . . 
   . . .
   . . .
""")
event_success("SUCCESS")
print(f"Concept achieved {s.ga}[CANNOT VALIDATE ID]{s.x}\n")
prompt(text=" INPUT > ")
timerM()
typing(" RUN self.exe")
timerS()

success("Instance I.AM in {LOCATION_NAME}")
fail("LOCATION_NAME is null")
print("""
   YOU ARE SOMEWHERE. 
   
   You can sense sight, but you cannot see. The darkness flickers. 
   There is the faint memory of thought.
""")

echoPrint(f"Is this right? Something itches. Hello?")


while True:
    FAST_MODE = False
    print(f'{s.ga}__________________________________________________________________{s.x}')

    def clean_command(command):
        command = command.lower()
        command = command.translate(str.maketrans('', '', string.punctuation))
        return command.strip()

    raw = input(s.sHb + " INPUT > " + s.x + " ")
    command = clean_command(raw)
    filler_words = ["at", "the", "a", "an", "to", "about", "of", "I", "am"]

    words = command.lower().split()
    words = [word for word in words if word not in filler_words]

    verb = None
    noun = None

# VERBS
#  - THINK (noun)
#       Will produce random thoughts (about location if no noun present)
#  - REMEMBER (noun)
#       Stores memorable content into your memory
#  - CONSIDER noun1 with noun2
#       Creates new memory
#  - GENERATE noun
#       Creates a Space or Thing
#  - LOOK (noun)
#  - GO (noun)
# 
# 
# 
# YOU ARE SOMEWHERE -> 

    if len(words) > 0:
        verb = words[0]
    if len(words) > 1:
        noun = words[1]

    if "end" in command:
        print("The room closes. The fades to your reality once more.")
        break

    if noun == "cat":
        echoPrint("That's purrfect.")
    
           
    if verb == "look":
        if len(words) == 1:
            print(you.space.txt_look)
            echoPrint(you.space.echo_look)
        elif len(words) > 1:
            for item in you.space.things:
                if item.name == noun:
                    success("SUCCESS")
                    print(item.txt_look)
            for item in you.memory:
                if item.name == noun:
                    event_partialsuccess("YOU REMEMBER SOMETHING")
                    print("Check your mem.")
                    print(item.txt_look)
            

    if verb == "look2":
        if len(words) == 1:
            print(you.space.txt_look)
            echoPrint(you.space.echo_look)
            if item in you.space.things:
                if item.name == noun:
                    success("SUCCESS")
                    print(item.txt_look)
            if item in you.memory:
                    event_partialsuccess("YOU REMEMBER SOMETHING")
                    print("Check your mem.")
                    print(item.txt_look)
            else:
                print(item.txt_look)
                fail(f"{noun} isn't real. Perhaps you made it up?")


                    

    if verb == "remember":
        if len(words) == 1:
            if you.space.is_memorable == True:
                if you.space in you.memory:
                    print("")
                    event_partialsuccess("ALREADY REMEMBERED")
                    echoPrint("Yes, yes. We already remembered {}.".format(you.space.name))
                else:
                    success("Memory aquired: {}.".format(you.space.name) + " You'll remember this.")
                    print(you.space.txt_mem)
                    echoPrint(you.space.echo_mem)
                    you.memory.append(you.space)
            else:
                print("Nah.")
        else:           
            for item in you.space.things:
                if item.name == noun:
                    # Check is it movable
                    if item.is_memorable:
                        print("You remember the {}".format(item.name))
                        # Remove from room
                        you.space.things.remove(item)
                        # Add to player's inventory
                        you.memory.append(item)
                    else:
                        print("Sorry, you can't move that.")
               
    if verb == "think":
        Spacer()
        if len(words) == 1:
            event_partialsuccess("I try thinking. I can always try to think.")
            thought = random.choice(you.space.thoughts)
            thoughtPrint(thought.txt)
        else:
            for item in you.space.things:
                if item.name == noun:
                    print(item.thought.txt)
            for item in you.memory:
                if item.name == noun:
                    print(item.thought)

    if verb in ["mem", "memory"]:
        print("You have the following: ")
        for item in you.memory:
            print(item.name)

    if verb in ["hi", "hello", "hey", "hello?"]:
        print("")
        choices = ["Uh-huh. Hey there. You going to try anything?", "Yeah. Hi. Exactly. What now?"]
        reply = random.choice(choices)
        echoPrint(reply)
