
import random

def generate_quote():
    quotes = [
        "\"The only impossible journey is the one you never begin.\" - Sarah Ban Breathnach",
        "\"Character is power.\" - Booker T. Washington",
        "\"Responsive is better than fast.\" - Zen of Python",
        "\"Design for failure.\" - Zen of Python",
        "\"The only thing we have to fear is fear itself.\" - Franklin D. Roosevelt",
        "\"If you are going through hell, keep going.\" - Winston Churchill",
        "\"The best way to get started is to quit talking and begin doing.\" - Walt Disney",
        "\"Talk is cheap. Show me the code.\" - Linus Torvalds",   
        "\"The only impossible journey is the one you never begin.\" — Bear Grylls",
        "\"Don't cry because it's over, smile because it happened.\" — Ludwig Jacobowski",
        "\"Do stuff. Be clenched, curious. Not waiting for inspiration's shove or society's kiss on your forehead. Pay attention.\" — Susan Sontag",
        "\"Talk is cheap. Show me the code.\" — Linus Torvalds" 
    ]
    return random.choice(quotes)