import sys
import time
import random

FAST_MODE = True # true when testing


class paintIt:
    red = '\033[91m'
    sHg = '\033[102m'
    sHb = '\033[1;33;44m'
    sHy = '\033[103m'
    yellow = '\033[93m'
    sHr = '\033[101m'
    sHYr = '\033[1;31;43m'
    sHB = '\033[0;100m'
    g = '\033[1;92m'
    gray = '\033[1;30;49m'
    HWr = '\033[0;31;47m'
    blue = '\033[94m'
    y = '\033[93m'
    sU = '\033[4m'
    sB = "\033[2m"
    x = '\033[00m'


SPEAKER_COLORS = {
    "BⱯ": paintIt.blue,
    "ꓘⱯ": paintIt.g,
    "ioio": paintIt.yellow,
    "sys9": paintIt.g
}



def speakerPrompt(speaker, text):
    color = SPEAKER_COLORS.get(speaker, paintIt.gray)
    print(" " + color + speaker + "    ", end="", flush=True)
    typing(text + paintIt.x)
    timerM()

def prompterClr(speaker, text, color):
    print(" " + color + speaker + "  ", end="", flush=True)
    typing(text + paintIt.x)
    timerM()

def prompter(speaker, text, color):
    print(" " + color + speaker + "  " + paintIt.x, end="", flush=True)
    typing(text + paintIt.x)
    timerM()

def prompt(text):
    print(paintIt.red + text + paintIt.x, end="", flush=True)


def promptBara(text, text2):
    print(paintIt.blue + text, end="", flush=True)
    slowPrint(text2 + paintIt.x)
    timerM()


def promptKK(text, text2):
    print(paintIt.gray + text, end="", flush=True)
    slowPrint(text2 + paintIt.x)
    timerM()


def slowPrint(string, speed=0.0005):
    for char in string:
        sys.stdout.write(char)
        sys.stdout.flush()
        if not FAST_MODE:
           time.sleep(speed)
    print("", flush=True)
    if not FAST_MODE:
        time.sleep(0.8)

def typing(string, speed=0.02):
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
def timerM(speed=0.3):
    if not FAST_MODE:
        time.sleep(speed)
def timerF(speed=0.2):
    if not FAST_MODE:
        time.sleep(speed)

def echoPrintY(text):
    print(paintIt.y + " E C H O " + paintIt.x, flush=True, end="  ")
    timerS()
    slowPrint(text)
    timerM()

def echoPrint(text):
    print(paintIt.red + " E C H O " + paintIt.x, flush=True, end="  ")
    timerS()
    slowPrint(text)
    timerM()

def thoughtPrint(text):
    print(paintIt.y + " SDK 808 " + paintIt.x, flush=True, end="  ")
    timerS()
    slowPrint(text)
    timerM()

def event_success(text):
    print(f"  {paintIt.sHg} {text} {paintIt.x}", flush=True)
    
def event_partialsuccess(text):
    print(f" {paintIt.gray} {paintIt.sHy} {text} {paintIt.x}", flush=True, end="")

def error(text):
    print("    " + paintIt.sHr + "| err " + paintIt.x, flush=True, end="")
    timerF()
    print(f"{paintIt.red} {text} {paintIt.x} ")
    timerM()

def success(text):
    print("  " + paintIt.sHg + " SUCCESS " + paintIt.x, flush=True, end="")
    timerF()
    print(f" {paintIt.y} {text} {paintIt.x} ")
    timerM()

def warning(text):
    print("\n  " + paintIt.sHB + " WARNING " + paintIt.x, flush=True, end="")
    timerF()
    print(f" {text} \n")
    timerM()

def event_fail(text):
    print("  " + paintIt.sHYr + f" {random.randint(1000000, 9999999)} " + paintIt.x, flush=True, end="")
    timerF()
    print(f" {text} \n")
    timerM()

def fail(text):
    print("  " + paintIt.sHr + " ERR-808 " + paintIt.x, flush=True, end="")
    timerF()
    print(f" {paintIt.red} {text} {paintIt.x} ")
    timerM()

def errorb(text):
    print(paintIt.sHr + " er0r " + paintIt.x, flush=True, end="")
    timerF()
    print(f"{paintIt.red}{paintIt.sU} {text} {paintIt.x} ")
    timerM()

def load_bar(width=25, char=" █"):
    print("")
    slowPrint(f"{char * width}")
    print("")
    
def Spacer():
    print()