
class Emotions(object):
    def __init__(self, name, txt):
        self.name = name
        self.txt = txt

class Space(object):
    def __init__(self, name, txt_look, txt_mem, txt_think, echo_look, echo_mem, echo_think, is_memorable, is_changeable):
        self.name = name
        self.txt_look = txt_look            # Text for look
        self.txt_mem = txt_mem              # Text for remember
        self.txt_think = txt_think          # Text for think
        self.echo_look = echo_look          # Text for echo-look  response
        self.echo_mem = echo_mem            # Text for echo-mem   response
        self.echo_think = echo_think        # Text for echo-think response
        self.is_memorable = is_memorable    # Place can be stor to mem
        self.is_changeable = is_changeable  # Place can be changed somehow
        self.feelings = []                  # Feelings stor here
        self.thoughts = []                  # Thoughts for loop on think
        self.concepts = []                  # Concepts contained inside Space
        self.spaces = []                    # Spaces contained within Space
        self.exits = {}                     # I MEAN CAN YOU EVEN GO ANYWHERE?

class You(object):
    def __init__(self, name, space):
        self.name = name
        self.space = space                 # Where are you?
        self.feelings = []                  # How ya feeling?
        self.memory = []                    # Memorrrrieeeeeeeesssssss, alll alone in the....mind?

class Concept(object):
    def __init__(self, name, title_look, title_think, title_mem, txt_look, txt_mem, txt_think, echo_look, echo_think,  echo_mem, is_memorable):
        self.name = name
        self.title_look = title_look
        self.title_think = title_think
        self.title_mem = title_mem
        self.txt_look = txt_look            # Text for look (post light?)
        self.txt_mem = txt_mem              # Text for remember
        self.txt_think = txt_think          # Text for think
        self.echo_look = echo_look          # Text for echo-look  response
        self.echo_mem = echo_mem            # Text for echo-mem   response
        self.echo_think = echo_think        # Text for echo-think response
        self.is_memorable = is_memorable    # Place can be stor to mem
        self.thoughts = []                  # Thoughts for loop on think
        self.concepts = []                  # Concepts contained inside Concept
        self.spaces = []                    # Spaces contained within Space

class Thought(object):
    def __init__(self, id,  name, txt):
        self.name = name            
        self.txt = txt                      # Text of the thought

class Combine(object):
    """
    consider A with B — both must already be in you.memory (by .name).
    unlock_space: optional Space object · field rewrites (you.space = unlock_space).
    You write txt / echo by playing. Thin stubs are fine.
    """
    def __init__(self, a, b, txt, echo, unlock_space=None, trigger_light=False, echo_unlock=None, grant=None):
        self.a = a.lower().strip()          # concept or space name
        self.b = b.lower().strip()
        self.txt = txt                      # what happens when you braid them
        self.echo = echo                    # Echo answers
        self.unlock_space = unlock_space    # Space or None
        self.trigger_light = trigger_light  # who+static / shock of success
        self.echo_unlock = echo_unlock      # extra Echo after the field moves
        self.grant = list(grant or [])      # objects to put in memory on braid