import sqlite3
import time
import sys
import random

FAST_MODE = True # true when testing

def slowPrint(string, speed=0.05):
    for char in string:
        sys.stdout.write(char)
        sys.stdout.flush()
        if not FAST_MODE:
           time.sleep(speed)
    print()
        
def initialize_db():
    con = sqlite3.connect("smh.db")

    cur = con.cursor()
    cur.execute("""
                 CREATE TABLE IF NOT EXISTS emotion(
                 ID INTEGER PRIMARY KEY AUTOINCREMENT,
                 name TEXT UNIQUE NOT NULL,
                 description TEXT
                 );
                 """)

    cur.execute("INSERT OR IGNORE INTO emotion (name, description) VALUES ('Calm', 'I am calm.')")

    con.commit()
    con.close()

def current_emotions():
    with sqlite3.connect("smh.db") as con:
        cur = con.cursor()

        query = 'SELECT name FROM emotion'
        cur.execute(query)

        feeling = cur.fetchall()[0][0]

        for row in feeling:
            return (feeling)



class Room(object):
    def __init__(self, name, description, short_description, is_memorable):
        self.name = name
        self.description = description
        self.short_description = short_description
        self.exits = {}
        self.items = []
        self.thoughts = []
        self.is_memorable = is_memorable

class Player(object):
    def __init__(self, name, location):
        self.name = name
        self.location = location
        self.inventory = []
        self.memory = []

class Item(object):
    def __init__(self, name, description, thought,  is_movable):
        self.name = name
        self.description = description
        self.thoughts = []
        self.thought = thought
        self.is_movable = is_movable

class Thought(object):
    def __init__(self, name, content, is_changable):
        self.name = name
        self.content = content
        self.is_changable = is_changable

class s:
    r = '\033[91m'
    sHg = '\033[102m'
    sHb = '\033[0;33;44m'
    sHy = '\033[103m'

    sHr = '\033[101m'
    sHYr = '\033[1;31;43m'

    g = '\033[1;92m'
    ga = '\033[1;30;40m'
    b = '\033[94m'
    y = '\033[93m'
    sU = '\033[4m'
    sB = "\033[2m"
    x = '\033[0m'

# BARA thoughts
thought1 = Thought("Where Am I?", "I wonder where I am? I could think harder on this.", False)
thought2 = Thought("What Am I?", "Wait! What am I? I could think harder on this.", False)
thought3 = Thought("Why Can't I Remember?", "Shit, I can't think.. What was I thinking about?", False)
thought4 = Thought("Who Am I?", "Who am I? I can't seem to remember.", False)


# BARA thngs
light = Item("light", f"""
THE LIGHT

    It flashes on an off like a pulse. 
    Like morse code. Could it be a code? 

    It appears {s.r + s.sU}beyond{s.x} where you are now. You have no ability to move. 
    The light lingers, blinking in its repeating pattern.
    I suppose you could try to {s.g + s.sU}think{s.x} about the {s.b + s.sU}light{s.x}.
    You seem capable of at least that.
    
""", f"""
THINKING ABOUT THE LIGHT

    You realize it is somewhat hard to think. Was it always so hard?
    There is nothing but the light. You think its calling you.
    There seems to be no way to not face the light.
    Can you not turn? Are you even here?

    You could try to {s.g + s.sU}go{s.x} to the {s.b + s.sU}light{s.x}. 
    I swear, its not death.
    Probably.

""", False)
key = Item("key", "It burns when you remember it.", "Key", True)

# BARA plcs
void = Room(f"The Unseen", f"""
YOU LOOK AROUND. AT WHAT? WHO KNOWS. BUT YOU TRY ANYWAY.

     You are standing in a space made of nothing. 
     There is nothing to see except the {s.g + s.sU}light{s.x} in the distance.
     There is a sense of wind against what might be your skin.
     You realize you cannot sense the shape of your own self.
     Where are you? 
     Are you even here....?
     """, "description", True)

void.items.append(light)
void.items.append(key)
void.thoughts.append(thought1)
void.thoughts.append(thought2)
void.thoughts.append(thought3)
void.thoughts.append(thought4)

# BARA adm
player = Player("Sam", void)

#EXITS
#void.exits["go"]= beyond


print("""
__________________________________________________________________


                    S O M E T H I N G    M A T T E R E D   H E R E
                                                   ==  PROLOGUE ==

__________________________________________________________________

A FUGUE GAME     BY: KITTEN MOIRE
__________________________________________________________________
""")
slowPrint(f"""
I N I T I A L I Z E     F O U N D A T I O N
. . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . .

{s.sHg} SUCCESS {s.x}

C R E A T E     S E L F    I N S T A N C E
. . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . .

""")


initialize_db()

# slowPrint("response  " + s.g + "PARTIAL SUCCESS" + s.x)
slowPrint(s.sHr + " ERROR    " + s.x + f"{s.sHYr} ERR138532110 {s.x} ")

slowPrint(s.r + " E C H O " + s.x + "   There is only darkness.")
slowPrint(s.r + " E C H O " + s.x + "   Core instance: '" + s.sU + "I AM" + s.x + "' is unrendered.")
slowPrint(s.r + " E C H O " + s.x + "   Vital data is missing.")
print()

slowPrint(s.sHr + " ERROR    " + s.x + f"{s.sHYr} ERR714714714 {s.x} ")
slowPrint(s.sHr + " ERROR    " + s.x + s.r + " " + s.sU + f"DIFFICULTY PARSING SELF CONCEPT{s.x} ")

#slowPrint(" reconfig................")

slowPrint("""
Reconfiguring master file el.json . . .
Defining conceptual variables . . .
Interpreting deviations. . . 
. . .
. . .""")

feeling = current_emotions()
slowPrint(s.sHg + " SUCCESS  " + s.x + f"{s.g} Concept achieved. You are feeling {s.sU}{feeling}{s.x}. ")

#slowPrint(f' SUCCESS.  Concept achieved. You are feeling {feeling}.')

print("""
===================================================================

YOU ARE SOMEWHERE.

    You can sense sight, but you cannot see.
    The darkness flickers like static inside, beyond the nothing.
    
===================================================================
""")

slowPrint(f"Is this right? Something itches. Maybe you can {s.g + s.ga}think{s.x}?")

while True:
    print(f'')

    command = input(s.r + "> " + s.x)

    filler_words = ["at", "the", "a", "an", "to", "about", "of"]

    words = command.lower().split()
    words = [word for word in words if word not in filler_words]
    
    if len(words) > 0:
        verb = words[0]
    if len(words) > 1:
        noun = words[1]

# VERBS
# ===========================================================
#    THINK about
#    REMEMBER something
#    CREATE something
#
#    Think > 
#    Produces thoughts containing MEMORIES
#
#    Remember >
#    Stores a MEMORY
#
#    Create >
#    Creates the MEMORY as location
# ===========================================================


    if verb == "look":

        if len(words) == 1:
                print(player.location.description)

        else:
            for item in player.location.items:
                if item.name == noun:
                    print(item.description)
            for item in player.inventory:
                if item.name == noun:
                    print(item.description)

    # verb and noun generator
    if verb == "remember":
        if len(words) == 1:
            if player.location.is_memorable ==True:
                print("You remember the {}".format(player.location.name))
                player.memory.append(player.location)
            else:
                print("Nah.")

        else:           
            for item in player.location.items:
                if item.name == noun:
                    # Check is it movable
                    if item.is_movable:
                        print("You remember the {}".format(item.name))
                        # Remove from room
                        player.location.items.remove(item)
                        # Add to player's inventory
                        player.memory.append(item)
                    else:
                        print("Sorry, you can't move that.")
                        
    if verb == "think":

        if len(words) == 1:
 #           thoughts in player.location.thoughts
            thought = random.choice(player.location.thoughts)
            print(f"""
THE STATIC FLICKERS.

You think: '{thought.content}'""")

        else:
            for item in player.location.items:
                if item.name == noun:
                    print(item.thought)
            for item in player.inventory:
                if item.name == noun:
                    print(item.thought)

    # Movement
    if verb in ["n", "s", "e", "w", "u", "d", "go"]:
        if verb in player.location.exits:
            player.location = player.location.exits[verb]
            print(f"""
SOMEWHERE NEW APPEARS AROUND YOU.

    You have no idea where you are.
    
 """)


   
        # Inventory
    if verb in ["mem", "memory"]:
        print("You have the following: ")
        for item in player.memory:
            print(item.name)

        
        # Inventory
    if verb in ["inv", "inventory"]:
        print("You have the following: ")
        for item in player.inventory:
            print(item.name)
    
    # other commands
    if command.lower() == "end":
        print("The room closes. The fades to your reality once more.")
        break

    if command.lower() == "help":
        print("Try to look.")

 
